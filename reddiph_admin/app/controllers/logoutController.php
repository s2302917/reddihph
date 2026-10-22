<?php

require_once __DIR__ . '/../models/loginModel.php';
require_once __DIR__ . '/../../config/config.php';

sendNoCacheHeaders();

$role = $_GET['role'] ?? null;
LoginModel::logout(is_string($role) ? $role : null);

header('Location: ' . assetUrl('app/controllers/portalController.php'));
exit;