<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/AuditLogService.php';

/** Thrown when the password is right but the emailed temporary password is past its 24 hours. */
final class TemporaryPasswordExpiredException extends RuntimeException
{
}

/** Thrown after valid credentials when the user's temporary suspension is still active. */
final class UserTemporarilyBannedException extends RuntimeException
{
}

final class UserPendingApprovalException extends RuntimeException
{
}

/**
 * Login and profile lookups on user_type, shared by every mobile role.
 */
final class UserModel
{
    public const ROLES = ['resident', 'responder', 'doctor', 'nurse', 'volunteer', 'barangay_admin'];

    /**
     * Verifies the email and password for the given role and returns the public profile,
     * or null when they do not match. The same null covers an unknown email and a wrong
     * password so the response never reveals which accounts exist.
     */
    public static function authenticate(string $email, string $password, string $role): ?array
    {
        if (!in_array($role, self::ROLES, true)) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT u.id, u.role, u.full_name, u.work_email, u.phone_number, u.age, u.gender,
                    u.hospital_id, u.barangay_id, u.hospital_name, b.name AS barangay_name,
                    u.password_hash,
                    u.must_change_password, u.temporary_password_expires_at, u.banned_until,
                    u.login_disabled_reason,
                    u.approval_status,
                    p.vehicle_type, p.is_available,
                    (SELECT AVG(r.rating)
                     FROM volunteer_assistance_reviews r
                     JOIN volunteer_assistances a ON a.id = r.assistance_id
                     WHERE a.volunteer_user_id = u.id) AS volunteer_rating,
                    (SELECT COUNT(*)
                     FROM volunteer_assistances a
                     WHERE a.volunteer_user_id = u.id AND a.status = 'completed')
                        AS volunteer_assisted_incidents
             FROM user_type u
             LEFT JOIN volunteer_profiles p ON p.user_id = u.id
             LEFT JOIN barangays b ON b.id = u.barangay_id
             WHERE u.work_email = ? AND u.role = ? LIMIT 1"
        );
        $stmt->execute([strtolower(trim($email)), $role]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        if (in_array($role, ['responder', 'volunteer'], true)
            && $user['approval_status'] === 'pending') {
            throw new UserPendingApprovalException();
        }
        if (in_array($role, ['responder', 'volunteer'], true)
            && $user['approval_status'] !== 'approved') {
            throw new UserPendingApprovalException('This account was not approved. Contact your Barangay Admin.');
        }
        if ($user['banned_until'] !== null && strtotime($user['banned_until']) > time()) {
            $reason = trim((string) ($user['login_disabled_reason'] ?? ''));
            throw new UserTemporarilyBannedException(
                $reason !== ''
                    ? 'Your login has been disabled. Reason: ' . $reason
                    : 'Your account is temporarily suspended until ' . $user['banned_until']
                        . '. Contact your Barangay Admin for help.'
            );
        }

        $mustChange = (int) $user['must_change_password'] === 1;
        $expiresAt = $user['temporary_password_expires_at'];
        if ($mustChange && ($expiresAt === null || strtotime($expiresAt) < time())) {
            throw new TemporaryPasswordExpiredException();
        }

        $pdo->prepare('UPDATE user_type SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        try {
            self::touchStaffPresence((int) $user['id'], $role);
        } catch (Throwable $presenceError) {
            error_log('[UserModel] Staff presence update failed: ' . $presenceError->getMessage());
        }
        AuditLogService::record($pdo, (int) $user['id'], 'auth.login_success', 'user', (string) $user['id']);

        return [
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'email' => $user['work_email'],
            'phone_number' => $user['phone_number'],
            'age' => $user['age'] !== null ? (int) $user['age'] : null,
            'gender' => $user['gender'],
            'hospital_id' => $user['hospital_id'] !== null ? (int) $user['hospital_id'] : null,
            'barangay_id' => $user['barangay_id'] !== null ? (int) $user['barangay_id'] : null,
            'hospital_name' => $user['hospital_name'],
            'barangay_name' => $user['barangay_name'],
            'vehicle_type' => $user['vehicle_type'],
            'is_available' => $user['is_available'] !== null
                ? (bool) $user['is_available']
                : null,
            'volunteer_rating' => $user['volunteer_rating'] !== null
                ? round((float) $user['volunteer_rating'], 1)
                : null,
            'volunteer_assisted_incidents' => (int) ($user['volunteer_assisted_incidents'] ?? 0),
            'must_change_password' => $mustChange,
        ];
    }

    public static function touchStaffPresence(int $userId, ?string $role = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $pdo = Database::connection();
        if ($role === null) {
            $statement = $pdo->prepare('SELECT role FROM user_type WHERE id = ? LIMIT 1');
            $statement->execute([$userId]);
            $role = $statement->fetchColumn() ?: null;
        }
        if (!in_array($role, ['doctor', 'nurse'], true)) {
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO staff_presence (user_id, last_seen_at)
             VALUES (?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE last_seen_at = UTC_TIMESTAMP()'
        );
        $statement->execute([$userId]);
    }

    /**
     * Returns the name, age, gender and hospital used to fill an incident report, or null if unknown.
     */
    public static function findReporter(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            '            SELECT full_name, age, gender, hospital_id, barangay_id, hospital_name,
                   role, approval_status, banned_until
             FROM user_type WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
