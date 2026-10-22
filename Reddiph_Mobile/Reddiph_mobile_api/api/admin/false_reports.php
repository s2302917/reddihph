<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/controllers/FalseReportController.php';

$action = (string) ($_GET['action'] ?? '');
if ($action === 'queue') {
    FalseReportController::adminQueue();
}
if ($action === 'review') {
    FalseReportController::review();
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
