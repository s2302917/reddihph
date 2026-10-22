<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/passwordResetMailer.php';
require_once __DIR__ . '/../services/PasswordPolicy.php';

class LoginModel
{
    /**
     * Validate login input.
     */
    public static function validateLogin(array $data): array
    {
        $errors = [];

        $workEmail = trim($data['work_email'] ?? '');
        $password = $data['password'] ?? '';

        if ($workEmail === '') {
            $errors[] = 'Work email is required.';
        } elseif (!filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid work email address.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }


    /**
     * Authenticate user.
     *
     * The role is checked so that:
     * hospital_admin login cannot be used by a doctor/nurse,
     * doctor login cannot be used by another role, etc.
     */
    public static function authenticate(
        string $workEmail,
        string $password,
        string $role
    ): array {

        $allowedRoles = [
            'hospital_admin',
            'doctor',
            'nurse'
        ];

        if (!in_array($role, $allowedRoles, true)) {
            return [
                'success' => false,
                'message' => 'Invalid account type.'
            ];
        }

        $workEmail = strtolower(trim($workEmail));

        try {

            $pdo = Database::connection();

            $sql = "
                SELECT
                    id,
                    role,
                    full_name,
                    hospital_name,
                    hospital_id,
                    work_email,
                    license_number,
                    profile_image,
                    password_hash,
                    must_change_password,
                    temporary_password_expires_at,
                    banned_until
                FROM user_type
                WHERE work_email = :work_email
                  AND role = :role
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':work_email' => $workEmail,
                ':role' => $role
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
             * Do not reveal whether the email exists.
             */
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.'
                ];
            }

            if ($user['banned_until'] !== null && strtotime($user['banned_until']) > time()) {
                return [
                    'success' => false,
                    'message' => 'Your account is suspended until ' . $user['banned_until'] . '. Contact an administrator for help.'
                ];
            }

            if (
                (int) $user['must_change_password'] === 1 &&
                (
                    empty($user['temporary_password_expires_at']) ||
                    strtotime($user['temporary_password_expires_at']) < time()
                )
            ) {
                return [
                    'success' => false,
                    'message' => 'Your temporary password has expired. Request a new one.'
                ];
            }

            $loginUpdate = $pdo->prepare('UPDATE user_type SET last_login_at = NOW() WHERE id = :id');
            $loginUpdate->execute([':id' => $user['id']]);

            /*
             * Upgrade password hash if PHP's recommended
             * hashing algorithm has changed.
             */
            if (
                password_needs_rehash(
                    $user['password_hash'],
                    PASSWORD_DEFAULT
                )
            ) {

                $newHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $update = $pdo->prepare("
                    UPDATE user_type
                    SET password_hash = :password_hash
                    WHERE id = :id
                ");

                $update->execute([
                    ':password_hash' => $newHash,
                    ':id' => $user['id']
                ]);
            }

            /*
             * Remove password hash before returning user data.
             */
            unset($user['password_hash']);

            return [
                'success' => true,
                'message' => 'Login successful.',
                'user' => $user
            ];

        } catch (Throwable $e) {

            error_log(
                'Login error: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Unable to process login. Please try again.'
            ];
        }
    }


