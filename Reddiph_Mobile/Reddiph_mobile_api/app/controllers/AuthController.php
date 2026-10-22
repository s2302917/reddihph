<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';

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
                self::respond(401, ['success' => false, 'message' => 'Invalid email or password.']);
            }
            self::respond(200, ['success' => true, 'user' => $user, 'token' => TokenService::issue($user['id'])]);
        } catch (TemporaryPasswordExpiredException) {
            self::respond(401, ['success' => false, 'message' => 'Your temporary password has expired. Request a new one.']);
        } catch (UserTemporarilyBannedException $e) {
            self::respond(403, ['success' => false, 'message' => $e->getMessage()]);
        } catch (UserPendingApprovalException $e) {
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

    public static function checkSession(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        try {
            $userId = TokenService::verify($data['token'] ?? null);
        } catch (Throwable $e) {
            self::fail('session check', $e, 'Unable to verify your session.');
        }
        if ($userId === null) {
            self::respond(401, [
                'success' => false,
                'message' => 'Your session is no longer active. Please sign in again.',
            ]);
        }
        self::respond(200, ['success' => true]);
    }
}
