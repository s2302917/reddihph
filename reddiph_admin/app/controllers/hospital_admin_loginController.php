<?php

require_once __DIR__ . '/../models/hospital_admin_loginModel.php';

class HospitalAdminLoginController
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
            if ($action === 'admin_login') {

                $formData = [
                    'work_email' => trim($_POST['work_email'] ?? ''),
                    'password' => $_POST['password'] ?? ''
                ];

                $validation =
                    HospitalAdminModel::validateLogin($formData);

                if (!$validation['success']) {

                    $errors = $validation['errors'];

                } else {

                    $result =
                        HospitalAdminModel::login($formData);

                    if ($result['success']) {

                        HospitalAdminModel::createSession(
                            $result['user']
                        );

                        header('Location: ' . assetUrl('app/controllers/dispatchController.php'));
                        exit;
                    } else {

                        $errors[] = $result['message'];
                    }
                }
            }


            /*
             * FORGOT PASSWORD
             */ elseif ($action === 'admin_forgot_password') {

                $email = $_POST['email'] ?? '';

                $resetStatus =
                    HospitalAdminModel::requestPasswordReset($email);
            }
        }

        $formData['work_email'] = '';
        $formData['password'] = '';

        require __DIR__ . '/../views/hospital_admin_loginView.php';
    }
}

$controller = new HospitalAdminLoginController();
$controller->handleRequest();