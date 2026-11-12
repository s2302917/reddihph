<?php
namespace App\Models;

use PDO;

class UsersModel
{
    private static function requireAccountArchiveSchema(): void
    {
        DatabaseModel::requireColumns('user_type', [
            'account_archived_at',
            'account_archived_reason',
            'account_archived_previous_banned_until',
            'account_archived_previous_login_reason',
        ], 'Import config/migrations/20261024_user_account_archive.sql into the shared database to enable account archiving.');
    }

    public static function all(
        ?string $role = null,
        int $limit = 15,
        int $offset = 0,
        string $search = '',
        bool $includeArchived = false
    ): array
    {
        self::requireAccountArchiveSchema();
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new \InvalidArgumentException('Choose a valid user page.');
        }
        [$where, $params] = self::filters($role, $search, $includeArchived);
        $stmt = DatabaseModel::connection()->prepare(
            'SELECT id, full_name AS name, work_email AS email, role, hospital_name, phone_number,
                    hospital_id, barangay_id, last_login_at, created_at, banned_until,
                    login_disabled_reason, account_archived_at, account_archived_reason,
                    (banned_until IS NOT NULL AND banned_until > CURRENT_TIMESTAMP) AS is_login_disabled
             FROM user_type' . $where . '
             ORDER BY created_at DESC, id DESC
             LIMIT ? OFFSET ?'
        );
        foreach ($params as $index => $param) {
            $stmt->bindValue($index + 1, $param, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function count(
        ?string $role = null,
        string $search = '',
        bool $includeArchived = false
    ): int
    {
        self::requireAccountArchiveSchema();
        [$where, $params] = self::filters($role, $search, $includeArchived);
        $stmt = DatabaseModel::connection()->prepare('SELECT COUNT(*) FROM user_type' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function roleCounts(bool $includeArchived = false): array
    {
        self::requireAccountArchiveSchema();
        $archiveCondition = $includeArchived
            ? 'account_archived_at IS NOT NULL'
            : 'account_archived_at IS NULL';
        $rows = DatabaseModel::connection()->query(
            "SELECT role, COUNT(*) AS account_count FROM user_type WHERE $archiveCondition GROUP BY role"
        )->fetchAll();
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['role']] = (int) $row['account_count'];
        }
        return $counts;
    }

    public static function find(int $id, bool $includeArchived = false): ?array
    {
        self::requireAccountArchiveSchema();
        $archiveCondition = $includeArchived
            ? 'account_archived_at IS NOT NULL'
            : 'account_archived_at IS NULL';
        DatabaseModel::requireColumns('user_type', [
            'id', 'full_name', 'work_email', 'role', 'hospital_name', 'phone_number',
            'hospital_id', 'barangay_id', 'last_login_at', 'created_at', 'banned_until',
            'login_disabled_reason',
        ], 'Import config/migrations/20261023_login_disabled_reason.sql into the shared database to enable login-disable reasons.');
        $stmt = DatabaseModel::connection()->prepare(
            'SELECT id, full_name AS name, work_email AS email, role, hospital_name, phone_number,
                    hospital_id, barangay_id, last_login_at, created_at, banned_until,
                    login_disabled_reason, account_archived_at, account_archived_reason,
                    (banned_until IS NOT NULL AND banned_until > CURRENT_TIMESTAMP) AS is_login_disabled
             FROM user_type WHERE id = ? AND ' . $archiveCondition . ' LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function archive(int $id, string $reason): void
    {
        self::requireAccountArchiveSchema();
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 500) {
            throw new \InvalidArgumentException('Enter an archive reason of up to 500 characters.');
        }
        $account = self::find($id);
        if (!$account) {
            throw new \RuntimeException('The selected account is no longer available.');
        }
        if ($account['role'] === 'super_admin') {
            throw new \RuntimeException('Super Admin accounts cannot be archived here.');
        }

        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type
             SET account_archived_at = CURRENT_TIMESTAMP,
                 account_archived_reason = ?,
                 account_archived_previous_banned_until = banned_until,
                 account_archived_previous_login_reason = login_disabled_reason,
                 banned_until = \'9999-12-31 23:59:59\',
                 login_disabled_reason = ?
             WHERE id = ? AND account_archived_at IS NULL AND role <> \'super_admin\''
        );
        $stmt->execute([$reason, $reason, $id]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('The account could not be archived. Reload the page and try again.');
        }
    }

    public static function restore(int $id): void
    {
        self::requireAccountArchiveSchema();
        $account = self::find($id, true);
        if (!$account) {
            throw new \RuntimeException('The archived account is no longer available.');
        }
        if ($account['role'] === 'super_admin') {
            throw new \RuntimeException('Super Admin accounts cannot be restored here.');
        }

        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type
             SET banned_until = account_archived_previous_banned_until,
                 login_disabled_reason = account_archived_previous_login_reason,
                 account_archived_at = NULL,
                 account_archived_reason = NULL,
                 account_archived_previous_banned_until = NULL,
                 account_archived_previous_login_reason = NULL
             WHERE id = ? AND account_archived_at IS NOT NULL AND role <> \'super_admin\''
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('The account could not be restored. Reload the page and try again.');
        }
    }

    private static function filters(
        ?string $role,
        string $search,
        bool $includeArchived
    ): array {
        $conditions = [
            $includeArchived ? 'account_archived_at IS NOT NULL' : 'account_archived_at IS NULL',
        ];
        $params = [];
        if ($role !== null) {
            $conditions[] = 'role = ?';
            $params[] = $role;
        }
        if ($search !== '') {
            $conditions[] = '(full_name LIKE ? OR work_email LIKE ? OR role LIKE ? OR hospital_name LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term);
        }
        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function create(array $data): int
    {
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
        self::requireAccountArchiveSchema();
        DatabaseModel::requireColumns('user_type', [
            'full_name', 'work_email', 'phone_number', 'role', 'hospital_name',
            'hospital_id', 'barangay_id', 'id',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type SET full_name = :name, work_email = :email, phone_number = :phone,
                role = :role, hospital_name = :hospital_name, hospital_id = :hospital_id,
                barangay_id = :barangay_id WHERE id = :id AND account_archived_at IS NULL'
        );
        $stmt->execute($data + ['id' => $id]);
        if ($stmt->rowCount() === 0 && !self::find($id)) {
            throw new \RuntimeException('The selected account no longer exists.');
        }
    }

    public static function setTemporaryPassword(int $id, string $passwordHash): void
    {
        self::requireAccountArchiveSchema();
        DatabaseModel::requireColumns('user_type', [
            'password_hash', 'must_change_password', 'temporary_password_expires_at',
            'password_changed_at', 'id',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type SET password_hash = ?, must_change_password = 1,
                temporary_password_expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY),
                password_changed_at = NULL WHERE id = ? AND account_archived_at IS NULL'
        );
        $stmt->execute([$passwordHash, $id]);
        if ($stmt->rowCount() === 0 && !self::find($id)) {
            throw new \RuntimeException('The selected account no longer exists.');
        }
    }

    public static function setLoginDisabled(int $id, bool $disabled, ?string $reason = null): void
    {
        self::requireAccountArchiveSchema();
        DatabaseModel::requireColumns('user_type', [
            'banned_until', 'login_disabled_reason', 'role', 'id',
        ], 'Import config/migrations/20261023_login_disabled_reason.sql into the shared database to enable login-disable reasons.');
        if ($disabled) {
            $reason = trim((string) $reason);
            if ($reason === '' || strlen($reason) > 500) {
                throw new \InvalidArgumentException('Enter a reason of up to 500 characters before disabling login.');
            }
        } else {
            $reason = null;
        }
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE user_type SET banned_until = ?, login_disabled_reason = ?
             WHERE id = ? AND role <> \'super_admin\' AND account_archived_at IS NULL'
        );
        $stmt->execute([
            $disabled ? '9999-12-31 23:59:59' : null,
            $reason,
            $id,
        ]);
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
        $stmt = DatabaseModel::connection()->prepare('SELECT 1 FROM user_type WHERE role = ? LIMIT 1');
        $stmt->execute([$role]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hospitals(): array
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'name']);
        $conditions = [];
        if (DatabaseModel::hasColumn('hospitals', 'is_active')) {
            $conditions[] = 'is_active = 1';
        }
        if (DatabaseModel::hasColumn('hospitals', 'archived_at')) {
            $conditions[] = 'archived_at IS NULL';
        }
        return DatabaseModel::connection()->query(
            'SELECT id, name FROM hospitals'
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . ' ORDER BY name'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function barangays(?int $hospitalId = null): array
    {
        DatabaseModel::requireColumns('barangays', ['id', 'name', 'hospital_id']);
        DatabaseModel::requireColumns('hospitals', ['id']);
        $conditions = [];
        if (DatabaseModel::hasColumn('barangays', 'is_active')) {
            $conditions[] = 'b.is_active = 1';
        }
        if (DatabaseModel::hasColumn('barangays', 'archived_at')) {
            $conditions[] = 'b.archived_at IS NULL';
        }
        if (DatabaseModel::hasColumn('hospitals', 'is_active')) {
            $conditions[] = 'h.is_active = 1';
        }
        if (DatabaseModel::hasColumn('hospitals', 'archived_at')) {
            $conditions[] = 'h.archived_at IS NULL';
        }
        if ($hospitalId !== null) {
            $conditions[] = 'b.hospital_id = ?';
            $stmt = DatabaseModel::connection()->prepare(
                'SELECT b.id, b.name FROM barangays b JOIN hospitals h ON h.id = b.hospital_id'
                . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY b.name'
            );
            $stmt->execute([$hospitalId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        return DatabaseModel::connection()->query(
            'SELECT b.id, b.name FROM barangays b JOIN hospitals h ON h.id = b.hospital_id'
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . ' ORDER BY b.name'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function roleLabels(): array
    {
        $roles = DatabaseModel::connection()->query(
            'SELECT DISTINCT role FROM user_type ORDER BY role'
        )->fetchAll(PDO::FETCH_COLUMN);
        $labels = [];
        foreach ($roles as $role) {
            $labels[$role] = ucwords(str_replace('_', ' ', $role));
        }
        return $labels;
    }
}
