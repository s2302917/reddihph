<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/hospitalnetworkModel.php';

requireAuthenticated('hospital_admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$referralId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
$referral = $referralId && $hospitalId > 0
    ? (new HospitalnetworkModel())->getReferralStatus((int) $referralId, $hospitalId)
    : null;

if (!$referral) {
    header('Location: ' . assetUrl('app/controllers/hospitalnetworkController.php'));
    exit;
}

require __DIR__ . '/../views/referralstatusView.php';