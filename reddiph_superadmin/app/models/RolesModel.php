<?php
namespace App\Models;

use PDO;

class RolesModel
{
    public static function data(): array
    {
        DatabaseModel::requireTables(['app_roles', 'app_permissions', 'app_role_permissions']);
        $roleRows = DatabaseModel::connection()->query(
            'SELECT r.role_key, r.display_name, r.description, r.is_system, COUNT(u.id) AS account_count
             FROM app_roles r LEFT JOIN user_type u ON u.role = r.role_key
             GROUP BY r.role_key, r.display_name, r.description, r.is_system ORDER BY r.display_name'
        )->fetchAll();
        $roleLabels = [];
        $roleCounts = [];
        foreach ($roleRows as $row) {
            $roleCounts[$row['role_key']] = (int) $row['account_count'];
            $roleLabels[$row['role_key']] = $row['display_name'];
        }

        $permissions = DatabaseModel::connection()->query(
            'SELECT id, permission_key, display_name, platform_scope FROM app_permissions ORDER BY id'
        )->fetchAll();
        $grants = DatabaseModel::connection()->query(
            'SELECT role_key, permission_id FROM app_role_permissions'
        )->fetchAll();
        $granted = [];
        foreach ($grants as $grant) {
            $granted[$grant['role_key']][(int) $grant['permission_id']] = true;
        }

        return [
            'roleCounts' => $roleCounts,
            'roleLabels' => $roleLabels,
            'roles' => $roleRows,
            'permissions' => $permissions,
            'grantedPermissions' => $granted,
        ];
    }

    public static function create(string $key, string $name, string $description): void
    {
        DatabaseModel::requireTables(['app_roles', 'app_permissions', 'app_role_permissions']);
        if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/', $key)) {
            throw new \InvalidArgumentException('Role keys must start with a letter and contain only lowercase letters, numbers, and underscores.');
        }
        $stmt = DatabaseModel::connection()->prepare(
            'INSERT INTO app_roles (role_key, display_name, description, is_system) VALUES (?, ?, ?, 0)'
        );
        $stmt->execute([$key, $name, $description]);
    }

    public static function update(string $key, string $name, string $description): void
    {
        DatabaseModel::requireTables(['app_roles']);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE app_roles SET display_name = ?, description = ? WHERE role_key = ?'
        );
        $stmt->execute([$name, $description, $key]);
        if ($stmt->rowCount() === 0 && !self::exists($key)) {
            throw new \RuntimeException('The selected role was not found.');
        }
    }

    public static function delete(string $key): void
    {
        DatabaseModel::requireTables(['app_roles', 'app_role_permissions']);
        if ($key === 'super_admin') {
            throw new \RuntimeException('The super_admin role cannot be deleted.');
        }
        $stmt = DatabaseModel::connection()->prepare('SELECT COUNT(*) FROM user_type WHERE role = ?');
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Reassign this role’s users before deleting the role.');
        }
        $stmt = DatabaseModel::connection()->prepare('DELETE FROM app_roles WHERE role_key = ?');
        $stmt->execute([$key]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('The selected role was not found.');
        }
    }

    public static function setPermission(string $role, int $permissionId, bool $granted, int $actorId): void
    {
        DatabaseModel::requireTables(['app_roles', 'app_permissions', 'app_role_permissions']);
        if ($role === 'super_admin' && !$granted) {
            throw new \RuntimeException('Super Admin permissions cannot be revoked.');
        }
        if ($granted) {
            $stmt = DatabaseModel::connection()->prepare(
                'INSERT INTO app_role_permissions (role_key, permission_id, granted_by_user_id)
                 VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE granted_by_user_id = VALUES(granted_by_user_id), granted_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$role, $permissionId, $actorId]);
            return;
        }
        $stmt = DatabaseModel::connection()->prepare(
            'DELETE FROM app_role_permissions WHERE role_key = ? AND permission_id = ?'
        );
        $stmt->execute([$role, $permissionId]);
    }

    public static function savePermissionMatrix(array $submitted, int $actorId): array
    {
        DatabaseModel::requireTables(['app_roles', 'app_permissions', 'app_role_permissions']);
        $connection = DatabaseModel::connection();
        $roles = $connection->query(
            "SELECT role_key FROM app_roles WHERE role_key <> 'super_admin'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $permissionIds = array_map(
            'intval',
            $connection->query('SELECT id FROM app_permissions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );

        if (array_diff(array_keys($submitted), $roles) || array_diff($roles, array_keys($submitted))) {
            throw new \InvalidArgumentException('The permission matrix has changed. Reload the page and try again.');
        }

        $changes = [];
        foreach ($roles as $role) {
            if (!is_array($submitted[$role]) ||
                array_map('intval', array_keys($submitted[$role])) !== $permissionIds) {
                throw new \InvalidArgumentException('The permission matrix has changed. Reload the page and try again.');
            }
            $currentStmt = $connection->prepare(
                'SELECT permission_id FROM app_role_permissions WHERE role_key = ?'
            );
            $currentStmt->execute([$role]);
            $current = array_fill_keys(array_map('intval', $currentStmt->fetchAll(PDO::FETCH_COLUMN)), true);

            $desired = [];
            foreach ($submitted[$role] as $permissionId => $value) {
                $permissionId = (int) $permissionId;
                if (!in_array($permissionId, $permissionIds, true) || !in_array($value, ['0', '1', 0, 1], true)) {
                    throw new \InvalidArgumentException('Invalid permission selection.');
                }
                if ((string) $value === '1') {
                    $desired[$permissionId] = true;
                }
            }

            foreach ($permissionIds as $permissionId) {
                $wasGranted = isset($current[$permissionId]);
                $shouldGrant = isset($desired[$permissionId]);
                if ($wasGranted === $shouldGrant) {
                    continue;
                }
                self::setPermission($role, $permissionId, $shouldGrant, $actorId);
                $changes[$role][] = ['permission_id' => $permissionId, 'granted' => $shouldGrant];
            }
        }

        return $changes;
    }

    private static function exists(string $key): bool
    {
        $stmt = DatabaseModel::connection()->prepare('SELECT 1 FROM app_roles WHERE role_key = ?');
        $stmt->execute([$key]);
        return (bool) $stmt->fetchColumn();
    }
}
