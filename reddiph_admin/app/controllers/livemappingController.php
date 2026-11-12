<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/livemappingModel.php';

requireAuthenticated('hospital_admin');

class LivemappingController
{
    private LivemappingModel $model;

    public function __construct()
    {
        $this->model = new LivemappingModel();
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['logged_in'])) {
            header('Location: ' . assetUrl('app/controllers/portalController.php'));
            exit;
        }

        $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
        if (!$hospitalId) {
            http_response_code(403);
            exit('Hospital assignment required.');
        }

        $barangaySlug = trim((string) ($_GET['barangay'] ?? ''));
        $mapData = $this->model->getMapData($barangaySlug !== '' ? $barangaySlug : null, $hospitalId);
        $_SESSION['staff_alert_csrf'] ??= bin2hex(random_bytes(32));
        $staffAlertCsrf = (string) $_SESSION['staff_alert_csrf'];
        $hospitalName = $_SESSION['hospital_name'] ?? 'Hospital #' . $hospitalId;
        $coordinatorName = $_SESSION['full_name'] ?? 'Hospital Administrator';
        $coordinatorRole = $_SESSION['role'] ?? 'hospital_admin';
        $coordinatorImage = userProfileImageUrl($_SESSION['profile_image'] ?? null);

        require __DIR__ . '/../views/livemappingView.php';
    }

    public function alertStaffOptions(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
        $incidentId = filter_input(INPUT_GET, 'incident_id', FILTER_VALIDATE_INT)
            ?: (int) ($_GET['incident_id'] ?? 0);

        try {
            $result = $this->model->getAlertStaffOptions(
                $incidentId,
                $hospitalId,
                trim(substr((string) ($_GET['search'] ?? ''), 0, 100)),
                max(1, (int) ($_GET['page'] ?? 1)),
                trim(substr((string) ($_GET['specialization'] ?? ''), 0, 120)),
                trim((string) ($_GET['online_status'] ?? 'all')),
                trim((string) ($_GET['account_status'] ?? 'all')),
                trim((string) ($_GET['duty_status'] ?? 'all'))
            );
            $status = $result['success'] ? 200 : 404;
        } catch (Throwable $e) {
            error_log('[LivemappingController] alertStaffOptions: ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Eligible staff could not be loaded. Please try again.'];
            $status = 500;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function sendAlert(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!hash_equals(
            (string) ($_SESSION['staff_alert_csrf'] ?? ''),
            (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')
        )) {
            $result = ['success' => false, 'message' => 'Your session verification expired. Refresh the page and try again.'];
            $status = 403;
        } else {
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $result = ['success' => false, 'message' => 'Invalid alert request.'];
                $status = 400;
            } else {
                try {
                    $result = $this->model->sendStaffAlert(
                        $payload,
                        (int) ($_SESSION['hospital_id'] ?? 0)
                    );
                    $status = $result['success'] ? 200 : 422;
                } catch (Throwable $e) {
                    error_log('[LivemappingController] sendAlert: ' . $e->getMessage());
                    $result = ['success' => false, 'message' => 'The staff alert could not be queued. Please try again.'];
                    $status = 500;
                }
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function staffAlertReceipts(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        try {
            $hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
            if ($hospitalId <= 0) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Hospital assignment required.']);
                exit;
            }

            $alerts = $this->model->getStaffAlertReceipts($hospitalId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'alerts' => $alerts]);
        } catch (Throwable $e) {
            error_log('[LivemappingController] staffAlertReceipts: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Staff alert status is temporarily unavailable.']);
        }
        exit;
    }

    public function markArrived(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $success = $this->model->markTransportArrived(
            (int) ($payload['incident_id'] ?? 0),
            trim((string) ($payload['transport_name'] ?? '')),
            (int) ($_SESSION['hospital_id'] ?? 0),
            (int) ($_SESSION['user_id'] ?? 0)
        );

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($success ? 200 : 422);
        echo json_encode(['success' => $success]);
        exit;
    }

    public function updateEta(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $etaMinutes = filter_var($payload['eta_minutes'] ?? null, FILTER_VALIDATE_INT);
        $success = $etaMinutes !== false
            && $etaMinutes >= 0
            && $etaMinutes <= 1440
            && $this->model->updateIncidentEta(
                (int) ($payload['incident_id'] ?? 0),
                trim((string) ($payload['transport_name'] ?? '')),
                $etaMinutes,
                (int) ($_SESSION['hospital_id'] ?? 0)
            );

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($success ? 200 : 422);
        echo json_encode(['success' => $success]);
        exit;
    }

    public function updateLocation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $latitude = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $result = $latitude !== false && $longitude !== false
            ? $this->model->updateTransportLocation(
                (int) ($payload['incident_id'] ?? 0),
                trim((string) ($payload['transport_name'] ?? '')),
                (float) $latitude,
                (float) $longitude,
                (int) ($_SESSION['hospital_id'] ?? 0)
            )
            : ['success' => false, 'message' => 'Valid latitude and longitude are required.'];

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($result['success'] ? 200 : 422);
        echo json_encode($result);
        exit;
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $controller = new LivemappingController();
    if (($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') && ($_GET['action'] ?? '') === 'alert_staff_options') {
        $controller->alertStaffOptions();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'send_alert') {
        $controller->sendAlert();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'staff_alert_receipts') {
        $controller->staffAlertReceipts();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'mark_arrived') {
        $controller->markArrived();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_eta') {
        $controller->updateEta();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_location') {
        $controller->updateLocation();
    }
    $controller->index();
}