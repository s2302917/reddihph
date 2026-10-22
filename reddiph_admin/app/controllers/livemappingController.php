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

        $result = $this->model->getAlertStaffOptions($incidentId, $hospitalId);

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($result['success'] ? 200 : 404);
        echo json_encode($result);
        exit;
    }

    public function sendAlert(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $result = $this->model->sendStaffAlert($payload, (int) ($_SESSION['hospital_id'] ?? 0));

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($result['success'] ? 200 : 422);
        echo json_encode($result);
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
            (int) ($_SESSION['hospital_id'] ?? 0)
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