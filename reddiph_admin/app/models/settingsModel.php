<?php
/**
 * ============================================================
 * settingsModel.php
 * ------------------------------------------------------------
 * REDDI PH / PULSE ALERT
 *
 * MODEL
 *
 * Responsibilities:
 * - Retrieve administrator information
 * - Retrieve hospital information
 * - Update account information
 * - Update hospital information
 *
 * No HTML.
 * No CSS.
 * No JavaScript.
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/PasswordPolicy.php';


class settingsModel
{
    private PDO $db;


    /**
     * --------------------------------------------------------
     * Constructor
     * --------------------------------------------------------
     */
    public function __construct()
    {
        $this->db = Database::connection();
    }


    /**
     * --------------------------------------------------------
     * Get administrator account
     * --------------------------------------------------------
     *
     */
    public function getAdmin(int $adminId): ?array
    {
        $sql = "
            SELECT
                u.id AS admin_id,
                u.hospital_id,
                u.full_name,
                u.role,
                u.work_email,
                u.phone_number,
                u.profile_image,
                COALESCE(h.name, u.hospital_name) AS hospital_name,
                h.address,
                h.latitude,
                h.longitude
            FROM user_type u
            LEFT JOIN hospitals h ON h.id = u.hospital_id
            WHERE u.id = :admin_id
                            AND u.role IN ('hospital_admin', 'doctor', 'nurse')
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':admin_id' => $adminId
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ?: null;
    }


    /**
     * --------------------------------------------------------
     * Get hospital profile
     * --------------------------------------------------------
     */
    public function getHospital(int $hospitalId): ?array
    {
        $sql = "
            SELECT
                id AS hospital_id,
                name AS hospital_name,
                address,
                latitude,
                longitude
            FROM hospitals
            WHERE id = :hospital_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':hospital_id' => $hospitalId
        ]);

        $hospital = $stmt->fetch(PDO::FETCH_ASSOC);

        return $hospital ?: null;
    }


    /**
     * --------------------------------------------------------
     * Update administrator account
     * --------------------------------------------------------
     */
    public function updateSettings(
        int $userId,
        string $email,
        ?string $phoneNumber,
        ?string $hospitalName,
        ?float $latitude,
        ?float $longitude
    ): bool {
        $this->db->beginTransaction();
        try {
            $profileQuery = $this->db->prepare("SELECT role, hospital_id FROM user_type
                WHERE id = :user_id AND role IN ('hospital_admin', 'doctor', 'nurse') FOR UPDATE");
            $profileQuery->execute([':user_id' => $userId]);
            $profile = $profileQuery->fetch(PDO::FETCH_ASSOC);
            if (!$profile) {
                throw new RuntimeException('Account could not be updated.');
            }

            $account = $this->db->prepare("UPDATE user_type
                SET work_email = :email, phone_number = :phone
                WHERE id = :user_id AND role IN ('hospital_admin', 'doctor', 'nurse')");
            $account->execute([
                ':email' => $email,
                ':phone' => $phoneNumber,
                ':user_id' => $userId,
            ]);

            if ($hospitalName !== null) {
                if ($profile['role'] !== 'hospital_admin' || empty($profile['hospital_id'])) {
                    throw new RuntimeException('Only hospital administrators can update hospital details.');
                }
                $hospitalId = (int) $profile['hospital_id'];
                $hospital = $this->db->prepare('UPDATE hospitals
                    SET name = :name, latitude = :latitude, longitude = :longitude
                    WHERE id = :hospital_id');
                $hospital->execute([
                    ':name' => $hospitalName,
                    ':latitude' => $latitude,
                    ':longitude' => $longitude,
                    ':hospital_id' => $hospitalId,
                ]);
                if ($hospital->rowCount() === 0) {
                    $check = $this->db->prepare('SELECT id FROM hospitals WHERE id = :hospital_id');
                    $check->execute([':hospital_id' => $hospitalId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Hospital could not be updated.');
                    }
                }

                $sync = $this->db->prepare('UPDATE user_type SET hospital_name = :name WHERE hospital_id = :hospital_id');
                $sync->execute([':name' => $hospitalName, ':hospital_id' => $hospitalId]);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function changePassword(int $adminId, string $currentPassword, string $newPassword): bool
    {
        if (PasswordPolicy::errors($newPassword) !== []) {
            return false;
        }
        $query = $this->db->prepare("SELECT password_hash FROM user_type
            WHERE id = :user_id AND role IN ('hospital_admin', 'doctor', 'nurse') LIMIT 1");
        $query->execute([':user_id' => $adminId]);
        $passwordHash = $query->fetchColumn();
        if (!$passwordHash || !password_verify($currentPassword, (string) $passwordHash)) {
            return false;
        }

        $update = $this->db->prepare("UPDATE user_type
            SET password_hash = :password_hash, password_changed_at = NOW(),
                must_change_password = 0, temporary_password_expires_at = NULL
            WHERE id = :user_id AND role IN ('hospital_admin', 'doctor', 'nurse')");
        $update->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':user_id' => $adminId,
        ]);
        return $update->rowCount() === 1;
    }

    public function updateProfileImage(int $adminId, string $fileName): ?string
    {
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare("SELECT profile_image FROM user_type
                WHERE id = :user_id AND role IN ('hospital_admin', 'doctor', 'nurse') FOR UPDATE");
            $query->execute([':user_id' => $adminId]);
            $previousImage = $query->fetchColumn();
            if ($previousImage === false) {
                throw new RuntimeException('Account could not be updated.');
            }

            $update = $this->db->prepare("UPDATE user_type SET profile_image = :image
                WHERE id = :user_id AND role IN ('hospital_admin', 'doctor', 'nurse')");
            $update->execute([':image' => $fileName, ':user_id' => $adminId]);
            $this->db->commit();
            return $previousImage !== null ? (string) $previousImage : null;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}