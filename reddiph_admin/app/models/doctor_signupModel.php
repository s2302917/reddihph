<?php

require_once __DIR__ . '/signupModel.php';

class DoctorSignupModel
{
    public static function validateRegistration(array $data): array
    {
        return SignupModel::validateRegistration(
            $data,
            'doctor'
        );
    }


    public static function createDoctorAccount(array $data): array
    {
        return SignupModel::createAccount(
            $data,
            'doctor'
        );
    }
}