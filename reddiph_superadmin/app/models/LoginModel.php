<?php
namespace App\Models;

class LoginModel
{
    public static function findSuperAdminByEmail(string $email): ?array
    {
        $stmt = DatabaseModel::connection()->prepare(
            "SELECT id, full_name AS name, work_email AS email, password_hash
             FROM user_type WHERE work_email = ? AND role = 'super_admin' LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function recordSuperAdminLogin(int $id): void
    {
        $stmt = DatabaseModel::connection()->prepare(
            "UPDATE user_type SET last_login_at = CURRENT_TIMESTAMP
             WHERE id = ? AND role = 'super_admin'"
        );
        $stmt->execute([$id]);
    }
}
