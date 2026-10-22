<?php

require_once __DIR__ . '/../models/doctor_loginModel.php';

class DoctorLoginController
{
    public function handleRequest(): void
    {
        sendNoCacheHeaders();

        $errors = [];
        $loginMessage = '';
        $resetStatus = null;

        $formData = [
            'work_email' => '',
            'password' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $action = $_POST['action'] ?? '';

            /*
             * LOGIN
             */
            if ($action === 'doctor_login') {

                $formData = [
                    'work_email' => trim($_POST['work_email'] ?? ''),
                    'password' => $_POST['password'] ?? ''
                ];

                $validation =
                    DoctorModel::validateLogin($formData);

                if (!$validation['success']) {

                    $errors = $validation['errors'];

                } else {

                    $result =
                        DoctorModel::login($formData);

                    if ($result['success']) {

                        DoctorModel::createSession(
                            $result['user']
                        );

                        header('Location: ' . assetUrl('app/controllers/DoctorNotifiedCasesController.php'));
                        exit;

                    } else {

                        $errors[] = $result['message'];
                    }
                }
            }


            /*
             * FORGOT PASSWORD
             */ elseif ($action === 'doctor_forgot_password') {

                $idOrEmail = $_POST['email'] ?? '';

                $resetStatus =
                    DoctorModel::requestPasswordReset(
                        $idOrEmail
                    );
            }
        }

        $formData['work_email'] = '';
        $formData['password'] = '';

        require __DIR__ . '/../views/doctor_loginView.php';
    }
}

$controller = new DoctorLoginController();
$controller->handleRequest();