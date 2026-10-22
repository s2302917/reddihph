<?php

require_once __DIR__ . '/signupModel.php';

class NurseSignupModel
{
    public static function validateRegistration(array $data): array
    {
        return SignupModel::validateRegistration(
            $data,
            'nurse'
        );
    }


    public static function createNurseAccount(array $data): array
    {
        return SignupModel::createAccount(
            $data,
            'nurse'
        );
    }
}