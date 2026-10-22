<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/AccountValidator.php';
require_once __DIR__ . '/../services/MailService.php';

/**
 * Forgot-password flow on user_type, mirroring the admin: a temporary password is
 * emailed, then the user must replace it before using the app.
 */
final class PasswordModel
{
    public const MIN_LENGTH = 8;
    // bcrypt ignores bytes beyond 72.
    public const MAX_LENGTH = 72;
    private const TEMP_PASSWORD_LENGTH = 12;
    private const TEMP_PASSWORD_LIFETIME = 86400;
    private const REQUEST_COOLDOWN = 60;
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';

    /**
     * Emails a temporary password to a registered account. Returns false only when the
     * email could not be sent; an unknown email or a too-recent request still returns
     * true so the response never reveals which accounts exist.
     */
    public static function requestReset(string $email, string $role): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, full_name, work_email, reset_requested_at FROM user_type
             WHERE work_email = ? AND role = ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email)), $role]);
        $user = $stmt->fetch();

        if (!$user || self::requestedRecently($user['reset_requested_at'])) {
            return true;
        }

        $temporaryPassword = self::generateTemporaryPassword();

        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE user_type
             SET password_hash = ?, must_change_password = 1,
                 temporary_password_expires_at = ?, reset_requested_at = NOW()
             WHERE id = ?'
        )->execute([
            password_hash($temporaryPassword, PASSWORD_DEFAULT),
            date('Y-m-d H:i:s', time() + self::TEMP_PASSWORD_LIFETIME),
            $user['id'],
        ]);

        // If the email fails, undo the change so the old password keeps working.
        if (!MailService::sendTemporaryPassword($user['work_email'], $user['full_name'], $temporaryPassword)) {
            $pdo->rollBack();
            return false;
        }
        $pdo->commit();

        return true;
    }

    /**
     * Replaces the password and clears the temporary-password flags.
     */
    public static function changePassword(int $userId, string $password): void
    {
        Database::connection()->prepare(
            'UPDATE user_type
             SET password_hash = ?, must_change_password = 0,
                 temporary_password_expires_at = NULL, password_changed_at = NOW()
             WHERE id = ?'
        )->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    }

    /**
     * @return string[] error messages for a new password and its confirmation
     */
    public static function validateNewPassword(string $password, string $confirmation): array
    {
        $errors = AccountValidator::passwordErrors($password);
        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'Passwords do not match.';
        }

        return $errors;
    }

    private static function requestedRecently(?string $requestedAt): bool
    {
        return $requestedAt !== null && strtotime($requestedAt) > time() - self::REQUEST_COOLDOWN;
    }

    private static function generateTemporaryPassword(): string
    {
        $password = [
            'A',
            'a',
            '!',
        ];
        $max = strlen(self::ALPHABET) - 1;
        while (count($password) < self::TEMP_PASSWORD_LENGTH) {
            $password[] = self::ALPHABET[random_int(0, $max)];
        }

        for ($i = count($password) - 1; $i > 0; $i--) {
            $index = random_int(0, $i);
            [$password[$i], $password[$index]] = [$password[$index], $password[$i]];
        }

        return implode('', $password);
    }
}
