<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\BrandingModel;
use App\Models\AuditLogsModel;

class BrandingController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            DashboardController::runPostAction('/branding', function (): string {
                $scope = trim(DashboardController::postString('scope', 'shared'));
                $data = [
                    'app_name' => trim(DashboardController::postString('app_name')),
                    'logo_path' => trim(DashboardController::postString('logo_path')),
                    'favicon_path' => trim(DashboardController::postString('favicon_path')),
                    'primary_color' => trim(DashboardController::postString('primary_color')),
                    'secondary_color' => trim(DashboardController::postString('secondary_color')),
                ];
                if (strlen($data['app_name']) > 120) {
                    throw new \InvalidArgumentException('Application names must be 120 characters or fewer.');
                }
                BrandingModel::save($scope, $data, (int) $_SESSION['user']['id']);
                AuditLogsModel::record((int) $_SESSION['user']['id'], 'branding.updated', 'branding', $scope, $data + ['platform_scope' => $scope]);
                return ucfirst($scope) . ' branding saved.';
            });
        }
        $config = require BASE_PATH . '/config/config.php';
        $scope = $_GET['scope'] ?? 'shared';
        if (!is_string($scope) || !in_array($scope, ['shared', 'web', 'mobile'], true)) {
            $scope = 'shared';
        }
        $branding = BrandingModel::current($config['app']);
        $defaultBranding = [
            'app_name' => $config['app']['name'],
            'logo_path' => 'assets/images/logo.png',
            'favicon_path' => '',
            'primary_color' => '#8d1018',
            'secondary_color' => '#d4803a',
        ];
        DashboardController::renderDashboard('branding', [
            'branding' => $branding,
            'selectedScope' => $scope,
            'selectedBranding' => $branding['platforms'][$scope] ?? $defaultBranding,
            'defaultBranding' => $defaultBranding,
        ]);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new BrandingController())->index();
}
