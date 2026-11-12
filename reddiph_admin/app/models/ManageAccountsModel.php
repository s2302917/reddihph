<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/AuditLogModel.php';

final class ManageAccountsModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getHospitalAdminContext(int $adminId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT hospital_id, hospital_name
             FROM user_type
             WHERE id = :admin_id AND role = 'hospital_admin'
             LIMIT 1"
        );
        $statement->execute([':admin_id' => $adminId]);
        $context = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($context) ? $context : null;
    }

    public function getAdminProfile(int $adminId): array
    {
        $statement = $this->db->prepare(
            "SELECT full_name, role, profile_image
             FROM user_type
             WHERE id = :admin_id AND role = 'hospital_admin'
             LIMIT 1"
        );
        $statement->execute([':admin_id' => $adminId]);
        $profile = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($profile) ? $profile : [];
    }

    public function getAccounts(int $hospitalId, array $filters): array
    {
        $conditions = ["u.hospital_id = :hospital_id", "u.role IN ('doctor', 'nurse')"];
        $parameters = [':hospital_id' => $hospitalId];

        if (($filters['role'] ?? 'all') !== 'all') {
            $conditions[] = 'u.role = :role';
            $parameters[':role'] = $filters['role'];
        }
        if (($filters['status'] ?? 'all') !== 'all') {
            $conditions[] = 'u.approval_status = :status';
            $parameters[':status'] = $filters['status'];
        }
        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(u.full_name LIKE :search_name OR u.work_email LIKE :search_email)';
            $term = '%' . $filters['search'] . '%';
            $parameters[':search_name'] = $term;
            $parameters[':search_email'] = $term;
        }

        $where = implode(' AND ', $conditions);
        $countStatement = $this->db->prepare(
            "SELECT COUNT(*) FROM user_type u WHERE {$where}"
        );
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();

        $pageSize = 15;
        $totalPages = max(1, (int) ceil($total / $pageSize));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $totalPages);
        $offset = ($page - 1) * $pageSize;

        $statement = $this->db->prepare(
            "SELECT u.id, u.full_name, u.role, u.work_email,
                u.approval_status AS status,
                (
                    SELECT s.department
                    FROM staff s
                    WHERE s.user_id = u.id
                      AND s.hospital_id = u.hospital_id
                    ORDER BY s.id DESC
                    LIMIT 1
                ) AS specialty
         FROM user_type u
         WHERE {$where}
         ORDER BY u.created_at DESC, u.id DESC
             LIMIT {$pageSize} OFFSET {$offset}"
        );
        $statement->execute($parameters);
        $accounts = $statement->fetchAll(PDO::FETCH_ASSOC);

        return [
            'accounts' => $accounts,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => $totalPages,
        ];
    }

    public function createAccount(int $adminId, int $hospitalId, array $data): array
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $role = strtolower(trim((string) ($data['role'] ?? '')));
        $specialty = trim((string) ($data['specialty'] ?? ''));
        $email = strtolower(trim((string) ($data['work_email'] ?? '')));
        $license = trim((string) ($data['license_number'] ?? ''));

        $errors = [];
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Enter a full name with no more than 150 characters.';
        }
        if (!in_array($role, ['doctor', 'nurse'], true)) {
            $errors['role'] = 'Choose Doctor or Nurse.';
        }
        if ($specialty === '' || mb_strlen($specialty) > 120) {
            $errors['specialty'] = 'Enter a specialty or department with no more than 120 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errors['work_email'] = 'Enter a valid work email address.';
        }
        if ($role === 'doctor' && $license === '') {
            $errors['license_number'] = 'A license number is required for doctors.';
        } elseif (mb_strlen($license) > 50) {
            $errors['license_number'] = 'The license number must be no more than 50 characters.';
        }
        if ($errors !== []) {
            return ['success' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors];
        }

        $temporaryPassword = LoginModel::generateTemporaryPassword();
        $this->db->beginTransaction();
        try {
            $hospitalStatement = $this->db->prepare(
                'SELECT name FROM hospitals WHERE id = :hospital_id LIMIT 1 FOR UPDATE'
            );
            $hospitalStatement->execute([':hospital_id' => $hospitalId]);
            $authorizedHospitalName = $hospitalStatement->fetchColumn();
            if ($authorizedHospitalName === false) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 403, 'message' => 'The authorized hospital is unavailable.'];
            }

            $existingAccount = $this->db->prepare(
                'SELECT
                    EXISTS(SELECT 1 FROM user_type WHERE work_email = :email) AS duplicate_email,
                    EXISTS(SELECT 1 FROM user_type WHERE license_number = :license_number) AS duplicate_license'
            );
            $existingAccount->execute([
                ':email' => $email,
                ':license_number' => $license !== '' ? $license : null,
            ]);
            $duplicates = $existingAccount->fetch(PDO::FETCH_ASSOC);
            $duplicateErrors = [];
            if ((int) ($duplicates['duplicate_email'] ?? 0) === 1) {
                $duplicateErrors['work_email'] = 'This email address is already assigned to an account.';
            }
            if ((int) ($duplicates['duplicate_license'] ?? 0) === 1) {
                $duplicateErrors['license_number'] = 'This license number is already assigned to an account.';
            }
            if ($duplicateErrors !== []) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'An account already uses this email or license number.',
                    'errors' => $duplicateErrors,
                ];
            }

            $insert = $this->db->prepare(
                'INSERT INTO user_type
                    (role, full_name, hospital_name, hospital_id, work_email,
                     license_number, password_hash, must_change_password,
                     temporary_password_expires_at)
                 VALUES
                    (:role, :full_name, :hospital_name, :hospital_id, :work_email,
                     :license_number, :password_hash, 1, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
            );
            $insert->execute([
                ':role' => $role,
                ':full_name' => $fullName,
                ':hospital_name' => (string) $authorizedHospitalName,
                ':hospital_id' => $hospitalId,
                ':work_email' => $email,
                ':license_number' => $license !== '' ? $license : null,
                ':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
            ]);
            $accountId = (int) $this->db->lastInsertId();

            $staffInsert = $this->db->prepare(
                'INSERT INTO staff (hospital_id, user_id, full_name, department)
                 VALUES (:hospital_id, :user_id, :full_name, :department)'
            );
            $staffInsert->execute([
                ':hospital_id' => $hospitalId,
                ':user_id' => $accountId,
                ':full_name' => $fullName,
                ':department' => $specialty,
            ]);

            AuditLogModel::record($this->db, $adminId, 'signup.created', 'user', (string) $accountId, [
                'role' => $role,
                'hospital_id' => $hospitalId,
                'source' => 'hospital_admin_manage_accounts',
            ]);
            $this->db->commit();

            $emailSent = false;
            try {
                $emailSent = sendTemporaryPasswordEmail($email, $fullName, $temporaryPassword);
            } catch (Throwable $emailError) {
                error_log('[ManageAccountsModel] Account created but temporary sign-in email failed: ' . $emailError->getMessage());
            }

            if (!$emailSent) {
                return [
                    'success' => true,
                    'emailSent' => false,
                    'message' => 'The account was created, but the temporary sign-in email could not be sent. Check the email configuration, then use Reset Password to send new sign-in details.',
                ];
            }

            return [
                'success' => true,
                'emailSent' => true,
                'message' => 'Account created. Temporary sign-in details were sent to the work email.',
            ];
        } catch (PDOException $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($error->getCode() === '23000' && (int) ($error->errorInfo[1] ?? 0) === 1062) {
                $databaseMessage = (string) ($error->errorInfo[2] ?? '');
                $duplicateField = str_contains($databaseMessage, 'uq_user_type_license_number')
                    ? 'license_number'
                    : 'work_email';
                return [
                    'success' => false,
                    'message' => 'An account already uses this email or license number.',
                    'errors' => [
                        $duplicateField => $duplicateField === 'license_number'
                            ? 'This license number is already assigned to an account.'
                            : 'This email address is already assigned to an account.',
                    ],
                ];
            }
            throw $error;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function resetPassword(int $adminId, int $hospitalId, int $accountId): array
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "SELECT id, full_name, work_email, reset_requested_at
                 FROM user_type
                 WHERE id = :account_id
                   AND hospital_id = :hospital_id
                   AND role IN ('doctor', 'nurse')
                 LIMIT 1 FOR UPDATE"
            );
            $statement->execute([
                ':account_id' => $accountId,
                ':hospital_id' => $hospitalId,
            ]);
            $account = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 404, 'message' => 'That staff account was not found in your hospital.'];
            }
            if (
                !empty($account['reset_requested_at']) &&
                strtotime((string) $account['reset_requested_at']) > time() - 60
            ) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 429, 'message' => 'A password reset was requested recently. Please wait a minute and try again.'];
            }

            $temporaryPassword = LoginModel::generateTemporaryPassword();
            $update = $this->db->prepare(
                'UPDATE user_type
                 SET password_hash = :password_hash,
                     must_change_password = 1,
                     temporary_password_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                     reset_requested_at = NOW()
                 WHERE id = :account_id AND hospital_id = :hospital_id'
            );
            $update->execute([
                ':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                ':account_id' => $accountId,
                ':hospital_id' => $hospitalId,
            ]);

            if (!sendTemporaryPasswordEmail(
                (string) $account['work_email'],
                (string) $account['full_name'],
                $temporaryPassword
            )) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'status' => 502,
                    'message' => 'The password reset could not be completed because the email could not be sent. Please try again later.',
                ];
            }

            AuditLogModel::record($this->db, $adminId, 'user.password_reset', 'user', (string) $accountId, [
                'hospital_id' => $hospitalId,
                'source' => 'hospital_admin_manage_accounts',
            ]);
            $this->db->commit();

            return ['success' => true, 'message' => 'Temporary sign-in details were sent to the account work email.'];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
