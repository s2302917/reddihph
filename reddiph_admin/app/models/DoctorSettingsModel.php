<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/PasswordPolicy.php';

class DoctorSettingsModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    public static function profile(int $doctorId): ?array
    {
        $statement = self::connection()->prepare(
            "SELECT id, full_name, role, work_email, phone_number, license_number,
                profile_image, password_changed_at
            FROM user_type
            WHERE id = :doctor_id AND role = 'doctor'
            LIMIT 1"
        );
        $statement->execute(['doctor_id' => $doctorId]);
        $profile = $statement->fetch();

        if (!$profile) {
            return null;
        }

        $profile['initials'] = '';
        $nameParts = preg_split('/\s+/', trim((string) $profile['full_name']), -1, PREG_SPLIT_NO_EMPTY);
        foreach (array_slice($nameParts ?: [], 0, 2) as $part) {
            $profile['initials'] .= strtoupper(substr($part, 0, 1));
        }
        $profile['profile_image_url'] = userProfileImageUrl($profile['profile_image'] ?? null);
        $profile['password_changed_label'] = !empty($profile['password_changed_at'])
            ? 'Last changed ' . (new DateTimeImmutable((string) $profile['password_changed_at']))->format('M j, Y')
            : 'Password change date unavailable';
        $profile['two_factor_label'] = 'Not enabled';

        return $profile;
    }

    public static function updateContact(int $doctorId, string $email, ?string $phoneNumber): void
    {
        $statement = self::connection()->prepare(
            "UPDATE user_type
            SET work_email = :email, phone_number = :phone_number
            WHERE id = :doctor_id AND role = 'doctor'"
        );
        $statement->execute([
            'email' => $email,
            'phone_number' => $phoneNumber,
            'doctor_id' => $doctorId,
        ]);

        if ($statement->rowCount() === 0) {
            $exists = self::connection()->prepare(
                "SELECT 1 FROM user_type WHERE id = :doctor_id AND role = 'doctor' LIMIT 1"
            );
            $exists->execute(['doctor_id' => $doctorId]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Doctor account not found.');
            }
        }
    }

    public static function changePassword(int $doctorId, string $currentPassword, string $newPassword): bool
    {
        if (PasswordPolicy::errors($newPassword) !== []) {
            return false;
        }
        $connection = self::connection();
        $query = $connection->prepare(
            "SELECT password_hash
            FROM user_type
            WHERE id = :doctor_id AND role = 'doctor'
            LIMIT 1"
        );
        $query->execute(['doctor_id' => $doctorId]);
        $passwordHash = $query->fetchColumn();

        if (!$passwordHash || !password_verify($currentPassword, (string) $passwordHash)) {
            return false;
        }

        $update = $connection->prepare(
            "UPDATE user_type
            SET password_hash = :password_hash,
                password_changed_at = NOW(),
                must_change_password = 0,
                temporary_password_expires_at = NULL
            WHERE id = :doctor_id AND role = 'doctor'"
        );
        $update->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'doctor_id' => $doctorId,
        ]);

        return $update->rowCount() === 1;
    }

    public static function updateProfileImage(int $doctorId, string $fileName): ?string
    {
        $connection = self::connection();
        $connection->beginTransaction();
        try {
            $query = $connection->prepare(
                "SELECT profile_image
                FROM user_type
                WHERE id = :doctor_id AND role = 'doctor'
                LIMIT 1
                FOR UPDATE"
            );
            $query->execute(['doctor_id' => $doctorId]);
            $previousImage = $query->fetchColumn();
            if ($previousImage === false) {
                throw new RuntimeException('Doctor account not found.');
            }

            $update = $connection->prepare(
                "UPDATE user_type SET profile_image = :image
                WHERE id = :doctor_id AND role = 'doctor'"
            );
            $update->execute(['image' => $fileName, 'doctor_id' => $doctorId]);
            $connection->commit();

            return $previousImage !== null ? (string) $previousImage : null;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
