<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\OverviewModel;

class OverviewController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        DashboardController::renderDashboard('overview', OverviewModel::data());
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new OverviewController())->index();
}
