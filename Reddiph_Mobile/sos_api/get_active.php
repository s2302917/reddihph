<?php
header('Content-Type: application/json');

$residentId = isset($_GET['resident_id']) ? (int) $_GET['resident_id'] : 0;

echo json_encode([
    'success' => true,
    'active' => false,
    'resident_id' => $residentId,
    'message' => 'No active emergency case.'
]);
