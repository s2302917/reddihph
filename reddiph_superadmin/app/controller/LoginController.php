<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\LoginModel;

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
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!DashboardController::csrfValid()) {
            $this->fail('Your session expired. Reload the page and try again.', $email);
            return;
        }
        if ($email === '' || $password === '') {
            $this->fail('Enter your email and password.', $email);
            return;
        }

        $user = LoginModel::findSuperAdminByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->fail('Email or password is incorrect.', $email);
            return;
        }

        LoginModel::recordSuperAdminLogin((int) $user['id']);
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']];
        DashboardController::redirect(DashboardController::url('/overview'));
    }

    public function logout(): void
    {
        if (DashboardController::csrfValid()) {
            $_SESSION = [];
            session_destroy();
        }
        DashboardController::redirect(DashboardController::url('/login'));
    }

    private function fail(string $message, string $email): void
    {
        $_SESSION['flash_error'] = $message;
        $_SESSION['flash_email'] = $email;
        DashboardController::redirect(DashboardController::url('/login'));
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
