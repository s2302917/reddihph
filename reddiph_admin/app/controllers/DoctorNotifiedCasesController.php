<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/DoctorNotifiedCasesModel.php';

requireAuthenticated('doctor');

class DoctorNotifiedCasesController
{
    private function doctorId(): int
    {
        return (int) $_SESSION['user_id'];
    }

    public function index(): void
    {
        $pageTitle = 'Notified Cases';
        $currentPage = 'notified-cases';
        $doctor = DoctorNotifiedCasesModel::sidebarProfile($this->doctorId());
        if (!$doctor) {
            http_response_code(404);
            exit('Doctor account not found.');
        }

        $_SESSION['doctor_duty_csrf_token'] ??= bin2hex(random_bytes(32));
        $_SESSION['profile_image'] = $doctor['profile_image'] ?? null;

        $summary = DoctorNotifiedCasesModel::summary($this->doctorId());
        $cases = DoctorNotifiedCasesModel::cases($this->doctorId());
        $pendingAlerts = DoctorNotifiedCasesModel::pendingAlerts($this->doctorId());

        require __DIR__ . '/../views/DoctorNotifiedCasesView.php';
    }

    public function updateAvailability(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['doctor_duty_csrf_token']) ||
            !hash_equals((string) $_SESSION['doctor_duty_csrf_token'], $token)
        ) {
            $this->jsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }

        $requestedStatus = $_POST['availability'] ?? null;
        if (!in_array($requestedStatus, ['on', 'off'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose a valid duty status.'], 422);
        }

        try {
            $available = $requestedStatus === 'on';
            DoctorNotifiedCasesModel::setAvailability($this->doctorId(), $available);
            $this->jsonResponse([
                'success' => true,
                'available' => $available,
                'message' => $available ? 'You are now on duty.' : 'You are now off duty.',
            ]);
        } catch (Throwable $exception) {
            error_log('[DoctorNotifiedCasesController] Duty status update failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Duty status could not be updated. Please try again.'], 500);
        }
    }

    public function pendingAlerts(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $this->jsonResponse([
                'success' => true,
                'alerts' => DoctorNotifiedCasesModel::pendingAlerts($this->doctorId()),
                'cases' => DoctorNotifiedCasesModel::cases($this->doctorId()),
                'summary' => DoctorNotifiedCasesModel::summary($this->doctorId()),
            ]);
        } catch (Throwable $exception) {
            error_log('[DoctorNotifiedCasesController] Pending alerts could not be loaded: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Incoming alerts could not be loaded. Please refresh the page.'], 500);
        }
    }

    public function acknowledgeAlert(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['doctor_duty_csrf_token']) ||
            !hash_equals((string) $_SESSION['doctor_duty_csrf_token'], $token)
        ) {
            $this->jsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }

        $alertId = filter_var($_POST['alert_id'] ?? null, FILTER_VALIDATE_INT);
        if ($alertId === false || $alertId === null || $alertId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'The incoming alert is invalid.'], 422);
        }

        try {
            if (!DoctorNotifiedCasesModel::acknowledgeAlert($this->doctorId(), $alertId)) {
                $this->jsonResponse(['success' => false, 'message' => 'This alert is no longer pending. Refresh the page to continue.'], 409);
            }
            $this->jsonResponse(['success' => true]);
        } catch (Throwable $exception) {
            error_log('[DoctorNotifiedCasesController] Incoming alert acknowledgement failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'The alert could not be acknowledged. Please try again.'], 500);
        }
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}

// Run this controller when its file is opened directly in the browser.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $controller = new DoctorNotifiedCasesController();
    if (($_GET['action'] ?? '') === 'pending_alerts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->pendingAlerts();
    } elseif (($_GET['action'] ?? '') === 'acknowledge_alert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->acknowledgeAlert();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->updateAvailability();
    } else {
        $controller->index();
    }
}
