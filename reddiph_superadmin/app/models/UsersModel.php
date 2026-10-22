<?php
namespace App\Models;

use PDO;

class UsersModel
{
    public static function all(?string $role = null): array
    {
        DatabaseModel::requireColumns('user_type', [
            'id', 'full_name', 'work_email', 'role', 'hospital_name', 'phone_number',
            'hospital_id', 'barangay_id', 'last_login_at', 'created_at', 'banned_until',
        ]);
        $sql = 'SELECT id, full_name AS name, work_email AS email, role, hospital_name, phone_number,
                       hospital_id, barangay_id, last_login_at, created_at,
                       (banned_until IS NOT NULL AND banned_until > CURRENT_TIMESTAMP) AS is_login_disabled
                FROM user_type';
        if ($role !== null) {
            $stmt = DatabaseModel::connection()->prepare("$sql WHERE role = ? ORDER BY created_at DESC");
            $stmt->execute([$role]);
            return $stmt->fetchAll();
        }

        return DatabaseModel::connection()->query("$sql ORDER BY created_at DESC")->fetchAll();
    }

    public static function roleCounts(): array
    {
        DatabaseModel::requireTables(['app_roles']);
        $counts = array_fill_keys(array_column(
            DatabaseModel::connection()->query('SELECT role_key FROM app_roles')->fetchAll(),
            'role_key'
        ), 0);
        $rows = DatabaseModel::connection()->query(
            'SELECT role, COUNT(*) AS account_count FROM user_type GROUP BY role'
        )->fetchAll();
        foreach ($rows as $row) {
            $counts[$row['role']] = (int) $row['account_count'];
        }
        return $counts;
    }

    public static function find(int $id): ?array
    {
        DatabaseModel::requireColumns('user_type', [
            'id', 'full_name', 'work_email', 'role', 'hospital_name', 'phone_number',
            'hospital_id', 'barangay_id', 'last_login_at', 'created_at', 'banned_until',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'SELECT id, full_name AS name, work_email AS email, role, hospital_name, phone_number,
                    hospital_id, barangay_id, last_login_at, created_at, banned_until,
                    (banned_until IS NOT NULL AND banned_until > CURRENT_TIMESTAMP) AS is_login_disabled
             FROM user_type WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        DatabaseModel::requireTables(['app_roles']);
        DatabaseModel::requireColumns('user_type', [
            'full_name', 'work_email', 'phone_number', 'role', 'hospital_name', 'hospital_id',
            'barangay_id', 'password_hash', 'must_change_password', 'temporary_password_expires_at',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'INSERT INTO user_type
                (full_name, work_email, phone_number, role, hospital_name, hospital_id, barangay_id,
                 password_hash, must_change_password, temporary_password_expires_at)
             VALUES (:name, :email, :phone, :role, :hospital_name, :hospital_id, :barangay_id,
                 :password_hash, 1, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY))'
        );
        $stmt->execute($data);
        return (int) DatabaseModel::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        DatabaseModel::requireTables(['app_roles']);
        DatabaseModel::requireColumns('user_type', [
            'full_name', 'work_email', 'phone_number', 'role', 'hospital_name',
            'hospital_id', 'barangay_id', 'id',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type SET full_name = :name, work_email = :email, phone_number = :phone,
                role = :role, hospital_name = :hospital_name, hospital_id = :hospital_id,
                barangay_id = :barangay_id WHERE id = :id'
        );
        $stmt->execute($data + ['id' => $id]);
        if ($stmt->rowCount() === 0 && !self::find($id)) {
            throw new \RuntimeException('The selected account no longer exists.');
        }
    }

    public static function setTemporaryPassword(int $id, string $passwordHash): void
    {
        DatabaseModel::requireColumns('user_type', [
            'password_hash', 'must_change_password', 'temporary_password_expires_at',
            'password_changed_at', 'id',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type SET password_hash = ?, must_change_password = 1,
                temporary_password_expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY),
                password_changed_at = NULL WHERE id = ?'
        );
        $stmt->execute([$passwordHash, $id]);
        if ($stmt->rowCount() === 0 && !self::find($id)) {
            throw new \RuntimeException('The selected account no longer exists.');
        }
    }

    public static function setLoginDisabled(int $id, bool $disabled): void
    {
        DatabaseModel::requireColumns('user_type', ['banned_until', 'role', 'id']);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type SET banned_until = ? WHERE id = ? AND role <> \'super_admin\''
        );
        $stmt->execute([$disabled ? '9999-12-31 23:59:59' : null, $id]);
        $account = self::find($id);
        if (!$account) {
            throw new \RuntimeException('The selected account no longer exists.');
        }
        if ($account['role'] === 'super_admin') {
            throw new \RuntimeException('Super Admin logins cannot be disabled here.');
        }
        if ((bool) $account['is_login_disabled'] !== $disabled) {
            throw new \RuntimeException('The login status could not be updated.');
        }
    }

    public static function validRole(string $role): bool
    {
        DatabaseModel::requireTables(['app_roles']);
        $stmt = DatabaseModel::connection()->prepare('SELECT 1 FROM app_roles WHERE role_key = ?');
        $stmt->execute([$role]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hospitals(): array
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'name', 'is_active']);
        return DatabaseModel::connection()->query(
            'SELECT id, name FROM hospitals WHERE is_active = 1 ORDER BY name'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function barangays(?int $hospitalId = null): array
    {
        DatabaseModel::requireColumns('barangays', ['id', 'name', 'hospital_id', 'is_active']);
        if ($hospitalId !== null) {
            $stmt = DatabaseModel::connection()->prepare(
                'SELECT id, name FROM barangays WHERE is_active = 1 AND hospital_id = ? ORDER BY name'
            );
            $stmt->execute([$hospitalId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        return DatabaseModel::connection()->query(
            'SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function roleLabels(): array
    {
        DatabaseModel::requireTables(['app_roles']);
        return DatabaseModel::connection()->query(
            'SELECT role_key, display_name FROM app_roles ORDER BY display_name'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
