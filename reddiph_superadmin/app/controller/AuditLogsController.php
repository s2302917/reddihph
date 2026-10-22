<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\AuditLogsModel;

class AuditLogsController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        $filters = [
            'scope' => self::queryValue('scope'),
            'query' => self::queryValue('query'),
            'action' => self::queryValue('action'),
            'from' => self::queryValue('from'),
            'to' => self::queryValue('to'),
        ];
        foreach (['from', 'to'] as $dateKey) {
            if ($filters[$dateKey] !== '') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$dateKey]);
                if (!$date || $date->format('Y-m-d') !== $filters[$dateKey]) {
                    $filters[$dateKey] = '';
                }
            }
        }
        DashboardController::renderDashboard('audit-logs', [
            'auditRows' => AuditLogsModel::search($filters),
            'filters' => $filters,
        ]);
    }

    private static function queryValue(string $key): string
    {
        $value = $_GET[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new AuditLogsController())->index();
}
