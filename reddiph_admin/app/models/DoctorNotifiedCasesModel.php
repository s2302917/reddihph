<?php

require_once __DIR__ . '/../../config/config.php';

class DoctorNotifiedCasesModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    public static function profile(int $doctorId): ?array
    {
        $statement = self::connection()->prepare(
            "SELECT
                u.id,
                u.full_name,
                u.role,
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
            WHERE u.id = :doctor_id AND u.role = 'doctor'
            LIMIT 1"
        );
        $statement->execute(['doctor_id' => $doctorId]);
        $profile = $statement->fetch();

        return $profile ?: null;
    }

    public static function sidebarProfile(int $doctorId): ?array
    {
        $profile = self::profile($doctorId);
        if (!$profile) {
            return null;
        }

        $parts = preg_split('/\s+/', trim((string) $profile['full_name']), -1, PREG_SPLIT_NO_EMPTY);
        $profile['initials'] = '';
        foreach (array_slice($parts ?: [], 0, 2) as $part) {
            $profile['initials'] .= strtoupper(substr($part, 0, 1));
        }
        $profile['coordinator_role'] = ucfirst(str_replace('_', ' ', (string) $profile['role']));
        $profile['profile_image_url'] = userProfileImageUrl($profile['profile_image'] ?? null);

        return $profile;
    }

    public static function cases(int $doctorId): array
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
                i.resolved_at,
                i.notes,
                COALESCE(h.name, i.hospital_name) AS registered_hospital
            FROM incidents i
            INNER JOIN user_type doctor
                ON doctor.id = :doctor_id
                AND doctor.role = 'doctor'
                AND doctor.hospital_id = i.hospital_id
            LEFT JOIN hospitals h ON h.id = doctor.hospital_id
            WHERE i.status IN ('critical', 'urgent', 'dispatched', 'stable')
              AND EXISTS (
                  SELECT 1
                  FROM incident_patients submitted_patient
                  WHERE submitted_patient.incident_id = i.incident_id
              )
              AND EXISTS (
                  SELECT 1
                  FROM staff_alerts alert
                  WHERE alert.incident_id = i.incident_id
                    AND alert.recipient_user_id = doctor.id
                    AND alert.recipient_role = 'doctor'
                    AND alert.channel = 'admin'
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
        $statement->execute(['doctor_id' => $doctorId]);

        return array_map(static function (array $row): array {
            $status = (string) $row['status'];
            $patientName = trim((string) ($row['patient_name'] ?? ''));
            if ($patientName !== '') {
                $patient = $patientName;
            } else {
                $patientGender = ucfirst((string) ($row['patient_gender'] ?? 'unknown'));
                $patient = $patientGender === 'Unknown' ? 'Patient details unavailable' : $patientGender;
                if ($row['patient_age'] !== null) {
                    $patient .= ', ' . (int) $row['patient_age'];
                }
            }

            return [
                'id' => (int) $row['incident_id'],
                'title' => (string) $row['incident_type'],
                'status' => $status,
                'statusLabel' => $status === 'dispatched' ? 'Urgent' : ucfirst($status),
                'location' => (string) $row['location'],
                'patient' => $patient,
                'eta' => $row['eta_minutes'] === null ? null : (int) $row['eta_minutes'],
                'responder' => (string) ($row['responder_name'] ?? ''),
                'hospital' => (string) $row['registered_hospital'],
                'reportedAt' => (string) $row['reported_at'],
                'notes' => (string) ($row['notes'] ?? ''),
            ];
        }, $statement->fetchAll());
    }

    public static function summary(int $doctorId): array
    {
        $statement = self::connection()->prepare(
            "SELECT
                COUNT(*) AS all_count,
                COALESCE(SUM(i.status = 'critical'), 0) AS critical_count,
                COALESCE(SUM(i.status IN ('urgent', 'dispatched')), 0) AS urgent_count,
                COALESCE(SUM(i.status = 'stable'), 0) AS stable_count
            FROM incidents i
            INNER JOIN user_type doctor
                ON doctor.id = :doctor_id
                AND doctor.role = 'doctor'
                AND doctor.hospital_id = i.hospital_id
            WHERE i.status IN ('critical', 'urgent', 'dispatched', 'stable')
              AND EXISTS (
                  SELECT 1
                  FROM incident_patients submitted_patient
                  WHERE submitted_patient.incident_id = i.incident_id
              )
              AND EXISTS (
                  SELECT 1
                  FROM staff_alerts alert
                  WHERE alert.incident_id = i.incident_id
                    AND alert.recipient_user_id = doctor.id
                    AND alert.recipient_role = 'doctor'
                    AND alert.channel = 'admin'
              )"
        );
        $statement->execute(['doctor_id' => $doctorId]);
        $row = $statement->fetch() ?: [];

        return [
            'all' => (int) ($row['all_count'] ?? 0),
            'critical' => (int) ($row['critical_count'] ?? 0),
            'urgent' => (int) ($row['urgent_count'] ?? 0),
            'stable' => (int) ($row['stable_count'] ?? 0),
        ];
    }

    public static function pendingAlerts(int $doctorId): array
    {
        $statement = self::connection()->prepare(
            "SELECT
                alert.id AS alert_id,
                i.incident_id,
                i.incident_type,
                i.status,
                i.location,
                i.patient_name,
                i.patient_gender,
                i.patient_age,
                i.eta_minutes,
                i.responder_name,
                i.reported_at,
                COALESCE(h.name, i.hospital_name) AS hospital_name
            FROM staff_alerts alert
            INNER JOIN incidents i ON i.incident_id = alert.incident_id
            INNER JOIN user_type doctor
                ON doctor.id = alert.recipient_user_id
                AND doctor.role = 'doctor'
                AND doctor.id = :doctor_id
                AND doctor.hospital_id = i.hospital_id
            LEFT JOIN hospitals h ON h.id = doctor.hospital_id
            WHERE alert.recipient_role = 'doctor'
              AND alert.channel = 'admin'
              AND alert.status = 'queued'
              AND EXISTS (
                  SELECT 1
                  FROM incident_patients submitted_patient
                  WHERE submitted_patient.incident_id = i.incident_id
              )
            ORDER BY alert.created_at ASC, alert.id ASC"
        );
        $statement->execute(['doctor_id' => $doctorId]);

        return array_map(static function (array $row): array {
            $status = (string) $row['status'];
            $patientName = trim((string) ($row['patient_name'] ?? ''));
            if ($patientName !== '') {
                $patient = $patientName;
            } else {
                $patientGender = ucfirst((string) ($row['patient_gender'] ?? 'unknown'));
                $patient = $patientGender === 'Unknown' ? 'Patient details unavailable' : $patientGender;
                if ($row['patient_age'] !== null) {
                    $patient .= ', ' . (int) $row['patient_age'];
                }
            }

            return [
                'alertId' => (int) $row['alert_id'],
                'id' => (int) $row['incident_id'],
                'title' => (string) $row['incident_type'],
                'status' => $status === 'dispatched' ? 'urgent' : $status,
                'statusLabel' => $status === 'dispatched' ? 'Urgent' : ucfirst($status),
                'location' => (string) $row['location'],
                'patient' => $patient,
                'eta' => $row['eta_minutes'] === null ? null : (int) $row['eta_minutes'],
                'responder' => (string) ($row['responder_name'] ?? ''),
                'hospital' => (string) $row['hospital_name'],
                'reportedAt' => (string) $row['reported_at'],
            ];
        }, $statement->fetchAll());
    }

    public static function acknowledgeAlert(int $doctorId, int $alertId): bool
    {
        $statement = self::connection()->prepare(
            "UPDATE staff_alerts
            SET status = 'read'
            WHERE id = :alert_id
              AND recipient_user_id = :doctor_id
              AND recipient_role = 'doctor'
              AND status = 'queued'"
        );
        $statement->execute([
            'alert_id' => $alertId,
            'doctor_id' => $doctorId,
        ]);

        return $statement->rowCount() === 1;
    }

    public static function setAvailability(int $doctorId, bool $available): void
    {
        $connection = self::connection();
        $connection->beginTransaction();

        try {
            $doctorStatement = $connection->prepare(
                "SELECT id, hospital_id, full_name, profile_image, duty_start, duty_end
                FROM user_type
                WHERE id = :doctor_id AND role = 'doctor'
                LIMIT 1
                FOR UPDATE"
            );
            $doctorStatement->execute(['doctor_id' => $doctorId]);
            $doctor = $doctorStatement->fetch();
            if (!$doctor) {
                throw new RuntimeException('Doctor account not found.');
            }

            $staffStatement = $connection->prepare(
                "SELECT id
                FROM staff
                WHERE user_id = :doctor_id
                  AND (hospital_id = :hospital_id OR hospital_id IS NULL)
                ORDER BY (hospital_id = :preferred_hospital_id) DESC, id DESC
                LIMIT 1
                FOR UPDATE"
            );
            $staffStatement->execute([
                'doctor_id' => $doctorId,
                'hospital_id' => $doctor['hospital_id'],
                'preferred_hospital_id' => $doctor['hospital_id'],
            ]);
            $staffId = $staffStatement->fetchColumn();

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
                    'hospital_id' => $doctor['hospital_id'],
                    'user_id' => $doctorId,
                    'profile_image' => $doctor['profile_image'],
                    'full_name' => $doctor['full_name'],
                    'department' => 'Doctor',
                    'is_available' => $available ? 1 : 0,
                ]);
                $staffId = $connection->lastInsertId();
            }

            $shiftStart = $doctor['duty_start'] !== null ? (string) $doctor['duty_start'] : null;
            $shiftEnd = $doctor['duty_end'] !== null ? (string) $doctor['duty_end'] : null;
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
}
