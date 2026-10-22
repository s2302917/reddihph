<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/livemappingModel.php';

header('Content-Type: application/json; charset=utf-8');

startApplicationSession('hospital_admin');

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? null) !== 'hospital_admin') {
    http_response_code(empty($_SESSION['logged_in']) ? 401 : 403);
    echo json_encode(['success' => false, 'message' => 'Hospital administrator access required.']);
    exit;
}

$incidentId = filter_input(INPUT_GET, 'incident_id', FILTER_VALIDATE_INT);
$hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : 0;
if (!$incidentId || $incidentId <= 0 || $hospitalId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid incident and hospital are required.']);
    exit;
}

try {
    $route = (new LivemappingModel())->getIncidentRoadRoute($incidentId, $hospitalId);
    if ($route === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The hospital-submitted incident or route coordinates are unavailable.']);
        exit;
    }

    echo json_encode(['success' => true, 'route' => $route], JSON_UNESCAPED_SLASHES);
} catch (RuntimeException $error) {
    error_log('[livemapping-route] ' . $error->getMessage());
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A road route to the hospital is currently unavailable.']);
} catch (Throwable $error) {
    error_log('[livemapping-route] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to calculate the road route.']);
}
