<?php

require_once __DIR__ . '/loginModel.php';

class NurseModel
{
    public static function validateLogin(array $data): array
    {
        return LoginModel::validateLogin($data);
    }


    public static function login(array $data): array
    {
        return LoginModel::authenticate(
            $data['work_email'] ?? '',
            $data['password'] ?? '',
            'nurse'
        );
    }


    public static function createSession(array $user): void
    {
        LoginModel::createSession($user);
    }


    public static function requestPasswordReset(string $idOrEmail): array
    {
        return LoginModel::requestPasswordReset($idOrEmail, 'nurse');
    }
}