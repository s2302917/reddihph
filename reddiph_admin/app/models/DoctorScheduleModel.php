<?php

require_once __DIR__ . '/../../config/config.php';

class DoctorScheduleModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    public static function forMonth(int $doctorId, string $month): array
    {
        $staffId = self::findStaffId($doctorId);
        if ($staffId === null) {
            return [];
        }

        $statement = self::connection()->prepare(
            "SELECT id, shift_date AS date, shift_type AS type,
                shift_start AS start, shift_end AS end
            FROM staff_shifts
            WHERE staff_id = :staff_id
              AND shift_date >= :month_start
              AND shift_date < :month_end
            ORDER BY shift_date, shift_start, id"
        );
        $monthStart = new DateTimeImmutable($month . '-01');
        $statement->execute([
            'staff_id' => $staffId,
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthStart->modify('+1 month')->format('Y-m-d'),
        ]);

        return array_map([self::class, 'normalize'], $statement->fetchAll());
    }

    public static function tomorrow(int $doctorId): array
    {
        $staffId = self::findStaffId($doctorId);
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

    public static function create(int $doctorId, array $data): int
    {
        $connection = self::connection();
        $connection->beginTransaction();
        try {
            $staffId = self::ensureStaffId($connection, $doctorId);
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

    public static function update(int $doctorId, int $scheduleId, array $data): bool
    {
        $staffId = self::findStaffId($doctorId);
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

    public static function delete(int $doctorId, int $scheduleId): bool
    {
        $staffId = self::findStaffId($doctorId);
        if ($staffId === null) {
            return false;
        }

        $statement = self::connection()->prepare(
            'DELETE FROM staff_shifts WHERE id = :schedule_id AND staff_id = :staff_id'
        );
        $statement->execute(['schedule_id' => $scheduleId, 'staff_id' => $staffId]);

        return $statement->rowCount() === 1;
    }

    private static function findStaffId(int $doctorId): ?int
    {
        $statement = self::connection()->prepare(
            "SELECT staff.id
            FROM staff
            INNER JOIN user_type doctor ON doctor.id = staff.user_id AND doctor.role = 'doctor'
            WHERE doctor.id = :doctor_id
              AND (staff.hospital_id = doctor.hospital_id OR staff.hospital_id IS NULL)
            ORDER BY (staff.hospital_id = doctor.hospital_id) DESC, staff.id DESC
            LIMIT 1"
        );
        $statement->execute(['doctor_id' => $doctorId]);
        $staffId = $statement->fetchColumn();

        return $staffId === false ? null : (int) $staffId;
    }

    private static function ensureStaffId(PDO $connection, int $doctorId): int
    {
        $doctorStatement = $connection->prepare(
            "SELECT id, hospital_id, full_name, profile_image
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
            return (int) $staffId;
        }

        $insert = $connection->prepare(
            "INSERT INTO staff (hospital_id, user_id, profile_image, full_name, department)
            VALUES (:hospital_id, :user_id, :profile_image, :full_name, 'Doctor')"
        );
        $insert->execute([
            'hospital_id' => $doctor['hospital_id'],
            'user_id' => $doctorId,
            'profile_image' => $doctor['profile_image'],
            'full_name' => $doctor['full_name'],
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
