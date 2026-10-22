<?php

require_once __DIR__ . '/../../config/config.php';

class StaffIncomingAlertsModel
{
    public static function pending(int $userId, string $role): array
    {
        if (!in_array($role, ['doctor', 'nurse'], true)) {
            throw new InvalidArgumentException('Unsupported staff role.');
        }

        $statement = Database::connection()->prepare(
            "SELECT
                alert.id AS alert_id,
                i.incident_id,
                i.incident_type,
                i.status,
                i.location,
                i.patient_name,
                i.patient_gender,
                i.patient_age,
                i.hospital_name,
                i.reported_at,
                alert.message
            FROM staff_alerts alert
            INNER JOIN incidents i ON i.incident_id = alert.incident_id
            INNER JOIN user_type staff
                ON staff.id = alert.recipient_user_id
                AND staff.role = alert.recipient_role
            WHERE alert.recipient_user_id = :user_id
              AND alert.recipient_role = :role
              AND alert.channel = 'admin'
              AND alert.status = 'queued'
              AND staff.hospital_id = i.hospital_id
              AND EXISTS (
                  SELECT 1
                  FROM incident_patients submitted_patient
                  WHERE submitted_patient.incident_id = i.incident_id
              )
            ORDER BY alert.created_at ASC, alert.id ASC"
        );
        $statement->execute(['user_id' => $userId, 'role' => $role]);
        $rows = $statement->fetchAll();
        if ($rows === []) {
            return [];
        }

        $incidentIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['incident_id'],
            $rows
        )));
        $placeholders = implode(',', array_fill(0, count($incidentIds), '?'));
        $patients = Database::connection()->prepare(
            "SELECT patient.id, patient.incident_id, patient.patient_name, patient.patient_age,
                    patient.patient_gender, patient.heart_rate, patient.spo2,
                    patient.nurse_blood_pressure, patient.notes, patient.photo_filename
             FROM incident_patients patient
             WHERE patient.incident_id IN ({$placeholders})
             ORDER BY patient.incident_id, patient.id"
        );
        $patients->execute($incidentIds);
        $patientRows = $patients->fetchAll();
        $patientIds = array_map(
            static fn (array $patient): int => (int) $patient['id'],
            $patientRows
        );
        $conditionsByPatient = [];
        if ($patientIds !== []) {
            $patientPlaceholders = implode(',', array_fill(0, count($patientIds), '?'));
            $conditions = Database::connection()->prepare(
                "SELECT patient_id, condition_code
                 FROM incident_patient_conditions
                 WHERE patient_id IN ({$patientPlaceholders})
                 ORDER BY patient_id, condition_code"
            );
            $conditions->execute($patientIds);
            foreach ($conditions->fetchAll() as $condition) {
                $conditionsByPatient[(int) $condition['patient_id']][] = $condition['condition_code'];
            }
        }
        $patientDetails = [];
        foreach ($patientRows as $patient) {
            $patient['scene_conditions'] = $conditionsByPatient[(int) $patient['id']] ?? [];
            $patientDetails[(int) $patient['incident_id']][] = self::patientSummary($patient);
        }

        return array_map(static function (array $row) use ($patientDetails): array {
            $status = (string) $row['status'];
            $patientName = trim((string) ($row['patient_name'] ?? ''));
            if ($patientName === '') {
                $gender = ucfirst((string) ($row['patient_gender'] ?? 'unknown'));
                $patientName = $gender === 'Unknown' ? 'Patient details unavailable' : $gender;
                if ($row['patient_age'] !== null) {
                    $patientName .= ', ' . (int) $row['patient_age'];
                }
            }
            $message = trim((string) ($row['message'] ?? ''));
            $summaries = $patientDetails[(int) $row['incident_id']] ?? [];
            if ($summaries !== []) {
                $message .= "\n\nPatient details:\n" . implode("\n", $summaries);
            }

            return [
                'alertId' => (int) $row['alert_id'],
                'incidentId' => (int) $row['incident_id'],
                'incident' => (string) $row['incident_type'],
                'status' => $status === 'dispatched' ? 'Urgent' : ucfirst($status),
                'location' => (string) $row['location'],
                'patient' => $patientName,
                'hospital' => (string) $row['hospital_name'],
                'reportedAt' => (string) $row['reported_at'],
                'message' => $message,
            ];
        }, $rows);
    }

    private static function patientSummary(array $patient): string
    {
        $gender = ucfirst((string) $patient['patient_gender']);
        $summary = $patient['patient_name'] . ' (' . $patient['patient_age'] . ', ' . $gender . ')';
        $conditions = $patient['scene_conditions'] ?? [];
        if ($conditions !== []) {
            $labels = array_map(
                static fn (string $condition): string => match ($condition) {
                    'breathing_difficulty' => 'Breathing difficulty',
                    default => ucfirst($condition),
                },
                $conditions
            );
            $summary .= "\nCondition at scene: " . implode(', ', $labels);
        }

        $vitals = [];
        if ($patient['heart_rate'] !== null) {
            $vitals[] = 'Pulse ' . (int) $patient['heart_rate'] . ' bpm';
        }
        if ($patient['spo2'] !== null) {
            $vitals[] = 'SpO2 ' . (int) $patient['spo2'] . '%';
        }
        if ($patient['nurse_blood_pressure'] !== null) {
            $vitals[] = 'BP ' . $patient['nurse_blood_pressure'];
        }
        if ($vitals !== []) {
            $summary .= "\nVitals: " . implode(' · ', $vitals);
        }
        if (trim((string) ($patient['notes'] ?? '')) !== '') {
            $summary .= "\nNotes: " . trim((string) $patient['notes']);
        }
        if ($patient['photo_filename'] !== null) {
            $summary .= "\nPatient photo attached.";
        }

        return $summary;
    }

    public static function acknowledge(int $userId, string $role, int $alertId): bool
    {
        if (!in_array($role, ['doctor', 'nurse'], true)) {
            throw new InvalidArgumentException('Unsupported staff role.');
        }

        $statement = Database::connection()->prepare(
            "UPDATE staff_alerts
            SET status = 'read'
            WHERE id = :alert_id
              AND recipient_user_id = :user_id
              AND recipient_role = :role
              AND channel = 'admin'
              AND status = 'queued'"
        );
        $statement->execute([
            'alert_id' => $alertId,
            'user_id' => $userId,
            'role' => $role,
        ]);

        return $statement->rowCount() === 1;
    }
}
