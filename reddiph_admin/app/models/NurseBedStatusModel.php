<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/HospitalBedInventoryModel.php';

class NurseBedStatusModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    public static function data(int $nurseId): array
    {
        $connection = self::connection();
        $profile = $connection->prepare(
            "SELECT u.hospital_id, COALESCE(h.name, u.hospital_name) AS hospital_name
             FROM user_type u
             LEFT JOIN hospitals h ON h.id = u.hospital_id
             WHERE u.id = :nurse_id AND u.role = 'nurse'
             LIMIT 1"
        );
        $profile->execute(['nurse_id' => $nurseId]);
        $nurse = $profile->fetch();
        if (!$nurse || $nurse['hospital_id'] === null) {
            throw new RuntimeException('No hospital is assigned to this nurse account.');
        }

        $hospitalId = (int) $nurse['hospital_id'];
        HospitalBedInventoryModel::initialize($connection, $hospitalId);

        $bedsQuery = $connection->prepare(
            'SELECT id, bed_type, bed_number, status
             FROM hospital_beds
             WHERE hospital_id = :hospital_id
             ORDER BY FIELD(bed_type, \'er\', \'pediatric\'), bed_number'
        );
        $bedsQuery->execute(['hospital_id' => $hospitalId]);
        $beds = ['er' => [], 'pediatric' => []];
        $counts = ['ready' => 0, 'preparing' => 0, 'occupied' => 0, 'cleaning' => 0];
        foreach ($bedsQuery->fetchAll() as $row) {
            $status = (string) $row['status'];
            $type = (string) $row['bed_type'];
            $bed = [
                'id' => (int) $row['id'],
                'number' => (int) $row['bed_number'],
                'type' => $type,
                'status' => $status,
                'label' => ucfirst($status),
                'incidentId' => null,
            ];
            $beds[$type][] = $bed;
            $counts[$status]++;
        }

        $assignments = $connection->prepare(
            "SELECT incidents.nurse_bed_id, incidents.incident_id
             FROM incidents
             INNER JOIN hospital_beds assigned_bed
                ON assigned_bed.id = incidents.nurse_bed_id
               AND assigned_bed.hospital_id = :hospital_id
             WHERE incidents.nurse_bed_id IS NOT NULL
               AND incidents.status IN ('critical', 'urgent', 'dispatched', 'stable')
             ORDER BY incidents.updated_at DESC, incidents.incident_id DESC"
        );
        $assignments->execute(['hospital_id' => $hospitalId]);
        $incidentByBed = [];
        foreach ($assignments->fetchAll() as $assignment) {
            $incidentByBed[(int) $assignment['nurse_bed_id']] ??= (int) $assignment['incident_id'];
        }
        foreach (['er', 'pediatric'] as $type) {
            foreach ($beds[$type] as &$bed) {
                $bed['incidentId'] = $incidentByBed[$bed['id']] ?? null;
            }
            unset($bed);
        }

        $summary = [];
        foreach (['ready' => 'Ready', 'preparing' => 'Preparing', 'occupied' => 'Occupied', 'cleaning' => 'Cleaning'] as $status => $label) {
            $summary[] = ['label' => $label, 'count' => $counts[$status], 'class' => $status];
        }

        return [
            'hospitalName' => (string) $nurse['hospital_name'],
            'summary' => $summary,
            'counts' => $counts,
            'erBeds' => $beds['er'],
            'pediatricBeds' => $beds['pediatric'],
            'incidentOptions' => self::incidentOptions($nurseId),
        ];
    }

    private static function incidentOptions(int $nurseId): array
    {
        $statement = self::connection()->prepare(
            "SELECT
                i.incident_id,
                i.incident_type,
                i.status,
                i.patient_name,
                i.patient_gender,
                i.patient_age,
                i.nurse_bed_id
             FROM incidents i
             INNER JOIN user_type nurse
                ON nurse.id = :nurse_id
               AND nurse.role = 'nurse'
                AND (
                     nurse.hospital_id = i.hospital_id
                     OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)
                )
             WHERE i.status IN ('critical', 'urgent', 'dispatched', 'stable')
               AND COALESCE((
                    SELECT current_staff.is_available
                    FROM staff current_staff
                    WHERE current_staff.user_id = nurse.id
                      AND (current_staff.hospital_id = nurse.hospital_id OR current_staff.hospital_id IS NULL)
                    ORDER BY (current_staff.hospital_id = nurse.hospital_id) DESC, current_staff.id DESC
                    LIMIT 1
               ), 1) = 1
               AND EXISTS (
                    SELECT 1
                    FROM staff_alerts received_alert
                    WHERE received_alert.incident_id = i.incident_id
                      AND received_alert.recipient_user_id = nurse.id
                      AND received_alert.recipient_role = 'nurse'
                      AND received_alert.channel = 'admin'
               )
             ORDER BY
                CASE i.status
                    WHEN 'critical' THEN 1
                    WHEN 'urgent' THEN 2
                    WHEN 'dispatched' THEN 2
                    ELSE 3
                END,
                i.reported_at DESC,
                i.incident_id DESC"
        );
        $statement->execute(['nurse_id' => $nurseId]);

        return array_map(static function (array $row): array {
            $patientName = trim((string) ($row['patient_name'] ?? ''));
            if ($patientName === '') {
                $patientName = ucfirst((string) ($row['patient_gender'] ?? 'unknown'));
                if ($patientName === 'Unknown') {
                    $patientName = 'Patient details unavailable';
                } elseif ($row['patient_age'] !== null) {
                    $patientName .= ', ' . (int) $row['patient_age'];
                }
            }

            return [
                'id' => (int) $row['incident_id'],
                'title' => (string) $row['incident_type'],
                'patient' => $patientName,
                'status' => (string) $row['status'],
                'bedId' => $row['nurse_bed_id'] === null ? null : (int) $row['nurse_bed_id'],
            ];
        }, $statement->fetchAll());
    }

    public static function updateStatus(int $nurseId, int $bedId, string $status, ?int $incidentId): ?array
    {
        if (!in_array($status, ['ready', 'preparing', 'occupied', 'cleaning'], true)) {
            throw new InvalidArgumentException('Choose a valid bed status.');
        }
        if ($status === 'occupied' && ($incidentId === null || $incidentId <= 0)) {
            throw new InvalidArgumentException('Choose an incoming incident for an occupied bed.');
        }

        $connection = self::connection();
        $connection->beginTransaction();
        try {
            $hospitalQuery = $connection->prepare(
                'SELECT hospital_id FROM user_type WHERE id = :nurse_id AND role = \'nurse\' LIMIT 1'
            );
            $hospitalQuery->execute(['nurse_id' => $nurseId]);
            $hospitalId = $hospitalQuery->fetchColumn();
            if ($hospitalId === false || $hospitalId === null) {
                throw new RuntimeException('No hospital is assigned to this nurse account.');
            }
            $hospitalId = (int) $hospitalId;

            $lock = $connection->prepare('SELECT id FROM hospitals WHERE id = :hospital_id LIMIT 1 FOR UPDATE');
            $lock->execute(['hospital_id' => $hospitalId]);
            if ($lock->fetchColumn() === false) {
                throw new RuntimeException('Hospital record was not found.');
            }

            $current = $connection->prepare(
                'SELECT status FROM hospital_beds WHERE id = :bed_id AND hospital_id = :hospital_id LIMIT 1 FOR UPDATE'
            );
            $current->execute(['bed_id' => $bedId, 'hospital_id' => $hospitalId]);
            if ($current->fetchColumn() === false) {
                $connection->rollBack();
                return null;
            }

            if ($status === 'occupied') {
                $incident = $connection->prepare(
                    "SELECT i.nurse_bed_id
                     FROM incidents i
                     INNER JOIN user_type nurse
                        ON nurse.id = :nurse_id
                       AND nurse.role = 'nurse'
                        AND (
                             nurse.hospital_id = i.hospital_id
                             OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)
                        )
                     WHERE i.incident_id = :incident_id
                       AND i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                       AND (i.nurse_bed_id IS NULL OR i.nurse_bed_id = :bed_id)
                       AND COALESCE((
                            SELECT current_staff.is_available
                            FROM staff current_staff
                            WHERE current_staff.user_id = nurse.id
                              AND (current_staff.hospital_id = nurse.hospital_id OR current_staff.hospital_id IS NULL)
                            ORDER BY (current_staff.hospital_id = nurse.hospital_id) DESC, current_staff.id DESC
                            LIMIT 1
                       ), 1) = 1
                       AND EXISTS (
                            SELECT 1
                            FROM staff_alerts received_alert
                            WHERE received_alert.incident_id = i.incident_id
                              AND received_alert.recipient_user_id = nurse.id
                              AND received_alert.recipient_role = 'nurse'
                              AND received_alert.channel = 'admin'
                       )
                     LIMIT 1 FOR UPDATE"
                );
                $incident->execute([
                    'nurse_id' => $nurseId,
                    'incident_id' => $incidentId,
                    'bed_id' => $bedId,
                ]);
                if ($incident->fetchColumn() === false) {
                    throw new InvalidArgumentException('Choose an active incident assigned to your nurse account that is not assigned to another bed.');
                }

                $otherActiveAssignment = $connection->prepare(
                    "SELECT incident_id
                     FROM incidents
                     WHERE nurse_bed_id = :bed_id
                       AND incident_id <> :incident_id
                       AND status IN ('critical', 'urgent', 'dispatched', 'stable')
                     LIMIT 1 FOR UPDATE"
                );
                $otherActiveAssignment->execute(['bed_id' => $bedId, 'incident_id' => $incidentId]);
                if ($otherActiveAssignment->fetchColumn() !== false) {
                    throw new InvalidArgumentException('This bed is already assigned to another active incident.');
                }

                $assign = $connection->prepare(
                    'UPDATE incidents SET nurse_bed_id = :bed_id WHERE incident_id = :incident_id'
                );
                $assign->execute(['bed_id' => $bedId, 'incident_id' => $incidentId]);
            }

            $update = $connection->prepare(
                'UPDATE hospital_beds SET status = :status WHERE id = :bed_id AND hospital_id = :hospital_id'
            );
            $update->execute(['status' => $status, 'bed_id' => $bedId, 'hospital_id' => $hospitalId]);
            HospitalBedInventoryModel::synchronizeAggregates($connection, $hospitalId);
            $connection->commit();

            return self::data($nurseId);
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
