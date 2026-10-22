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
        $_SESSION['doctor_history_csrf_token'] ??= bin2hex(random_bytes(32));
        $visibleCases = DoctorCaseHistoryModel::history($doctorId, $filter);
        $stats = DoctorCaseHistoryModel::stats($doctorId, $filter);

        require __DIR__ . '/../views/DoctorCaseHistoryView.php';
    }

    public function updateStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['doctor_history_csrf_token']) ||
            !hash_equals((string) $_SESSION['doctor_history_csrf_token'], $token)
        ) {
            $this->jsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }

        $incidentId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $status = strtolower(trim((string) ($_POST['status'] ?? '')));
        if (
            $incidentId === false || $incidentId === null || $incidentId <= 0 ||
            !in_array($status, ['critical', 'stable', 'resolved'], true)
        ) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose a valid incident and status.'], 422);
        }

        try {
            $updated = DoctorCaseHistoryModel::updateStatus((int) $_SESSION['user_id'], $incidentId, $status);
            if (!$updated) {
                $this->jsonResponse(['success' => false, 'message' => 'This incident was not notified to your account or is no longer available.'], 404);
            }
            $this->jsonResponse(['success' => true, 'status' => $status]);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[DoctorCaseHistoryController] Incident status update failed for user %d, incident %d, status %s: %s',
                (int) $_SESSION['user_id'],
                $incidentId,
                $status,
                $exception->getMessage()
            ));
            $this->jsonResponse(['success' => false, 'message' => 'Could not save the selected status.'], 500);
        }
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
            $this->jsonResponse([
                'success' => true,
                'cases' => DoctorCaseHistoryModel::history($doctorId, $filter),
                'stats' => DoctorCaseHistoryModel::stats($doctorId, $filter),
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
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_status') {
        $controller->updateStatus();
    } else {
        $controller->index();
    }
}
