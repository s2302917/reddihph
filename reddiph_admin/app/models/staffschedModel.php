<?php

class StaffschedModel
{
    private ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (Throwable $e) {
            error_log('[StaffschedModel] Database connection failed: ' . $e->getMessage());
        }
    }

    public function getSchedules(?int $hospitalId): array
    {
        if (!$this->db) {
            throw new RuntimeException('Staff schedule database connection is unavailable.');
        }
        if (!$hospitalId) {
            return [];
        }

        try {
            $timezone = new DateTimeZone('Asia/Manila');
            $now = new DateTimeImmutable('now', $timezone);
            $statement = $this->db->prepare("SELECT u.id, u.full_name, u.role, u.last_login_at,
                CASE u.role WHEN 'doctor' THEN 'Doctor' WHEN 'nurse' THEN 'Nurse' ELSE '' END AS department,
                s.id AS staff_id, sh.id AS shift_id, sh.shift_date,
                sh.shift_type, sh.shift_start, sh.shift_end, sh.shift_status
                FROM user_type u
                LEFT JOIN staff s ON s.id = (
                    SELECT matching_staff.id
                    FROM staff matching_staff
                    WHERE matching_staff.user_id = u.id
                      AND (matching_staff.hospital_id = u.hospital_id OR matching_staff.hospital_id IS NULL)
                    ORDER BY (matching_staff.hospital_id = u.hospital_id) DESC, matching_staff.id DESC
                    LIMIT 1
                )
                LEFT JOIN staff_shifts sh
                    ON sh.staff_id = s.id
                   AND sh.shift_date IN (:today, :yesterday)
                WHERE u.hospital_id = :hospital_id
                AND u.role IN ('doctor', 'nurse')
                ORDER BY u.full_name, sh.shift_date DESC, sh.shift_start");
            $statement->execute([
                'today' => $now->format('Y-m-d'),
                'yesterday' => $now->modify('-1 day')->format('Y-m-d'),
                'hospital_id' => $hospitalId,
            ]);

            return $statement->fetchAll();
        } catch (Throwable $e) {
            error_log('[StaffschedModel] Schedule query failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getRecentStaff(?int $hospitalId): array
    {
        if (!$this->db || !$hospitalId) {
            return [];
        }

        try {
            $statement = $this->db->prepare("SELECT id, full_name, role, created_at
                FROM user_type
                WHERE hospital_id = :hospital_id
                  AND role IN ('doctor', 'nurse')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC, id DESC
                LIMIT 10");
            $statement->execute(['hospital_id' => $hospitalId]);
            return $statement->fetchAll();
        } catch (Throwable $e) {
            error_log('[StaffschedModel] Recent staff query failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getUserProfile(int $userId): ?array
    {
        if (!$this->db || $userId <= 0) {
            return null;
        }

        try {
            $statement = $this->db->prepare('SELECT full_name, role FROM user_type WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $userId]);
            return $statement->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('[StaffschedModel] User profile query failed: ' . $e->getMessage());
            return null;
        }
    }
}