<?php
namespace App\Models;

class SettingsModel
{
    public static function current(array $appConfig, array $databaseConfig, string $scope = 'shared'): array
    {
        DatabaseModel::requireColumns('application_settings', [
            'setting_key', 'setting_value', 'value_type', 'description', 'updated_at', 'platform_scope',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'SELECT setting_key, setting_value, value_type, description, updated_at
             FROM application_settings WHERE platform_scope = ? ORDER BY setting_key'
        );
        $stmt->execute([$scope]);
        return [
            'appName' => $appConfig['name'],
            'environment' => $appConfig['env'],
            'databaseName' => $databaseConfig['name'],
            'databaseHost' => $databaseConfig['host'],
            'scope' => $scope,
            'values' => $stmt->fetchAll(),
        ];
    }

    public static function save(string $scope, string $key, string $value, string $type, string $description, int $actorId): void
    {
        DatabaseModel::requireColumns('application_settings', [
            'platform_scope', 'setting_key', 'setting_value', 'value_type', 'description', 'updated_by_user_id',
        ]);
        if (!in_array($scope, ['shared', 'web', 'mobile'], true)) {
            throw new \InvalidArgumentException('Select a valid settings platform.');
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,119}$/', $key)) {
            throw new \InvalidArgumentException('Setting keys must start with a letter and contain only letters, numbers, dots, underscores, and hyphens.');
        }
        $normalized = match ($type) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (string) (int) $value : throw new \InvalidArgumentException('Enter a valid integer value.'),
            'boolean' => in_array(strtolower($value), ['true', 'false', '1', '0'], true) ? (strtolower($value) === 'true' || $value === '1' ? '1' : '0') : throw new \InvalidArgumentException('Boolean values must be true, false, 1, or 0.'),
            'json' => self::validateJson($value),
            'string' => $value,
            default => throw new \InvalidArgumentException('Select a valid value type.'),
        };
        $stmt = DatabaseModel::connection()->prepare(
            'INSERT INTO application_settings
                (platform_scope, setting_key, setting_value, value_type, description, updated_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type),
                description = VALUES(description), updated_by_user_id = VALUES(updated_by_user_id)'
        );
        $stmt->execute([$scope, $key, $normalized, $type, $description ?: null, $actorId]);
    }

    public static function delete(string $scope, string $key): void
    {
        DatabaseModel::requireColumns('application_settings', ['platform_scope', 'setting_key']);
        $stmt = DatabaseModel::connection()->prepare(
            'DELETE FROM application_settings WHERE platform_scope = ? AND setting_key = ?'
        );
        $stmt->execute([$scope, $key]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('The selected setting was not found.');
        }
    }

    private static function validateJson(string $value): string
    {
        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Enter valid JSON for this setting.');
        }
        return $value;
    }
}
