<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/loginModel.php';

requireAuthenticated();

if (empty($_SESSION['must_change_password'])) {
    header('Location: ' . assetUrl('app/controllers/dispatchController.php'));
    exit;
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = LoginModel::changePassword(
        (int) ($_SESSION['user_id'] ?? 0),
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );

    $message = $result['message'];
    $success = $result['success'];

    if ($success) {
        $destinations = [
            'hospital_admin' => 'app/controllers/dispatchController.php',
            'doctor' => 'app/controllers/DoctorNotifiedCasesController.php',
            'nurse' => 'app/controllers/NurseNotifiedCasesController.php',
        ];
        $destination = $destinations[$_SESSION['role'] ?? ''] ?? 'app/controllers/portalController.php';
        header('Location: ' . assetUrl($destination));
        exit;
    }
}

require __DIR__ . '/../views/passwordChangeView.php';
