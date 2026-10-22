<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/NurseBedStatusModel.php';
require_once __DIR__ . '/../models/NurseNotifiedCasesModel.php';

requireAuthenticated('nurse');

class NurseBedStatusController
{
    private function nurseId(): int
    {
        return (int) $_SESSION['user_id'];
    }

    public function index(): void
    {
        $pageTitle = 'Bed Status';
        $currentPage = 'bed-status';
        $sidebarNurse = NurseNotifiedCasesModel::profile($this->nurseId());
        if (!$sidebarNurse) {
            http_response_code(404);
            exit('Nurse account not found.');
        }
        $_SESSION['nurse_bed_status_csrf_token'] ??= bin2hex(random_bytes(32));
        $bedStatus = NurseBedStatusModel::data($this->nurseId());
        $hospitalName = $bedStatus['hospitalName'];
        $summary = $bedStatus['summary'];
        $erBeds = $bedStatus['erBeds'];
        $pediatricBeds = $bedStatus['pediatricBeds'];

        require __DIR__ . '/../views/NurseBedStatusView.php';
    }

    public function updateStatus(): void
    {
        $this->validateCsrf();
        $bedId = filter_var($_POST['bed_id'] ?? null, FILTER_VALIDATE_INT);
        if ($bedId === false || $bedId === null || $bedId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose a valid bed.'], 422);
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['ready', 'preparing', 'occupied', 'cleaning'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose a valid bed status.'], 422);
        }
        $incidentId = null;
        if ($status === 'occupied') {
            $incidentId = filter_var($_POST['incident_id'] ?? null, FILTER_VALIDATE_INT);
            if ($incidentId === false || $incidentId === null || $incidentId <= 0) {
                $this->jsonResponse(['success' => false, 'message' => 'Choose an incident for the occupied bed.'], 422);
            }
        }

        try {
            $data = NurseBedStatusModel::updateStatus($this->nurseId(), (int) $bedId, $status, $incidentId === null ? null : (int) $incidentId);
            if ($data === null) {
                $this->jsonResponse(['success' => false, 'message' => 'That bed is not available at your hospital. Refresh and try again.'], 404);
            }
            $this->jsonResponse([
                'success' => true,
                'message' => 'Bed status updated.',
                'summary' => $data['summary'],
                'counts' => $data['counts'],
                'erBeds' => $data['erBeds'],
                'pediatricBeds' => $data['pediatricBeds'],
                'incidentOptions' => $data['incidentOptions'],
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            error_log('[NurseBedStatusController] Bed status update failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Bed status could not be updated. Please try again.'], 500);
        }
    }

    private function validateCsrf(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['nurse_bed_status_csrf_token']) ||
            !hash_equals((string) $_SESSION['nurse_bed_status_csrf_token'], $token)
        ) {
            $this->jsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }
    }

    private function jsonResponse(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    public function dispatch(): void
    {
        $action = (string) ($_GET['action'] ?? '');
        if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->updateStatus();
        }
        if ($action !== '') {
            $this->jsonResponse(['success' => false, 'message' => 'Unsupported bed-status request.'], 405);
        }
        $this->index();
    }
}

// Run this controller when its file is opened directly in the browser.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    (new NurseBedStatusController())->dispatch();
}