    /**
     * Start authenticated session.
     */
    public static function createSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            startApplicationSession((string) ($user['role'] ?? ''));
        }

        /*
         * Prevent session fixation.
         */
        session_regenerate_id(true);

        $_SESSION['logged_in'] = true;

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['hospital_name'] = $user['hospital_name'];
        $_SESSION['hospital_id'] = $user['hospital_id'];
        $_SESSION['work_email'] = $user['work_email'];
        $_SESSION['license_number'] = $user['license_number'] ?? null;
        $_SESSION['profile_image'] = $user['profile_image'] ?? null;
        $_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0) === 1;
    }


    /**
     * Logout user.
     */
    public static function logout(?string $role = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            startApplicationSession($role);
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId > 0) {
            try {
                $pdo = Database::connection();
                $logoutUpdate = $pdo->prepare('UPDATE user_type SET last_login_at = NULL WHERE id = :id');
                $logoutUpdate->execute([':id' => $userId]);
            } catch (Throwable $e) {
                error_log('Logout presence update failed: ' . $e->getMessage());
            }
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }


    /**
     * Check whether user is logged in.
     */
    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            startApplicationSession();
        }

        return isset($_SESSION['logged_in'])
            && $_SESSION['logged_in'] === true;
    }


    public static function requestPasswordReset(string $email, string $role): array
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Please enter a valid registered email address.'
            ];
        }

        try {
            $pdo = Database::connection();

            $stmt = $pdo->prepare("
                SELECT id, full_name, work_email, reset_requested_at
                FROM user_type
                WHERE work_email = :work_email
                  AND role = :role
                LIMIT 1
            ");
            $stmt->execute([
                ':work_email' => $email,
                ':role' => $role
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $genericMessage = 'If that email is registered, a temporary password has been sent.';

            if (!$user) {
                return ['success' => true, 'message' => $genericMessage];
            }

            if (
                !empty($user['reset_requested_at']) &&
                strtotime($user['reset_requested_at']) > time() - 60
            ) {
                return ['success' => true, 'message' => $genericMessage];
            }

            $temporaryPassword = self::generateTemporaryPassword();
            $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);

            $pdo->beginTransaction();
            $update = $pdo->prepare("
                UPDATE user_type
                SET password_hash = :password_hash,
                    must_change_password = 1,
                    temporary_password_expires_at = :expires_at,
                    reset_requested_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                ':password_hash' => $passwordHash,
                ':expires_at' => $expiresAt,
                ':id' => $user['id']
            ]);

            if (!sendTemporaryPasswordEmail($user['work_email'], $user['full_name'], $temporaryPassword)) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Unable to send password reset instructions right now. Please try again later or contact support.'
                ];
            }

            $pdo->commit();

            return ['success' => true, 'message' => $genericMessage];
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Password reset error: ' . $exception->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to process the password reset request.'
            ];
        }
    }


    public static function changePassword(int $userId, string $password, string $confirmation): array
    {
        $errors = PasswordPolicy::errors($password);
        if ($password !== $confirmation) {
            $errors[] = 'Passwords do not match.';
        }
        if ($errors !== []) {
            return ['success' => false, 'message' => $errors[0]];
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                UPDATE user_type
                SET password_hash = :password_hash,
                    must_change_password = 0,
                    temporary_password_expires_at = NULL,
                    password_changed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => $userId
            ]);

            if (session_status() === PHP_SESSION_NONE) {
                startApplicationSession();
            }
            session_regenerate_id(true);
            $_SESSION['must_change_password'] = false;

            return ['success' => true, 'message' => 'Password updated.'];
        } catch (Throwable $exception) {
            error_log('Password change error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to update your password.'];
        }
    }


    private static function generateTemporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $password = ['A', 'a', '!'];
        $max = strlen($alphabet) - 1;

        while (count($password) < 12) {
            $password[] = $alphabet[random_int(0, $max)];
        }

        for ($index = count($password) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$password[$index], $password[$swap]] = [$password[$swap], $password[$index]];
        }

        return implode('', $password);
    }


    /**
     * Require a specific role.
     */
    public static function requireRole(string $role): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            startApplicationSession($role);
        }

        if (
            !isset($_SESSION['logged_in']) ||
            $_SESSION['logged_in'] !== true
        ) {
            header('Location: /login');
            exit;
        }

        if (
            !isset($_SESSION['role']) ||
            $_SESSION['role'] !== $role
        ) {
            http_response_code(403);
            exit('Access denied.');
        }
    }


    /**
     * Get currently logged-in user.
     */
    public static function currentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            startApplicationSession();
        }

        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'hospital_name' => $_SESSION['hospital_name'] ?? null,
            'hospital_id' => $_SESSION['hospital_id'] ?? null,
            'work_email' => $_SESSION['work_email'] ?? null,
            'license_number' => $_SESSION['license_number'] ?? null
        ];
    }
}