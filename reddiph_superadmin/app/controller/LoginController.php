<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\LoginModel;
use PDOException;
use RuntimeException;

class LoginController
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user'])) {
            DashboardController::redirect(DashboardController::url('/overview'));
        }
        DashboardController::renderView('LoginView.php', [
            'csrf'  => DashboardController::csrfToken(),
            'error' => $_SESSION['flash_error'] ?? null,
            'email' => $_SESSION['flash_email'] ?? '',
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_email']);
    }

    public function login(): void
    {
        $email = isset($_POST['email']) && is_string($_POST['email'])
            ? trim($_POST['email'])
            : '';
        $password = isset($_POST['password']) && is_string($_POST['password'])
            ? $_POST['password']
            : '';

        if (!DashboardController::csrfValid()) {
            $this->fail('Your session expired. Reload the page and try again.', $email);
            return;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->recordAuthAudit(null, 'auth.login_failed');
            $this->fail('Enter your email and password.', $email);
            return;
        }

        try {
            $user = LoginModel::findSuperAdminByEmail($email);
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->recordAuthAudit(null, 'auth.login_failed');
                $this->fail('Email or password is incorrect.', $email);
                return;
            }
            if ($user['banned_until'] !== null && strtotime($user['banned_until']) > time()) {
                $this->recordAuthAudit((int) $user['id'], 'auth.login_failed');
                $this->fail(
                    'Your account is suspended until ' . $user['banned_until'] . '. Contact an administrator for help.',
                    $email
                );
                return;
            }
            if (
                (int) $user['must_change_password'] === 1 &&
                (
                    empty($user['temporary_password_expires_at']) ||
                    strtotime($user['temporary_password_expires_at']) < time()
                )
            ) {
                $this->recordAuthAudit((int) $user['id'], 'auth.login_failed');
                $this->fail('Your temporary password has expired. Request a new one.', $email);
                return;
            }
        } catch (RuntimeException $exception) {
            error_log('Super Admin sign-in database failure: ' . $exception->getMessage());
            $this->fail('Sign-in is temporarily unavailable. Check the Super Admin database configuration and schema, then try again.', $email);
            return;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            try {
                LoginModel::updatePasswordHash(
                    (int) $user['id'],
                    password_hash($password, PASSWORD_DEFAULT)
                );
            } catch (PDOException $exception) {
                error_log('Super Admin password hash could not be upgraded: ' . $exception->getMessage());
            }
        }

        try {
            LoginModel::recordSuperAdminLogin((int) $user['id']);
            $this->recordAuthAudit((int) $user['id'], 'auth.login_success');
        } catch (PDOException $exception) {
            error_log('Super Admin last-login timestamp could not be recorded: ' . $exception->getMessage());
            DashboardController::setFlash(
                'error',
                'You are signed in, but your last-login time could not be recorded. Ask the database administrator to check UPDATE permission on user_type.'
            );
        }

        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']];
        $_SESSION['last_activity'] = time();
        DashboardController::redirect(DashboardController::url('/overview'));
    }

    public function logout(): void
    {
        if (!DashboardController::csrfValid()) {
            http_response_code(403);
            exit('Invalid request token.');
        }

        $actorId = filter_var($_SESSION['user']['id'] ?? null, FILTER_VALIDATE_INT);
        if (is_int($actorId) && $actorId > 0) {
            $this->recordAuthAudit($actorId, 'auth.logout');
        }
        DashboardController::destroySession();
        DashboardController::redirect(DashboardController::url('/login'));
    }

    private function fail(string $message, string $email): void
    {
        $_SESSION['flash_error'] = $message;
        $_SESSION['flash_email'] = $email;
        DashboardController::redirect(DashboardController::url('/login'));
    }

    private function recordAuthAudit(?int $actorId, string $actionCode): void
    {
        try {
            \App\Models\AuditLogsModel::record(
                $actorId && $actorId > 0 ? $actorId : null,
                $actionCode,
                'user',
                $actorId && $actorId > 0 ? (string) $actorId : null
            );
        } catch (\Throwable $exception) {
            error_log('Super Admin authentication audit event could not be recorded: ' . $exception->getMessage());
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    $controller = new LoginController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->login();
    } else {
        $controller->showLogin();
    }
}
