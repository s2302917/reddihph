<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/HospitalBedInventoryModel.php';

class NurseNotifiedCasesModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    public static function profile(int $nurseId): ?array
    {
        $statement = self::connection()->prepare(
            "SELECT
                u.id,
                u.full_name,
                u.role,
                u.hospital_id,
                u.profile_image,
                COALESCE(h.name, u.hospital_name) AS hospital_name,
                COALESCE((
                    SELECT s.is_available
                    FROM staff s
                    WHERE s.user_id = u.id
                      AND (s.hospital_id = u.hospital_id OR s.hospital_id IS NULL)
                    ORDER BY (s.hospital_id = u.hospital_id) DESC, s.id DESC
                    LIMIT 1
                ), 1) AS is_available
            FROM user_type u
            LEFT JOIN hospitals h ON h.id = u.hospital_id
            WHERE u.id = :nurse_id AND u.role = 'nurse'
            LIMIT 1"
        );
        $statement->execute(['nurse_id' => $nurseId]);
        $profile = $statement->fetch();
        if (!$profile) {
            return null;
        }

        $parts = preg_split('/\s+/', trim((string) $profile['full_name']), -1, PREG_SPLIT_NO_EMPTY);
        $profile['initials'] = '';
        foreach (array_slice($parts ?: [], 0, 2) as $part) {
            $profile['initials'] .= strtoupper(substr($part, 0, 1));
        }
        $profile['coordinator_role'] = ucfirst((string) $profile['role']);
        $profile['profile_image_url'] = userProfileImageUrl($profile['profile_image'] ?? null);
        $profile['is_available'] = (bool) $profile['is_available'];

        return $profile;
    }

    public static function cases(int $nurseId): array
    {
        $statement = self::connection()->prepare(
            "SELECT
                i.incident_id,
                i.incident_type,
                i.status,
                i.location,
                i.patient_name,
                i.patient_gender,
                i.patient_age,
                i.eta_minutes,
                i.responder_name,
                i.hospital_name,
                i.reported_at,
                i.notes,
                i.nurse_heart_rate,
                i.nurse_blood_pressure,
                i.nurse_spo2,
                i.nurse_gcs,
                i.nurse_notes,
                i.nurse_bed_id,
                i.nurse_prep_completed,
                i.nurse_prep_completed_by,
                i.nurse_prep_completed_at,
                preparer.full_name AS prep_completed_by_name,
                i.transport_name,
                i.transport_type,
                a.status AS ambulance_status,
                a.eta_minutes AS ambulance_eta_minutes,
                a.updated_at AS ambulance_updated_at,
                a.arrived_at AS ambulance_arrived_at,
                assigned_bed.bed_type AS nurse_bed_type,
                assigned_bed.bed_number AS nurse_bed_number,
                COALESCE(h.name, i.hospital_name) AS registered_hospital
            FROM incidents i
            INNER JOIN user_type nurse
                ON nurse.id = :nurse_id
                AND nurse.role = 'nurse'
                AND (
                    nurse.hospital_id = i.hospital_id
                    OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)
                )
            LEFT JOIN hospitals h ON h.id = nurse.hospital_id
            LEFT JOIN user_type preparer
                ON preparer.id = i.nurse_prep_completed_by
                AND preparer.role = 'nurse'
            LEFT JOIN ambulances a
                ON a.call_sign = i.transport_name
                AND a.hospital_id = nurse.hospital_id
            LEFT JOIN hospital_beds assigned_bed
                ON assigned_bed.id = i.nurse_bed_id
                AND assigned_bed.hospital_id = nurse.hospital_id
            WHERE i.status IN ('critical', 'urgent', 'dispatched', 'stable')
              AND EXISTS (
                    SELECT 1
                    FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = i.incident_id
              )
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
                      AND EXISTS (
                          SELECT 1
                          FROM staff duty_staff
                          INNER JOIN staff_shifts duty_shift
                              ON duty_shift.staff_id = duty_staff.id
                          WHERE duty_staff.user_id = nurse.id
                            AND (duty_staff.hospital_id = nurse.hospital_id OR duty_staff.hospital_id IS NULL)
                            AND received_alert.created_at >= TIMESTAMP(duty_shift.shift_date, duty_shift.shift_start)
                            AND received_alert.created_at < DATE_ADD(
                                TIMESTAMP(duty_shift.shift_date, duty_shift.shift_end),
                                INTERVAL IF(duty_shift.shift_end <= duty_shift.shift_start, 1, 0) DAY
                            )
                      )
              )
            ORDER BY
                CASE i.status
                    WHEN 'critical' THEN 1
                    WHEN 'urgent' THEN 2
                    WHEN 'dispatched' THEN 2
                    WHEN 'stable' THEN 3
                    ELSE 4
                END,
                i.reported_at DESC,
                i.incident_id DESC"
        );
        $statement->execute(['nurse_id' => $nurseId]);

        return array_map(static function (array $row): array {
            $status = (string) $row['status'];
            $patientName = trim((string) ($row['patient_name'] ?? ''));
            if ($patientName === '') {
                $gender = ucfirst((string) ($row['patient_gender'] ?? 'unknown'));
                $patientName = $gender === 'Unknown' ? 'Patient details unavailable' : $gender;
                if ($row['patient_age'] !== null) {
                    $patientName .= ', ' . (int) $row['patient_age'];
                }
            }

            $transportStatus = (string) ($row['ambulance_status'] ?? '');
            $etaMinutes = $row['ambulance_eta_minutes'] !== null
                ? (int) $row['ambulance_eta_minutes']
                : ($row['eta_minutes'] === null ? null : (int) $row['eta_minutes']);
            $arrivalLabel = 'Awaiting transport';
            $arrivalAt = null;
            if ($transportStatus === 'arrived') {
                $arrivalLabel = 'Arrived';
                $arrivalAt = $row['ambulance_arrived_at']
                    ? (new DateTimeImmutable((string) $row['ambulance_arrived_at']))->format('g:i A')
                    : 'Now';
            } elseif ($transportStatus === 'en_route') {
                $arrivalLabel = 'En route';
                if ($etaMinutes !== null) {
                    $arrivalAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
                        ->modify('+' . $etaMinutes . ' minutes')
                        ->format('g:i A');
                }
            }

            return [
                'id' => (int) $row['incident_id'],
                'severity' => $status === 'dispatched' ? 'Urgent' : ucfirst($status),
                'status' => $status,
                'unit' => trim((string) ($row['transport_name'] ?? '')) !== ''
                    ? (string) $row['transport_name']
                    : 'Transport not assigned',
                'title' => (string) $row['incident_type'],
                'patient' => $patientName,
                'location' => (string) $row['location'],
                'hospital' => (string) $row['registered_hospital'],
                'reportedAt' => (string) $row['reported_at'],
                'responder' => trim((string) ($row['responder_name'] ?? '')) ?: 'Responder not assigned',
                'transportType' => (string) ($row['transport_type'] ?? ''),
                'arrivalStatus' => $arrivalLabel,
                'arrival' => $arrivalAt,
                'etaMinutes' => $etaMinutes,
                'heartRate' => $row['nurse_heart_rate'] === null ? '' : (string) $row['nurse_heart_rate'],
                'bp' => (string) ($row['nurse_blood_pressure'] ?? ''),
                'spo2' => $row['nurse_spo2'] === null ? '' : (string) $row['nurse_spo2'],
                'consciousness' => $row['nurse_gcs'] === null ? '' : (string) $row['nurse_gcs'],
                'note' => (string) ($row['notes'] ?? ''),
                'nurseNote' => (string) ($row['nurse_notes'] ?? ''),
                'bedId' => $row['nurse_bed_id'] === null ? null : (int) $row['nurse_bed_id'],
                'bedLabel' => $row['nurse_bed_type'] === null
                    ? 'Not assigned'
                    : (($row['nurse_bed_type'] === 'er' ? 'ER Bed ' : 'Pediatric Bed ') . (int) $row['nurse_bed_number']),
                'prepCompleted' => (bool) $row['nurse_prep_completed'],
                'prepCompletedBy' => $row['nurse_prep_completed_by'] === null ? null : (int) $row['nurse_prep_completed_by'],
                'prepCompletedByName' => (string) ($row['prep_completed_by_name'] ?? ''),
                'prepCompletedAt' => $row['nurse_prep_completed_at'],
            ];
        }, $statement->fetchAll());
    }

    public static function summary(int $nurseId): array
    {
        $statement = self::connection()->prepare(
            "SELECT
                COUNT(*) AS incoming_count,
                COALESCE(SUM(
                    i.nurse_prep_completed = 1
                ), 0) AS prep_completed_count
            FROM incidents i
            INNER JOIN user_type nurse
                ON nurse.id = :nurse_id
                AND nurse.role = 'nurse'
                AND (
                    nurse.hospital_id = i.hospital_id
                    OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)
                )
            WHERE i.status IN ('critical', 'urgent', 'dispatched', 'stable')
              AND EXISTS (
                    SELECT 1
                    FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = i.incident_id
              )
              AND COALESCE((
                    SELECT current_staff.is_available
                    FROM staff current_staff
                    WHERE current_staff.user_id = nurse.id
                      AND (current_staff.hospital_id = nurse.hospital_id OR current_staff.hospital_id IS NULL)
                    ORDER BY (current_staff.hospital_id = nurse.hospital_id) DESC, current_staff.id DESC
                    LIMIT 1
              ), 1) = 1
              AND EXISTS (
                    SELECT 1 FROM staff_alerts received_alert
                    WHERE received_alert.incident_id = i.incident_id
                      AND received_alert.recipient_user_id = nurse.id
                      AND received_alert.recipient_role = 'nurse'
                      AND received_alert.channel = 'admin'
              )"
        );
        $statement->execute(['nurse_id' => $nurseId]);
        $row = $statement->fetch() ?: [];

        return [
            'incoming' => (int) ($row['incoming_count'] ?? 0),
            'prepCompleted' => (int) ($row['prep_completed_count'] ?? 0),
        ];
    }

    public static function nextArrival(int $nurseId): ?array
    {
        $statement = self::connection()->prepare(
            "SELECT
                i.incident_id,
                i.incident_type,
                a.status AS ambulance_status,
                COALESCE(a.eta_minutes, i.eta_minutes) AS eta_minutes
            FROM incidents i
            INNER JOIN user_type nurse
                ON nurse.id = :nurse_id
                AND nurse.role = 'nurse'
                AND (
                    nurse.hospital_id = i.hospital_id
                    OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)
                )
            INNER JOIN ambulances a
                ON a.call_sign = i.transport_name
                AND a.hospital_id = nurse.hospital_id
                AND a.status IN ('en_route', 'arrived')
            WHERE i.status IN ('critical', 'urgent', 'dispatched', 'stable')
              AND EXISTS (
                    SELECT 1
                    FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = i.incident_id
              )
              AND COALESCE((
                    SELECT current_staff.is_available
                    FROM staff current_staff
                    WHERE current_staff.user_id = nurse.id
                      AND (current_staff.hospital_id = nurse.hospital_id OR current_staff.hospital_id IS NULL)
                    ORDER BY (current_staff.hospital_id = nurse.hospital_id) DESC, current_staff.id DESC
                    LIMIT 1
              ), 1) = 1
              AND EXISTS (
                    SELECT 1 FROM staff_alerts received_alert
                    WHERE received_alert.incident_id = i.incident_id
                      AND received_alert.recipient_user_id = nurse.id
                      AND received_alert.recipient_role = 'nurse'
                      AND received_alert.channel = 'admin'
              )
            ORDER BY
                CASE WHEN a.status = 'arrived' THEN 0 ELSE 1 END,
                COALESCE(a.eta_minutes, i.eta_minutes, 2147483647),
                i.reported_at ASC,
                i.incident_id ASC
            LIMIT 1"
        );
        $statement->execute(['nurse_id' => $nurseId]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        $etaMinutes = $row['eta_minutes'] === null ? null : (int) $row['eta_minutes'];
        $arrivalTime = $row['ambulance_status'] === 'arrived'
            ? 'Arrived'
            : ($etaMinutes === null
                ? 'ETA unavailable'
                : (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
                    ->modify('+' . $etaMinutes . ' minutes')
                    ->format('g:i A'));

        return [
            'incidentId' => (int) $row['incident_id'],
            'incident' => (string) $row['incident_type'],
            'status' => (string) $row['ambulance_status'],
            'arrival' => $arrivalTime,
            'etaMinutes' => $etaMinutes,
        ];
    }

    public static function bedAvailability(int $nurseId): array
    {
        $connection = self::connection();
        $hospitalQuery = $connection->prepare(
            "SELECT hospital_id
             FROM user_type
             WHERE id = :nurse_id AND role = 'nurse'
             LIMIT 1"
        );
        $hospitalQuery->execute(['nurse_id' => $nurseId]);
        $hospitalId = $hospitalQuery->fetchColumn();
        if ($hospitalId === false || $hospitalId === null) {
            return ['er' => 0, 'pediatric' => 0];
        }
        $inventoryQuery = $connection->prepare(
            'SELECT COUNT(*) FROM hospital_beds WHERE hospital_id = :hospital_id'
        );
        $inventoryQuery->execute(['hospital_id' => (int) $hospitalId]);
        if ((int) $inventoryQuery->fetchColumn() === 0) {
            HospitalBedInventoryModel::initialize($connection, (int) $hospitalId);
        }

        $statement = self::connection()->prepare(
            "SELECT bed_type, COUNT(*) AS available_count
             FROM hospital_beds
            WHERE hospital_id = :hospital_id AND status IN ('ready', 'preparing')
             GROUP BY bed_type"
        );
        $statement->execute(['hospital_id' => (int) $hospitalId]);
        $counts = ['er' => 0, 'pediatric' => 0];
        foreach ($statement->fetchAll() as $row) {
            $counts[(string) $row['bed_type']] = (int) $row['available_count'];
        }

        return $counts;
    }

    public static function beds(int $nurseId): array
    {
        $connection = self::connection();
        $statement = $connection->prepare(
            "SELECT b.id, b.bed_type, b.bed_number, b.status
             FROM hospital_beds b
             INNER JOIN user_type nurse
                ON nurse.hospital_id = b.hospital_id
               AND nurse.id = :nurse_id
               AND nurse.role = 'nurse'
             ORDER BY FIELD(b.bed_type, 'er', 'pediatric'), b.bed_number"
        );
        $statement->execute(['nurse_id' => $nurseId]);

        return array_map(static function (array $bed): array {
            $type = (string) $bed['bed_type'];
            $status = (string) $bed['status'];
            return [
                'id' => (int) $bed['id'],
                'type' => $type,
                'number' => (int) $bed['bed_number'],
                'status' => $status,
                'label' => ($type === 'er' ? 'ER Bed ' : 'Pediatric Bed ') . (int) $bed['bed_number'] .
                    ' · ' . ucfirst($status),
            ];
        }, $statement->fetchAll());
    }

    public static function setAvailability(int $nurseId, bool $available): void
    {
        $connection = self::connection();
        $connection->beginTransaction();

        try {
            $nurseQuery = $connection->prepare(
                "SELECT id, hospital_id, full_name, profile_image, duty_start, duty_end
                FROM user_type
                WHERE id = :nurse_id AND role = 'nurse'
                LIMIT 1
                FOR UPDATE"
            );
            $nurseQuery->execute(['nurse_id' => $nurseId]);
            $nurse = $nurseQuery->fetch();
            if (!$nurse) {
                throw new RuntimeException('Nurse account not found.');
            }

            $staffQuery = $connection->prepare(
                "SELECT id
                FROM staff
                WHERE user_id = :nurse_id
                  AND (hospital_id = :hospital_id OR hospital_id IS NULL)
                ORDER BY (hospital_id = :preferred_hospital_id) DESC, id DESC
                LIMIT 1
                FOR UPDATE"
            );
            $staffQuery->execute([
                'nurse_id' => $nurseId,
                'hospital_id' => $nurse['hospital_id'],
                'preferred_hospital_id' => $nurse['hospital_id'],
            ]);
            $staffId = $staffQuery->fetchColumn();

            if ($staffId !== false) {
                $update = $connection->prepare(
                    'UPDATE staff SET is_available = :is_available WHERE id = :staff_id'
                );
                $update->execute([
                    'is_available' => $available ? 1 : 0,
                    'staff_id' => $staffId,
                ]);
            } else {
                $insert = $connection->prepare(
                    'INSERT INTO staff (hospital_id, user_id, profile_image, full_name, department, is_available)
                    VALUES (:hospital_id, :user_id, :profile_image, :full_name, :department, :is_available)'
                );
                $insert->execute([
                    'hospital_id' => $nurse['hospital_id'],
                    'user_id' => $nurseId,
                    'profile_image' => $nurse['profile_image'],
                    'full_name' => $nurse['full_name'],
                    'department' => 'Nurse',
                    'is_available' => $available ? 1 : 0,
                ]);
                $staffId = $connection->lastInsertId();
            }

            $shiftStart = $nurse['duty_start'] !== null ? (string) $nurse['duty_start'] : null;
            $shiftEnd = $nurse['duty_end'] !== null ? (string) $nurse['duty_end'] : null;
            $shiftType = $shiftStart === null
                ? null
                : ((int) substr($shiftStart, 0, 2) < 12 ? 'morning' : 'evening');
            $shift = $connection->prepare(
                "INSERT INTO staff_shifts
                    (staff_id, shift_date, shift_type, shift_start, shift_end, shift_status)
                VALUES
                    (:staff_id, :shift_date, :shift_type, :shift_start, :shift_end, :shift_status)
                ON DUPLICATE KEY UPDATE shift_status = VALUES(shift_status)"
            );
            $shift->execute([
                'staff_id' => (int) $staffId,
                'shift_date' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'),
                'shift_type' => $shiftType,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'shift_status' => $available ? 'on_duty' : 'off_duty',
            ]);

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function updatePreparation(int $nurseId, int $incidentId, array $preparation): bool
    {
        $connection = self::connection();
        $connection->beginTransaction();

        try {
            $access = $connection->prepare(
                "SELECT
                    i.nurse_bed_id,
                    i.nurse_prep_completed,
                    nurse.hospital_id,
                    EXISTS (
                        SELECT 1 FROM ambulances arrived_transport
                        WHERE arrived_transport.call_sign = i.transport_name
                          AND arrived_transport.hospital_id = nurse.hospital_id
                          AND arrived_transport.status = 'arrived'
                    ) AS transport_arrived
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
                  AND EXISTS (
                      SELECT 1
                      FROM incident_patients submitted_patient
                      WHERE submitted_patient.incident_id = i.incident_id
                  )
                  AND COALESCE((
                        SELECT current_staff.is_available
                        FROM staff current_staff
                        WHERE current_staff.user_id = nurse.id
                          AND (current_staff.hospital_id = nurse.hospital_id OR current_staff.hospital_id IS NULL)
                        ORDER BY (current_staff.hospital_id = nurse.hospital_id) DESC, current_staff.id DESC
                        LIMIT 1
                  ), 1) = 1
                  AND EXISTS (
                        SELECT 1 FROM staff_alerts received_alert
                        WHERE received_alert.incident_id = i.incident_id
                          AND received_alert.recipient_user_id = nurse.id
                          AND received_alert.recipient_role = 'nurse'
                          AND received_alert.channel = 'admin'
                  )
                LIMIT 1
                FOR UPDATE"
            );
            $access->execute(['nurse_id' => $nurseId, 'incident_id' => $incidentId]);
            $incident = $access->fetch();
            if (!$incident) {
                $connection->rollBack();
                return false;
            }

            $completed = !empty($preparation['prepCompleted']);
            $bedId = $preparation['bedId'] === null ? null : (int) $preparation['bedId'];
            if ($completed && (
                $preparation['heartRate'] === '' ||
                $preparation['bloodPressure'] === '' ||
                $preparation['spo2'] === '' ||
                $preparation['gcs'] === '' ||
                $bedId === null
            )) {
                throw new InvalidArgumentException('Enter all vital signs and assign a bed before completing preparation.');
            }

            $hospitalId = $incident['hospital_id'] === null ? null : (int) $incident['hospital_id'];
            $previousBedId = $incident['nurse_bed_id'] === null ? null : (int) $incident['nurse_bed_id'];
            $bedStatus = null;
            if ($bedId !== null) {
                if ($hospitalId === null) {
                    throw new InvalidArgumentException('A registered hospital is required to assign a bed.');
                }
                $bedQuery = $connection->prepare(
                    'SELECT status FROM hospital_beds
                     WHERE id = :bed_id AND hospital_id = :hospital_id
                     LIMIT 1 FOR UPDATE'
                );
                $bedQuery->execute(['bed_id' => $bedId, 'hospital_id' => $hospitalId]);
                $bedStatus = $bedQuery->fetchColumn();
                if ($bedStatus === false) {
                    throw new InvalidArgumentException('Choose a bed belonging to your hospital.');
                }
                if ($bedId !== $previousBedId && $bedStatus !== 'ready') {
                    throw new InvalidArgumentException('The selected bed is no longer ready. Choose another bed.');
                }

                $usage = $connection->prepare(
                    "SELECT COUNT(*)
                     FROM incidents
                     WHERE nurse_bed_id = :bed_id
                       AND incident_id <> :incident_id
                       AND status IN ('critical', 'urgent', 'dispatched', 'stable')"
                );
                $usage->execute(['bed_id' => $bedId, 'incident_id' => $incidentId]);
                if ((int) $usage->fetchColumn() > 0) {
                    throw new InvalidArgumentException('That bed is already assigned to another active incident.');
                }
            }

            if ($previousBedId !== null && $previousBedId !== $bedId) {
                $release = $connection->prepare(
                    "UPDATE hospital_beds
                     SET status = 'ready'
                     WHERE id = :bed_id
                       AND hospital_id = :hospital_id
                       AND status = 'preparing'"
                );
                $release->execute(['bed_id' => $previousBedId, 'hospital_id' => $hospitalId]);
            }

            if ($bedId !== null) {
                $nextBedStatus = $completed && (bool) $incident['transport_arrived']
                    ? 'occupied'
                    : 'preparing';
                $setBedStatus = $connection->prepare(
                    'UPDATE hospital_beds
                     SET status = :status
                     WHERE id = :bed_id AND hospital_id = :hospital_id'
                );
                $setBedStatus->execute([
                    'status' => $nextBedStatus,
                    'bed_id' => $bedId,
                    'hospital_id' => $hospitalId,
                ]);
            }

            $update = $connection->prepare(
                "UPDATE incidents i
                INNER JOIN user_type nurse
                    ON nurse.id = :nurse_id
                    AND nurse.role = 'nurse'
                    AND (
                        nurse.hospital_id = i.hospital_id
                        OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)
                    )
                SET i.nurse_heart_rate = :heart_rate,
                    i.nurse_blood_pressure = :blood_pressure,
                    i.nurse_spo2 = :spo2,
                    i.nurse_gcs = :gcs,
                    i.nurse_notes = :nurse_notes,
                    i.nurse_bed_id = :bed_id,
                    i.nurse_prep_completed = :prep_completed,
                    i.nurse_prep_completed_by = CASE
                        WHEN :completed_by_flag = 1 THEN :completed_by
                        ELSE NULL
                    END,
                    i.nurse_prep_completed_at = CASE
                        WHEN :completed_at_flag = 1 THEN COALESCE(i.nurse_prep_completed_at, NOW())
                        ELSE NULL
                    END
                WHERE i.incident_id = :incident_id
                  AND i.status IN ('critical', 'urgent', 'dispatched', 'stable')"
            );
            $update->execute([
                'nurse_id' => $nurseId,
                'heart_rate' => $preparation['heartRate'] !== '' ? (int) $preparation['heartRate'] : null,
                'blood_pressure' => $preparation['bloodPressure'] !== '' ? $preparation['bloodPressure'] : null,
                'spo2' => $preparation['spo2'] !== '' ? (int) $preparation['spo2'] : null,
                'gcs' => $preparation['gcs'] !== '' ? (int) $preparation['gcs'] : null,
                'nurse_notes' => $preparation['nurseNote'] !== '' ? $preparation['nurseNote'] : null,
                'bed_id' => $bedId,
                'prep_completed' => $completed ? 1 : 0,
                'completed_by_flag' => $completed ? 1 : 0,
                'completed_by' => $nurseId,
                'completed_at_flag' => $completed ? 1 : 0,
                'incident_id' => $incidentId,
            ]);
            if ($previousBedId !== $bedId || $bedId !== null) {
                HospitalBedInventoryModel::synchronizeAggregates($connection, (int) $hospitalId);
            }
            $connection->commit();

            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
