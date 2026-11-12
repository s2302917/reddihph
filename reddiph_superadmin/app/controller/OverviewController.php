<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\OverviewModel;

class OverviewController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        try {
            DashboardController::renderDashboard('overview', OverviewModel::data());
        } catch (\RuntimeException $exception) {
            error_log('Super Admin overview could not load: ' . $exception->getMessage());
            DashboardController::renderDashboard('overview', [
                'overviewError' => 'The overview could not load from the configured database. Check the database connection and confirm the backup schema is present.',
            ]);
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new OverviewController())->index();
}
