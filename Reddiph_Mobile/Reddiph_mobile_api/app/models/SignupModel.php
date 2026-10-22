<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/AccountValidator.php';
require_once __DIR__ . '/VolunteerModel.php';

/**
 * Signup validation and storage in user_type for every mobile role.
 */
final class SignupModel
{
    public const ROLES = ['resident', 'responder', 'doctor', 'nurse', 'volunteer', 'barangay_admin'];
    private const ADMIN_SIGNUP_WINDOW_MINUTES = 15;
    private const ADMIN_SIGNUP_MAX_ATTEMPTS = 8;

    public static function adminSignupCodeMatches(mixed $submitted, ?string $configured): bool
    {
        return $configured !== null
            && strlen($configured) >= 32
            && is_string($submitted)
            && hash_equals($configured, $submitted);
    }

    public static function adminSignupLocked(string $ipHash): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT locked_until FROM barangay_admin_signup_attempts WHERE ip_hash = ? LIMIT 1'
        );
        $stmt->execute([$ipHash]);
        $lockedUntil = $stmt->fetchColumn();

        return is_string($lockedUntil) && strtotime($lockedUntil) > time();
    }

    public static function recordAdminSignupFailure(string $ipHash): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT IGNORE INTO barangay_admin_signup_attempts
                    (ip_hash, window_started_at, attempt_count)
                 VALUES (?, NOW(), 0)'
            )->execute([$ipHash]);
            $stmt = $pdo->prepare(
                'SELECT window_started_at, attempt_count
                 FROM barangay_admin_signup_attempts WHERE ip_hash = ? FOR UPDATE'
            );
            $stmt->execute([$ipHash]);
            $attempt = $stmt->fetch();
            $windowExpired = strtotime((string) $attempt['window_started_at'])
                < time() - self::ADMIN_SIGNUP_WINDOW_MINUTES * 60;
            $count = $windowExpired ? 1 : (int) $attempt['attempt_count'] + 1;
            $lockedUntil = $count >= self::ADMIN_SIGNUP_MAX_ATTEMPTS
                ? date('Y-m-d H:i:s', time() + self::ADMIN_SIGNUP_WINDOW_MINUTES * 60)
                : null;
            $pdo->prepare(
                'UPDATE barangay_admin_signup_attempts
                 SET window_started_at = ?, attempt_count = ?, locked_until = ?
                 WHERE ip_hash = ?'
            )->execute([
                $windowExpired ? date('Y-m-d H:i:s') : $attempt['window_started_at'],
                $count,
                $lockedUntil,
                $ipHash,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function clearAdminSignupAttempts(string $ipHash): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM barangay_admin_signup_attempts WHERE ip_hash = ?'
        );
        $stmt->execute([$ipHash]);
    }

    /**
     * @param string[] $genders allowed gender values from UserTypeModel::genderOptions
     * @return string[] validation error messages
     */
    public static function validate(array $data, string $role, array $genders): array
    {
        if (!in_array($role, self::ROLES, true)) {
            return ['Invalid account type.'];
        }

        return array_merge(
            AccountValidator::profileErrors($data, $genders),
            AccountValidator::workplaceErrors($data, $role),
            self::volunteerErrors($data, $role)
        );
    }

    private static function volunteerErrors(array $data, string $role): array
    {
        if ($role !== 'volunteer') {
            return [];
        }

        if (isset($data['vehicle_type']) && !is_string($data['vehicle_type'])) {
            return ['Enter valid vehicle information.'];
        }

        return strlen(trim((string) ($data['vehicle_type'] ?? ''))) > 80
            ? ['Vehicle information must be 80 characters or fewer.']
            : [];
    }

    /**
     * Creates the account and returns the new user_type id. Only doctors have a license number.
     * The hospital and barangay come from the signup form (user_type.barangay_id, migration 004).
     */
    public static function create(array $data, string $role, string $hospitalName): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO user_type
                    (role, full_name, hospital_name, hospital_id, barangay_id, work_email, phone_number,
                     license_number, age, gender, password_hash, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $role,
                trim((string) $data['full_name']),
                $hospitalName,
                (int) $data['hospital_id'],
                (int) $data['barangay_id'],
                strtolower(trim((string) $data['work_email'])),
                trim((string) $data['phone_number']),
                $role === 'doctor' ? trim((string) $data['license_number']) : null,
                (int) $data['age'],
                (string) $data['gender'],
                password_hash((string) $data['password'], PASSWORD_DEFAULT),
                in_array($role, ['responder', 'volunteer'], true) ? 'pending' : 'approved',
            ]);
            $userId = (int) $pdo->lastInsertId();
            if ($role === 'volunteer') {
                VolunteerModel::createProfile(
                    $userId,
                    trim((string) ($data['vehicle_type'] ?? '')) ?: null
                );
            }
            $pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
