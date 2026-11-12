<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';
require_once __DIR__ . '/../services/AuditLogService.php';

/**
 * JSON controller for email login, used by every mobile role.
 */
final class AuthController extends ApiController
{
    public static function login(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = (string) ($data['role'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            self::respond(422, ['success' => false, 'message' => 'Enter your email and password.']);
        }

        try {
            $user = UserModel::authenticate($email, $password, $role);
            if ($user === null) {
                self::recordLoginFailure($role);
                self::respond(401, ['success' => false, 'message' => 'Invalid email or password.']);
            }
            self::respond(200, ['success' => true, 'user' => $user, 'token' => TokenService::issue($user['id'])]);
        } catch (TemporaryPasswordExpiredException) {
            self::recordLoginFailure($role);
            self::respond(401, ['success' => false, 'message' => 'Your temporary password has expired. Request a new one.']);
        } catch (UserTemporarilyBannedException $e) {
            self::recordLoginFailure($role);
            self::respond(403, ['success' => false, 'message' => $e->getMessage()]);
        } catch (UserPendingApprovalException $e) {
            self::recordLoginFailure($role);
            self::respond(403, [
                'success' => false,
                'message' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Your account is awaiting Barangay Admin approval.',
            ]);
        } catch (Throwable $e) {
            self::fail('login', $e, 'Unable to sign in.');
        }
    }

    private static function recordLoginFailure(string $role): void
    {
        try {
            AuditLogService::record(
                Database::connection(),
                null,
                'auth.login_failed',
                'user',
                null,
                in_array($role, UserModel::ROLES, true) ? ['role' => $role] : []
            );
        } catch (Throwable $e) {
            self::fail('login audit', $e, 'Unable to sign in.');
        }
    }

    public static function checkSession(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $token = $data['token'] ?? null;
        try {
            $userId = TokenService::verify($token);
        } catch (Throwable $e) {
            self::fail('session check', $e, 'Unable to verify your session.');
        }
        if ($userId === null) {
            try {
                $disabledMessage = TokenService::disabledMessage($token);
            } catch (Throwable $e) {
                self::fail('session status check', $e, 'Unable to verify your session.');
            }
            self::respond(401, [
                'success' => false,
                'message' => $disabledMessage
                    ?? 'Your session is no longer active. Please sign in again.',
            ]);
        }
        try {
            UserModel::touchStaffPresence($userId);
        } catch (Throwable $e) {
            error_log('[AuthController] Mobile staff presence update failed: ' . $e->getMessage());
        }
        self::respond(200, ['success' => true]);
    }
}
