<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/NurseNotifiedCasesModel.php';

class NurseHandoffLogModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    private static function notifiedIncidentsSql(): string
    {
        return "SELECT alert.incident_id, MIN(alert.created_at) AS notified_at
            FROM staff_alerts alert
            INNER JOIN user_type recipient
                ON recipient.id = alert.recipient_user_id
                AND recipient.role = 'nurse'
            INNER JOIN staff recipient_staff
                ON recipient_staff.id = (
                    SELECT matching_staff.id
                    FROM staff matching_staff
                    WHERE matching_staff.user_id = recipient.id
                      AND (matching_staff.hospital_id = recipient.hospital_id OR matching_staff.hospital_id IS NULL)
                    ORDER BY (matching_staff.hospital_id = recipient.hospital_id) DESC, matching_staff.id DESC
                    LIMIT 1
                )
            INNER JOIN staff_shifts duty_shift
                ON duty_shift.staff_id = recipient_staff.id
               AND alert.created_at >= TIMESTAMP(duty_shift.shift_date, duty_shift.shift_start)
               AND alert.created_at < DATE_ADD(
                    TIMESTAMP(duty_shift.shift_date, duty_shift.shift_end),
                    INTERVAL IF(duty_shift.shift_end <= duty_shift.shift_start, 1, 0) DAY
               )
            WHERE alert.recipient_user_id = :alert_nurse_id
              AND alert.recipient_role = 'nurse'
              AND alert.channel = 'admin'
              AND alert.incident_id IS NOT NULL
            GROUP BY alert.incident_id";
    }

    public static function data(int $nurseId): array
    {
        $connection = self::connection();
        NurseNotifiedCasesModel::bedAvailability($nurseId);
        $notifiedIncidents = self::notifiedIncidentsSql();

        $summaryQuery = $connection->prepare(
            "SELECT
                (
                    SELECT COUNT(*)
                    FROM nurse_patient_handoffs recorded_handoff
                    WHERE recorded_handoff.nurse_id = nurse.id
                ) AS total_handoffs,
                AVG(
                    CASE
                        WHEN i.nurse_prep_completed = 1
                         AND i.nurse_bed_id IS NOT NULL
                         AND i.nurse_prep_completed_at >= notified.notified_at
                         AND EXISTS (
                            SELECT 1
                            FROM nurse_patient_handoffs completed_handoff
                            WHERE completed_handoff.incident_id = i.incident_id
                              AND completed_handoff.nurse_id = nurse.id
                         )
                        THEN TIMESTAMPDIFF(SECOND, notified.notified_at, i.nurse_prep_completed_at)
                        ELSE NULL
                    END
                ) AS average_prep_seconds,
                (
                    SELECT COUNT(*)
                    FROM hospital_beds ready_bed
                    WHERE ready_bed.hospital_id = nurse.hospital_id
                      AND ready_bed.status = 'ready'
                ) AS beds_ready
            FROM user_type nurse
            LEFT JOIN ({$notifiedIncidents}) notified ON 1 = 1
            LEFT JOIN incidents i
                ON i.incident_id = notified.incident_id
               AND (
                    i.hospital_id = nurse.hospital_id
                    OR (i.hospital_id IS NULL AND i.hospital_name = nurse.hospital_name)
               )
            WHERE nurse.id = :nurse_id
              AND nurse.role = 'nurse'
            GROUP BY nurse.id, nurse.hospital_id"
        );
        $summaryQuery->execute(['alert_nurse_id' => $nurseId, 'nurse_id' => $nurseId]);
        $summary = $summaryQuery->fetch();
        if (!$summary) {
            throw new RuntimeException('Nurse account not found.');
        }

        $handoffQuery = $connection->prepare(
            "SELECT recorded_handoff.id, recorded_handoff.incident_id,
                recorded_handoff.from_responder, recorded_handoff.pulse,
                recorded_handoff.spo2, recorded_handoff.blood_pressure,
                recorded_handoff.notes, recorded_handoff.created_at,
                i.incident_type, i.patient_name, i.patient_gender, i.patient_age,
                bed.bed_type, bed.bed_number
             FROM nurse_patient_handoffs recorded_handoff
             INNER JOIN incidents i ON i.incident_id = recorded_handoff.incident_id
             INNER JOIN user_type nurse
                ON nurse.id = recorded_handoff.nurse_id AND nurse.role = 'nurse'
             LEFT JOIN hospital_beds bed
                ON bed.id = recorded_handoff.bed_id AND bed.hospital_id = nurse.hospital_id
             WHERE recorded_handoff.nurse_id = :nurse_id
               AND (i.hospital_id = nurse.hospital_id
                    OR (i.hospital_id IS NULL AND i.hospital_name = nurse.hospital_name))
             ORDER BY recorded_handoff.created_at DESC, recorded_handoff.id DESC"
        );
        $handoffQuery->execute(['nurse_id' => $nurseId]);
        $rows = [];
        foreach ($handoffQuery->fetchAll() as $row) {
            $patientName = trim((string) ($row['patient_name'] ?? ''));
            if ($patientName === '') {
                $patientName = 'Patient';
                if ($row['patient_gender'] && $row['patient_gender'] !== 'unknown') {
                    $patientName .= ' · ' . ucfirst((string) $row['patient_gender']);
                }
                if ($row['patient_age'] !== null) {
                    $patientName .= ', ' . (int) $row['patient_age'];
                }
            }

            $bedLabel = 'Not assigned';
            if ($row['bed_type'] !== null && $row['bed_number'] !== null) {
                $bedLabel = ($row['bed_type'] === 'er' ? 'ER Bed ' : 'Pediatric Bed ') . (int) $row['bed_number'];
            }

            $rows[] = [
                'id' => (int) $row['id'],
                'incidentId' => (int) $row['incident_id'],
                'time' => self::formatTime($row['created_at']),
                'patient' => $patientName,
                'case' => (string) $row['incident_type'],
                'bed' => $bedLabel,
                'prep' => '—',
                'handedOff' => true,
                'fromResponder' => (string) ($row['from_responder'] ?? 'Responder'),
                'pulse' => (string) ($row['pulse'] ?? '—'),
                'spo2' => (string) ($row['spo2'] ?? '—'),
                'bloodPressure' => (string) ($row['blood_pressure'] ?? '—'),
                'notes' => (string) ($row['notes'] ?? ''),
            ];
        }

        return [
            'hospitalName' => NurseNotifiedCasesModel::profile($nurseId)['hospital_name'],
            'rows' => $rows,
            'summary' => [
                'totalHandoffs' => (int) $summary['total_handoffs'],
                'averagePrepTime' => $summary['average_prep_seconds'] === null
                    ? '—'
                    : number_format(((float) $summary['average_prep_seconds']) / 60, 1) . ' min',
                'bedsReady' => (int) $summary['beds_ready'],
            ],
        ];
    }

    private static function formatTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '—';
        }

        return (new DateTimeImmutable($time))->format('g:i A');
    }
}
