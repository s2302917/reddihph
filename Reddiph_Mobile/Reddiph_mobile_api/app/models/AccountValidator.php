<?php

declare(strict_types=1);

/**
 * Validation rules shared by every signup role. Pure functions, no database access.
 */
final class AccountValidator
{
    private const MIN_PASSWORD_LENGTH = 8;
    // bcrypt ignores bytes beyond 72.
    private const MAX_PASSWORD_LENGTH = 72;
    private const MAX_NAME_LENGTH = 150;
    private const MAX_EMAIL_LENGTH = 150;
    private const MAX_LICENSE_LENGTH = 50;
    private const PHONE_PATTERN = '/^[0-9+\-\s()]{7,20}$/';
    private const MIN_AGE = 1;
    private const MAX_AGE = 120;

    /**
     * Checks name, email, age, gender, phone and password.
     *
     * @param string[] $genders allowed gender values read from the database
     * @return string[] error messages
     */
    public static function profileErrors(array $data, array $genders): array
    {
        $errors = [];
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = trim((string) ($data['work_email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $age = filter_var($data['age'] ?? null, FILTER_VALIDATE_INT);

        if ($fullName === '' || mb_strlen($fullName) > self::MAX_NAME_LENGTH) {
            $errors[] = 'Enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > self::MAX_EMAIL_LENGTH) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($age === false || $age < self::MIN_AGE || $age > self::MAX_AGE) {
            $errors[] = 'Enter a valid age.';
        }
        if (!in_array($data['gender'] ?? null, $genders, true)) {
            $errors[] = 'Select your gender.';
        }
        if (!preg_match(self::PHONE_PATTERN, trim((string) ($data['phone_number'] ?? '')))) {
            $errors[] = 'Enter a valid mobile number.';
        }
        $errors = array_merge($errors, self::passwordErrors($password));
        if (!hash_equals($password, (string) ($data['confirm_password'] ?? ''))) {
            $errors[] = 'Passwords do not match.';
        }

        return $errors;
    }

    /** Returns password complexity errors shared by signup and password updates. */
    public static function passwordErrors(string $password): array
    {
        $errors = [];
        if (strlen($password) < self::MIN_PASSWORD_LENGTH || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $errors[] = 'Password must be 8 to 72 characters.';
            return $errors;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors;
    }

    /**
     * Checks the hospital, the barangay and, for doctors, the license number.
     *
     * @return string[] error messages
     */
    public static function workplaceErrors(array $data, string $role): array
    {
        $errors = [];
        $license = trim((string) ($data['license_number'] ?? ''));

        if ($role === 'doctor' && ($license === '' || strlen($license) > self::MAX_LICENSE_LENGTH)) {
            $errors[] = 'Enter your license number.';
        }
        if (filter_var($data['hospital_id'] ?? null, FILTER_VALIDATE_INT) === false
            || filter_var($data['barangay_id'] ?? null, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Select a hospital and barangay.';
        }

        return $errors;
    }
}
