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
            'platform' => self::queryValue('platform') ?: self::queryValue('scope'),
            'query' => self::queryValue('query'),
            'action' => self::queryValue('action'),
            'from' => self::queryValue('from'),
            'to' => self::queryValue('to'),
        ];
        if (!in_array($filters['platform'], ['', 'superadmin', 'website', 'mobile', 'system'], true)) {
            $filters['platform'] = '';
        }
        foreach (['from', 'to'] as $dateKey) {
            if ($filters[$dateKey] !== '') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$dateKey]);
                if (!$date || $date->format('Y-m-d') !== $filters[$dateKey]) {
                    $filters[$dateKey] = '';
                }
            }
        }
        $pageSize = 15;
        try {
            $totalLogs = AuditLogsModel::count($filters);
            $totalPages = max(1, (int) ceil($totalLogs / $pageSize));
            $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $currentPage = max(1, min(
                $totalPages,
                is_int($requestedPage) ? $requestedPage : 1
            ));
            $data = [
                'auditRows' => AuditLogsModel::search(
                    $filters,
                    $pageSize,
                    ($currentPage - 1) * $pageSize
                ),
                'filters' => $filters,
                'totalLogs' => $totalLogs,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'pageSize' => $pageSize,
            ];
        } catch (\PDOException | \RuntimeException $exception) {
            error_log('Super Admin audit logs could not load: ' . $exception->getMessage());
            $data = [
                'auditError' => 'The shared audit log schema is missing required columns. Review and apply config/migrations/20261029_shared_platform_audit.sql after the base portal migration, then reload this page.',
                'filters' => $filters,
                'totalLogs' => 0,
                'currentPage' => 1,
                'totalPages' => 1,
                'pageSize' => $pageSize,
            ];
        }
        DashboardController::renderDashboard('audit-logs', $data);
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
