<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\SettingsModel;
use App\Models\AuditLogsModel;

class SettingsController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            DashboardController::runPostAction('/settings', function (): string {
                $action = DashboardController::postString('action');
                $scope = trim(DashboardController::postString('scope', 'shared'));
                $key = trim(DashboardController::postString('setting_key'));
                if ($action === 'save') {
                    $type = trim(DashboardController::postString('value_type', 'string'));
                    $value = DashboardController::postString('setting_value');
                    $description = trim(DashboardController::postString('description'));
                    if (strlen($description) > 255) {
                        throw new \InvalidArgumentException('Descriptions must be 255 characters or fewer.');
                    }
                    SettingsModel::save($scope, $key, $value, $type, $description, (int) $_SESSION['user']['id']);
                    AuditLogsModel::record((int) $_SESSION['user']['id'], 'setting.saved', 'setting', $key, ['scope' => $scope]);
                    return 'Setting saved.';
                }
                if ($action === 'delete') {
                    SettingsModel::delete($scope, $key);
                    AuditLogsModel::record((int) $_SESSION['user']['id'], 'setting.deleted', 'setting', $key, ['scope' => $scope]);
                    return 'Setting deleted.';
                }
                throw new \InvalidArgumentException('Choose a valid settings action.');
            });
        }
        $config = require BASE_PATH . '/config/config.php';
        $scope = $_GET['scope'] ?? 'shared';
        if (!is_string($scope) || !in_array($scope, ['shared', 'web', 'mobile'], true)) {
            $scope = 'shared';
        }
        DashboardController::renderDashboard('settings', [
            'settings' => SettingsModel::current($config['app'], $config['db'], $scope),
        ]);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new SettingsController())->index();
}
