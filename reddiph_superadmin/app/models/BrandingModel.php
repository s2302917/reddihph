<?php
namespace App\Models;

class BrandingModel
{
    public static function current(array $appConfig): array
    {
        DatabaseModel::requireColumns('application_branding', [
            'platform_scope', 'app_name', 'logo_path', 'favicon_path', 'primary_color', 'secondary_color',
        ]);
        $rows = DatabaseModel::connection()->query(
            'SELECT platform_scope, app_name, logo_path, favicon_path, primary_color, secondary_color
             FROM application_branding'
        )->fetchAll();
        $branding = [];
        foreach ($rows as $row) {
            $branding[$row['platform_scope']] = $row;
        }
        return [
            'name' => $appConfig['name'],
            'platforms' => $branding,
        ];
    }

    public static function save(string $scope, array $data, int $actorId): void
    {
        DatabaseModel::requireColumns('application_branding', [
            'platform_scope', 'app_name', 'logo_path', 'favicon_path', 'primary_color',
            'secondary_color', 'updated_by_user_id',
        ]);
        if (!in_array($scope, ['shared', 'web', 'mobile'], true)) {
            throw new \InvalidArgumentException('Select a valid branding platform.');
        }
        foreach (['primary_color', 'secondary_color'] as $color) {
            if ($data[$color] !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $data[$color])) {
                throw new \InvalidArgumentException('Colors must use six-digit hexadecimal values.');
            }
        }
        foreach (['logo_path', 'favicon_path'] as $path) {
            if (strlen($data[$path]) > 255) {
                throw new \InvalidArgumentException('Branding paths must be 255 characters or fewer.');
            }
        }
        $scopes = $scope === 'shared' ? ['shared', 'web', 'mobile'] : [$scope];
        $values = implode(', ', array_fill(0, count($scopes), '(?, ?, ?, ?, ?, ?, ?)'));
        $parameters = [];
        foreach ($scopes as $targetScope) {
            array_push(
                $parameters,
                $targetScope,
                $data['app_name'] ?: null,
                $data['logo_path'] ?: null,
                $data['favicon_path'] ?: null,
                $data['primary_color'] ?: null,
                $data['secondary_color'] ?: null,
                $actorId
            );
        }
        $stmt = DatabaseModel::connection()->prepare(
            "INSERT INTO application_branding
                (platform_scope, app_name, logo_path, favicon_path, primary_color, secondary_color, updated_by_user_id)
             VALUES {$values}
             ON DUPLICATE KEY UPDATE app_name = VALUES(app_name), logo_path = VALUES(logo_path),
                favicon_path = VALUES(favicon_path), primary_color = VALUES(primary_color),
                secondary_color = VALUES(secondary_color), updated_by_user_id = VALUES(updated_by_user_id)"
        );
        $stmt->execute($parameters);
    }
}
