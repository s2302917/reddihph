<?php

require_once __DIR__ . '/signupModel.php';

class HospitalAdminSignupModel
{
    public static function validateRegistration(array $data): array
    {
        return SignupModel::validateRegistration(
            $data,
            'hospital_admin'
        );
    }


    public static function createHospitalAdminAccount(array $data): array
    {
        return SignupModel::createAccount(
            $data,
            'hospital_admin'
        );
    }
}