<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/DoctorCaseHistoryModel.php';
require_once __DIR__ . '/../models/DoctorNotifiedCasesModel.php';

requireAuthenticated('doctor');

class DoctorCaseHistoryController
{
    public function index(): void
    {
        $pageTitle = 'Case History';
        $currentPage = 'case-history';
        $filter = $_GET['filter'] ?? 'all';
        if (!in_array($filter, ['all', 'week', 'month'], true)) {
            $filter = 'all';
        }

        $doctorId = (int) $_SESSION['user_id'];
        $sidebarDoctor = DoctorNotifiedCasesModel::sidebarProfile($doctorId);
        if (!$sidebarDoctor) {
            http_response_code(404);
            exit('Doctor account not found.');
        }
        $visibleCases = DoctorCaseHistoryModel::history($doctorId, $filter);
        $stats = DoctorCaseHistoryModel::stats(
            $doctorId,
            $filter,
            DoctorNotifiedCasesModel::activeCount($doctorId)
        );

        require __DIR__ . '/../views/DoctorCaseHistoryView.php';
    }

    public function refresh(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $filter = $_GET['filter'] ?? 'all';
        if (!in_array($filter, ['all', 'week', 'month'], true)) {
            $filter = 'all';
        }

        try {
            $doctorId = (int) $_SESSION['user_id'];
            $activeNotifiedCount = DoctorNotifiedCasesModel::activeCount($doctorId);
            $this->jsonResponse([
                'success' => true,
                'cases' => DoctorCaseHistoryModel::history($doctorId, $filter),
                'stats' => DoctorCaseHistoryModel::stats($doctorId, $filter, $activeNotifiedCount),
            ], 200);
        } catch (Throwable $exception) {
            error_log('[DoctorCaseHistoryController] Case-history refresh failed: ' . $exception->getMessage());
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

// Run this controller when its file is opened directly in the browser.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $controller = new DoctorCaseHistoryController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'refresh') {
        $controller->refresh();
    } else {
        $controller->index();
    }
}
