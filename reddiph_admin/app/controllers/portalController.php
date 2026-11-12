<?php

require_once __DIR__ . '/../../config/config.php';
$loginNotice = null;
$portalRole = $_GET['role'] ?? null;
if (
    is_string($portalRole) &&
    in_array($portalRole, ['hospital_admin', 'doctor', 'nurse'], true)
) {
    startApplicationSession($portalRole);
    $loginNotice = $_SESSION['login_notice'] ?? null;
    unset($_SESSION['login_notice']);
}

function getPortalRoles(): array
{
    return [
        [
            'id' => 'hospital-admin',
            'title' => 'Hospital Admin',
            'subtitle' => 'Login to Front Desk ',
            'href' => 'hospital_admin_loginController.php',
            'cardClass' => 'card--admin',
            'icon' => 'assets/images/portal/hospital-admin.png',
        ],
        [
            'id' => 'doctor',
            'title' => 'Doctor',
            'subtitle' => 'Login to Doctor ',
            'href' => 'doctor_loginController.php',
            'cardClass' => 'card--doctor',
            'icon' => 'assets/images/portal/doctor.png',
        ],
        [
            'id' => 'nurse',
            'title' => 'Nurse',
            'subtitle' => 'Login to Nurse ',
            'href' => 'nurse_loginController.php',
            'cardClass' => 'card--nurse',
            'icon' => 'assets/images/portal/nurse.png',
        ],
    ];
}

$roles = getPortalRoles();

require __DIR__ . '/../views/portalView.php';