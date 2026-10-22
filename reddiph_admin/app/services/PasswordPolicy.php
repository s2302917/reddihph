<?php

declare(strict_types=1);

final class PasswordPolicy
{
    public static function errors(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8 || strlen($password) > 72) {
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
}
