<?php

require_once __DIR__ . '/../models/nurse_signupModel.php';

$formData = [
    'full_name' => '',
    'hospital_name' => '',
    'work_email' => '',
    'password' => '',
    'confirm_password' => '',
];

$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['hospital_name'] = trim($_POST['hospital_name'] ?? '');
    $formData['work_email'] = trim($_POST['work_email'] ?? '');
    $formData['password'] = $_POST['password'] ?? '';
    $formData['confirm_password'] = $_POST['confirm_password'] ?? '';

    $validation = NurseSignupModel::validateRegistration($formData);

    if ($validation['success']) {
        $record = NurseSignupModel::createNurseAccount($formData);

        if ($record['success']) {
            $successMessage = $record['message'];
            $formData = [
                'full_name' => '',
                'hospital_name' => '',
                'work_email' => '',
                'password' => '',
                'confirm_password' => '',
            ];
        } else {
            $errors[] = $record['message'];
        }
    } else {
        $errors = $validation['errors'];
    }
}

$hospitals = SignupModel::getHospitals();
require __DIR__ . '/../views/nurse_signupView.php';
