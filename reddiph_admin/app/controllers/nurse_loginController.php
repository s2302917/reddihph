<?php

require_once __DIR__ . '/../models/nurse_loginModel.php';

class NurseLoginController
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
            if ($action === 'nurse_login') {

                $formData = [
                    'work_email' => trim($_POST['work_email'] ?? ''),
                    'password' => $_POST['password'] ?? ''
                ];

                $validation =
                    NurseModel::validateLogin($formData);

                if (!$validation['success']) {

                    $errors = $validation['errors'];

                } else {

                    $result =
                        NurseModel::login($formData);

                    if ($result['success']) {

                        NurseModel::createSession(
                            $result['user']
                        );

                        header('Location: ' . assetUrl('app/controllers/NurseNotifiedCasesController.php'));
                        exit;

                    } else {
                        $errors[] = $result['message'];
                    }
                }
            }


            /*
             * FORGOT PASSWORD
             */ elseif ($action === 'nurse_forgot_password') {

                $idOrEmail = $_POST['email'] ?? '';

                $resetStatus =
                    NurseModel::requestPasswordReset(
                        $idOrEmail
                    );
            }
        }

        $formData['work_email'] = '';
        $formData['password'] = '';

        require __DIR__ . '/../views/nurse_loginView.php';
    }
}

$controller = new NurseLoginController();
$controller->handleRequest();