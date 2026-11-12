<?php

require_once __DIR__ . '/../../config/config.php';

$role = $_GET['role'] ?? '';
if (!in_array($role, ['doctor', 'nurse'], true)) {
    http_response_code(400);
    exit('Invalid staff role.');
}

requireAuthenticated($role);
require_once __DIR__ . '/../models/StaffIncomingAlertsModel.php';

class StaffIncomingAlertsController
{
    public function handle(string $role): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $action = $_GET['action'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            if ($action === 'pending' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $this->respond([
                    'success' => true,
                    'alerts' => StaffIncomingAlertsModel::pending($userId, $role),
                ]);
            }

            if ($action === 'acknowledge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $token = (string) ($_POST['csrf_token'] ?? '');
                if (
                    empty($_SESSION['staff_alert_csrf_token']) ||
                    !hash_equals((string) $_SESSION['staff_alert_csrf_token'], $token)
                ) {
                    $this->respond(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
                }

                $alertId = filter_var($_POST['alert_id'] ?? null, FILTER_VALIDATE_INT);
                if ($alertId === false || $alertId === null || $alertId <= 0) {
                    $this->respond(['success' => false, 'message' => 'The incoming alert is invalid.'], 422);
                }
                $outcome = StaffIncomingAlertsModel::acknowledge($userId, $role, $alertId);
                if ($outcome === 'already_processed') {
                    $this->respond([
                        'success' => false,
                        'code' => 'already_processed',
                        'message' => 'This notification has already been processed. Refresh alerts to continue.',
                    ], 409);
                }
                if ($outcome === 'not_found') {
                    $this->respond([
                        'success' => false,
                        'code' => 'not_found',
                        'message' => 'This notification was not found or is not assigned to your account.',
                    ], 404);
                }
                if ($outcome !== 'acknowledged') {
                    $this->respond([
                        'success' => false,
                        'code' => 'processing_failed',
                        'message' => 'The notification is still pending but could not be processed. Please try again.',
                    ], 500);
                }
                $this->respond(['success' => true]);
            }

            $this->respond(['success' => false, 'message' => 'Unsupported notification request.'], 405);
        } catch (Throwable $exception) {
            error_log('[StaffIncomingAlertsController] Notification request failed: ' . $exception->getMessage());
            $isAcknowledgement = $action === 'acknowledge';
            $this->respond([
                'success' => false,
                'code' => $isAcknowledgement ? 'processing_failed' : 'load_failed',
                'message' => $isAcknowledgement
                    ? 'The notification could not be processed. Refresh alerts before retrying.'
                    : 'Incoming incident notifications could not be loaded. Please try again.',
            ], 500);
        }
    }

    private function respond(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}

(new StaffIncomingAlertsController())->handle($role);
