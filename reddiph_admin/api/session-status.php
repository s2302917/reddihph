<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$role = (string) ($_GET['role'] ?? '');
if (!in_array($role, ['hospital_admin', 'doctor', 'nurse'], true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Invalid account role.']);
    exit;
}

requireAuthenticated($role);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
