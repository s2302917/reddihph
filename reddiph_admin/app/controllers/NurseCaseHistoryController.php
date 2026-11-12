<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/NurseCaseHistoryModel.php';
require_once __DIR__ . '/../models/NurseNotifiedCasesModel.php';

requireAuthenticated('nurse');

class NurseCaseHistoryController
{
    public function index(): void
    {
        $filter = $_GET['filter'] ?? 'all';
        if (!in_array($filter, ['all', 'week', 'month'], true)) {
            $filter = 'all';
        }

        $nurseId = (int) $_SESSION['user_id'];
        $sidebarNurse = NurseNotifiedCasesModel::profile($nurseId);
        if (!$sidebarNurse) {
            http_response_code(404);
            exit('Nurse account not found.');
        }

        $pageTitle = 'Case History';
        $currentPage = 'case-history';
        $visibleCases = NurseCaseHistoryModel::history($nurseId, $filter);
        $stats = NurseCaseHistoryModel::stats(
            $nurseId,
            $filter,
            NurseNotifiedCasesModel::activeCount($nurseId)
        );

        require __DIR__ . '/../views/NurseCaseHistoryView.php';
    }

    public function refresh(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $filter = $_GET['filter'] ?? 'all';
        if (!in_array($filter, ['all', 'week', 'month'], true)) {
            $filter = 'all';
        }

        try {
            $nurseId = (int) $_SESSION['user_id'];
            $activeNotifiedCount = NurseNotifiedCasesModel::activeCount($nurseId);
            $this->jsonResponse([
                'success' => true,
                'cases' => NurseCaseHistoryModel::history($nurseId, $filter),
                'stats' => NurseCaseHistoryModel::stats($nurseId, $filter, $activeNotifiedCount),
            ], 200);
        } catch (Throwable $exception) {
            error_log('[NurseCaseHistoryController] Case-history refresh failed: ' . $exception->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Case history could not be refreshed.',
            ], 500);
        }
    }

    private function jsonResponse(array $payload, int $status): never
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $controller = new NurseCaseHistoryController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'refresh') {
        $controller->refresh();
    } else {
        $controller->index();
    }
}
