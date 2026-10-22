<?php

require_once __DIR__ . '/../../config/config.php';

class NurseScheduleModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    public static function forMonth(int $nurseId, string $month): array
    {
        $staffId = self::findStaffId($nurseId);
        if ($staffId === null) {
            return [];
        }

        $monthStart = new DateTimeImmutable($month . '-01');
        $statement = self::connection()->prepare(
            "SELECT id, shift_date AS date, shift_type AS type,
                shift_start AS start, shift_end AS end
            FROM staff_shifts
            WHERE staff_id = :staff_id
              AND shift_date >= :month_start
              AND shift_date < :month_end
            ORDER BY shift_date, shift_start, id"
        );
        $statement->execute([
            'staff_id' => $staffId,
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthStart->modify('+1 month')->format('Y-m-d'),
        ]);

        return array_map([self::class, 'normalize'], $statement->fetchAll());
    }

    public static function tomorrow(int $nurseId): array
    {
        $staffId = self::findStaffId($nurseId);
        if ($staffId === null) {
            return [];
        }

        $tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        $statement = self::connection()->prepare(
            "SELECT id, shift_date AS date, shift_type AS type,
                shift_start AS start, shift_end AS end
            FROM staff_shifts
            WHERE staff_id = :staff_id AND shift_date = :shift_date
            ORDER BY shift_start, id"
        );
        $statement->execute(['staff_id' => $staffId, 'shift_date' => $tomorrow]);

        return array_map([self::class, 'normalize'], $statement->fetchAll());
    }

    public static function create(int $nurseId, array $data): int
    {
        $connection = self::connection();
        $connection->beginTransaction();
        try {
            $staffId = self::ensureStaffId($connection, $nurseId);
            $statement = $connection->prepare(
                "INSERT INTO staff_shifts
                    (staff_id, shift_date, shift_type, shift_start, shift_end)
                VALUES
                    (:staff_id, :shift_date, :shift_type, :shift_start, :shift_end)"
            );
            $statement->execute([
                'staff_id' => $staffId,
                'shift_date' => $data['date'],
                'shift_type' => $data['type'],
                'shift_start' => $data['start'] !== '' ? $data['start'] : null,
                'shift_end' => $data['end'] !== '' ? $data['end'] : null,
            ]);
            $id = (int) $connection->lastInsertId();
            $connection->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function update(int $nurseId, int $scheduleId, array $data): bool
    {
        $staffId = self::findStaffId($nurseId);
        if ($staffId === null) {
            return false;
        }

        $statement = self::connection()->prepare(
            "UPDATE staff_shifts
            SET shift_date = :shift_date,
                shift_type = :shift_type,
                shift_start = :shift_start,
                shift_end = :shift_end
            WHERE id = :schedule_id AND staff_id = :staff_id"
        );
        $statement->execute([
            'shift_date' => $data['date'],
            'shift_type' => $data['type'],
            'shift_start' => $data['start'] !== '' ? $data['start'] : null,
            'shift_end' => $data['end'] !== '' ? $data['end'] : null,
            'schedule_id' => $scheduleId,
            'staff_id' => $staffId,
        ]);

        return $statement->rowCount() > 0 || self::scheduleExists($staffId, $scheduleId);
    }

    public static function delete(int $nurseId, int $scheduleId): bool
    {
        $staffId = self::findStaffId($nurseId);
        if ($staffId === null) {
            return false;
        }

        $statement = self::connection()->prepare(
            'DELETE FROM staff_shifts WHERE id = :schedule_id AND staff_id = :staff_id'
        );
        $statement->execute(['schedule_id' => $scheduleId, 'staff_id' => $staffId]);

        return $statement->rowCount() === 1;
    }

    public static function label(array $shift): string
    {
        if (!empty($shift['start']) && !empty($shift['end'])) {
            return date('g:i A', strtotime($shift['start'])) . '–' . date('g:i A', strtotime($shift['end']));
        }

        return match ($shift['type'] ?? '') {
            'oncall' => 'On-call',
            'leave' => 'Leave',
            default => 'Schedule',
        };
    }

    private static function findStaffId(int $nurseId): ?int
    {
        $statement = self::connection()->prepare(
            "SELECT staff.id
            FROM staff
            INNER JOIN user_type nurse ON nurse.id = staff.user_id AND nurse.role = 'nurse'
            WHERE nurse.id = :nurse_id
              AND (staff.hospital_id = nurse.hospital_id OR staff.hospital_id IS NULL)
            ORDER BY (staff.hospital_id = nurse.hospital_id) DESC, staff.id DESC
            LIMIT 1"
        );
        $statement->execute(['nurse_id' => $nurseId]);
        $staffId = $statement->fetchColumn();

        return $staffId === false ? null : (int) $staffId;
    }

    private static function ensureStaffId(PDO $connection, int $nurseId): int
    {
        $nurseStatement = $connection->prepare(
            "SELECT id, hospital_id, full_name, profile_image
            FROM user_type
            WHERE id = :nurse_id AND role = 'nurse'
            LIMIT 1
            FOR UPDATE"
        );
        $nurseStatement->execute(['nurse_id' => $nurseId]);
        $nurse = $nurseStatement->fetch();
        if (!$nurse) {
            throw new RuntimeException('Nurse account not found.');
        }

        $staffStatement = $connection->prepare(
            "SELECT id
            FROM staff
            WHERE user_id = :nurse_id
              AND (hospital_id = :hospital_id OR hospital_id IS NULL)
            ORDER BY (hospital_id = :preferred_hospital_id) DESC, id DESC
            LIMIT 1
            FOR UPDATE"
        );
        $staffStatement->execute([
            'nurse_id' => $nurseId,
            'hospital_id' => $nurse['hospital_id'],
            'preferred_hospital_id' => $nurse['hospital_id'],
        ]);
        $staffId = $staffStatement->fetchColumn();
        if ($staffId !== false) {
            return (int) $staffId;
        }

        $insert = $connection->prepare(
            "INSERT INTO staff (hospital_id, user_id, profile_image, full_name, department)
            VALUES (:hospital_id, :user_id, :profile_image, :full_name, 'Nurse')"
        );
        $insert->execute([
            'hospital_id' => $nurse['hospital_id'],
            'user_id' => $nurseId,
            'profile_image' => $nurse['profile_image'],
            'full_name' => $nurse['full_name'],
        ]);

        return (int) $connection->lastInsertId();
    }

    private static function scheduleExists(int $staffId, int $scheduleId): bool
    {
        $statement = self::connection()->prepare(
            'SELECT 1 FROM staff_shifts WHERE id = :schedule_id AND staff_id = :staff_id LIMIT 1'
        );
        $statement->execute(['schedule_id' => $scheduleId, 'staff_id' => $staffId]);

        return (bool) $statement->fetchColumn();
    }

    private static function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'date' => (string) $row['date'],
            'type' => (string) $row['type'],
            'start' => $row['start'] !== null ? substr((string) $row['start'], 0, 5) : '',
            'end' => $row['end'] !== null ? substr((string) $row['end'], 0, 5) : '',
        ];
    }
}
