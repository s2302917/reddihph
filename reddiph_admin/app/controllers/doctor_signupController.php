<?php

require_once __DIR__ . '/../models/doctor_signupModel.php';

class DoctorSignupController
{
    public function handleRequest(): void
    {
        $errors = [];
        $successMessage = '';
        $formData = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = [
                'full_name' => $_POST['full_name'] ?? '',
                'hospital_name' => $_POST['hospital_name'] ?? '',
                'work_email' => $_POST['work_email'] ?? '',
                'license_number' => $_POST['license_number'] ?? '',
                'password' => $_POST['password'] ?? '',
                'confirm_password' => $_POST['confirm_password'] ?? '',
            ];

            $validation = DoctorSignupModel::validateRegistration($formData);
            if (!$validation['success']) {
                $errors = $validation['errors'];
            } else {
                $result = DoctorSignupModel::createDoctorAccount($formData);
                if ($result['success']) {
                    $successMessage = $result['message'];
                    $formData = [];
                } else {
                    $errors[] = $result['message'];
                }
            }
        }

        $hospitals = SignupModel::getHospitals();
        require __DIR__ . '/../views/doctor_signupView.php';
    }
}

$controller = new DoctorSignupController();
$controller->handleRequest();
