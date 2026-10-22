<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

/**
 * Lookups on the shared user_type table that apply to every role.
 */
final class UserTypeModel
{
    /**
     * Gender choices come from the user_type.gender enum, so the database stays the
     * single source of truth. Returns [] until migrations/002 has been applied.
     *
     * @return string[]
     */
    public static function genderOptions(): array
    {
        $stmt = Database::connection()->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_type' AND COLUMN_NAME = 'gender'"
        );
        preg_match_all("/'([^']+)'/", (string) $stmt->fetchColumn(), $matches);

        return $matches[1];
    }

    public static function emailExists(string $email): bool
    {
        return self::exists('work_email', $email);
    }

    public static function licenseExists(string $license): bool
    {
        return self::exists('license_number', $license);
    }

    private static function exists(string $column, string $value): bool
    {
        // $column is only ever one of the two literals above, never user input.
        $stmt = Database::connection()->prepare("SELECT 1 FROM user_type WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$value]);

        return (bool) $stmt->fetchColumn();
    }
}
