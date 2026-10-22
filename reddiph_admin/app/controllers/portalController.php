<?php

require_once __DIR__ . '/../../config/config.php';
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