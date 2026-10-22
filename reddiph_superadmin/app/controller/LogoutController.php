<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';
require_once __DIR__ . '/LoginController.php';

class LogoutController
{
    public function index(): void
    {
        (new LoginController())->logout();
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new LogoutController())->index();
}
