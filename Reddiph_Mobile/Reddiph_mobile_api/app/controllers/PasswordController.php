<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/PasswordModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';

/**
 * JSON controller for the forgot-password flow, shared by every mobile role.
 */
final class PasswordController extends ApiController
{
    private const MESSAGE_SENT = 'If that email is registered, a temporary password has been sent.';

    public static function forgot(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();

        $email = trim((string) ($data['email'] ?? ''));
        $role = (string) ($data['role'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, UserModel::ROLES, true)) {
            self::respond(422, ['success' => false, 'message' => 'Enter a valid registered email address.']);
        }

        try {
            if (!PasswordModel::requestReset($email, $role)) {
                self::respond(503, ['success' => false, 'message' => 'Unable to send the email right now. Please try again later.']);
            }
            self::respond(200, ['success' => true, 'message' => self::MESSAGE_SENT]);
        } catch (Throwable $e) {
            self::fail('forgot_password', $e, 'Unable to process the password reset request.');
        }
    }

    /** Sets a new password for the signed-in user, e.g. to replace a temporary one. */
    public static function change(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();

        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        $password = (string) ($data['password'] ?? '');
        $errors = PasswordModel::validateNewPassword($password, (string) ($data['confirm_password'] ?? ''));
        if ($errors !== []) {
            self::respond(422, ['success' => false, 'message' => $errors[0]]);
        }

        try {
            PasswordModel::changePassword($userId, $password);
            self::respond(200, ['success' => true, 'message' => 'Password updated.']);
        } catch (Throwable $e) {
            self::fail('change_password', $e, 'Unable to update your password.');
        }
    }
}
