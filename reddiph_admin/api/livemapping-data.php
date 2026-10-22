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

try {
    $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
    if (!$hospitalId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hospital assignment required.']);
        exit;
    }

    $barangaySlug = trim((string) ($_GET['barangay'] ?? ''));
    echo json_encode([
        'success' => true,
        'data' => (new LivemappingModel())->getMapData(
            $barangaySlug !== '' ? $barangaySlug : null,
            $hospitalId,
            false
        ),
    ]);
} catch (Throwable $e) {
    error_log('[livemapping-data] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load map data.']);
}