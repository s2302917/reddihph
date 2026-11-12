<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/PasswordPolicy.php';
require_once __DIR__ . '/AuditLogModel.php';

class SignupModel
{
    private static array $columnCache = [];

    public static function getHospitals(): array
    {
        $pdo = Database::connection();
        $conditions = self::activeHospitalConditions($pdo);
        $stmt = $pdo->query(
            'SELECT id, name FROM hospitals'
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . ' ORDER BY name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function hospitalExists(string $hospitalName): bool
    {
        $pdo = Database::connection();
        $conditions = array_merge(
            ['name = :hospital_name'],
            self::activeHospitalConditions($pdo)
        );
        $stmt = $pdo->prepare(
            'SELECT 1 FROM hospitals WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1'
        );
        $stmt->execute([':hospital_name' => $hospitalName]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Validate signup form.
     */
    public static function validateRegistration(
        array $data,
        string $role
    ): array {

        $errors = [];

        $fullName = trim($data['full_name'] ?? '');
        $hospitalName = trim($data['hospital_name'] ?? '');
        $workEmail = trim($data['work_email'] ?? '');
        $licenseNumber = trim($data['license_number'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        $allowedRoles = [
            'hospital_admin',
            'doctor',
            'nurse'
        ];

        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Invalid account type.';
        }

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }

        if ($hospitalName === '' || !self::hospitalExists($hospitalName)) {
            $errors[] = 'Please select a valid hospital.';
        }

        if ($workEmail === '') {

            $errors[] = 'Work email is required.';

        } elseif (!filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {

            $errors[] = 'Please enter a valid work email address.';
        }

        // Doctor requires a license number
        if ($role === 'doctor' && $licenseNumber === '') {
            $errors[] = 'License number is required.';
        }

        $errors = array_merge($errors, PasswordPolicy::errors($password));

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }


    /**
     * Check if email already exists.
     */
    private static function emailExists(
        PDO $pdo,
        string $email
    ): bool {

        $sql = "
            SELECT id
            FROM user_type
            WHERE work_email = :email
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':email' => $email
        ]);

        return (bool) $stmt->fetch();
    }


    /**
     * Check if doctor license already exists.
     */
    private static function licenseExists(
        PDO $pdo,
        string $licenseNumber
    ): bool {

        $sql = "
            SELECT id
            FROM user_type
            WHERE license_number = :license_number
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':license_number' => $licenseNumber
        ]);

        return (bool) $stmt->fetch();
    }


    /**
     * Find a canonical hospital selected during signup.
     */
    private static function getHospitalId(
        PDO $pdo,
        string $hospitalName
    ): int {
        $conditions = array_merge(
            ['name = :hospital_name'],
            self::activeHospitalConditions($pdo)
        );
        $stmt = $pdo->prepare(
            'SELECT id FROM hospitals WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1'
        );

        $stmt->execute([
            ':hospital_name' => $hospitalName
        ]);

        $hospital = $stmt->fetch();

        if ($hospital) {
            return (int) $hospital['id'];
        }

        throw new RuntimeException('The selected hospital is not configured.');
    }

    private static function activeHospitalConditions(PDO $pdo): array
    {
        $conditions = [];
        if (self::hasColumn($pdo, 'hospitals', 'is_active')) {
            $conditions[] = 'is_active = 1';
        }
        if (self::hasColumn($pdo, 'hospitals', 'archived_at')) {
            $conditions[] = 'archived_at IS NULL';
        }
        return $conditions;
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (!array_key_exists($cacheKey, self::$columnCache)) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
            );
            $stmt->execute([$table, $column]);
            self::$columnCache[$cacheKey] = (int) $stmt->fetchColumn() > 0;
        }
        return self::$columnCache[$cacheKey];
    }


    /**
     * Create user account.
     */
    public static function createAccount(
        array $data,
        string $role
    ): array {

        $allowedRoles = [
            'hospital_admin',
            'doctor',
            'nurse'
        ];

        if (!in_array($role, $allowedRoles, true)) {

            return [
                'success' => false,
                'message' => 'Invalid account role.'
            ];
        }

        $fullName = trim($data['full_name'] ?? '');
        $hospitalName = trim($data['hospital_name'] ?? '');
        $workEmail = strtolower(trim($data['work_email'] ?? ''));
        $licenseNumber = trim($data['license_number'] ?? '');
        $password = $data['password'] ?? '';

        try {

            $pdo = Database::connection();

            /*
             * Start transaction.
             *
             * If hospital creation succeeds but user creation fails,
             * everything will be rolled back.
             */
            $pdo->beginTransaction();


            // --------------------------------
            // Check duplicate email
            // --------------------------------

            if (self::emailExists($pdo, $workEmail)) {

                $pdo->rollBack();

                return [
                    'success' => false,
                    'message' => 'That work email is already registered.'
                ];
            }


            // --------------------------------
            // Doctor license duplicate check
            // --------------------------------

            if (
                $role === 'doctor' &&
                self::licenseExists($pdo, $licenseNumber)
            ) {

                $pdo->rollBack();

                return [
                    'success' => false,
                    'message' => 'That doctor license number is already registered.'
                ];
            }


            // --------------------------------
            // Hospital
            // --------------------------------

            $hospitalId = self::getHospitalId(
                $pdo,
                $hospitalName
            );


            // --------------------------------
            // Password hashing
            // --------------------------------

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // --------------------------------
            // Insert user
            // --------------------------------

            $sql = "
                INSERT INTO user_type (
                    role,
                    full_name,
                    hospital_name,
                    hospital_id,
                    work_email,
                    license_number,
                    password_hash
                )
                VALUES (
                    :role,
                    :full_name,
                    :hospital_name,
                    :hospital_id,
                    :work_email,
                    :license_number,
                    :password_hash
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':role' => $role,
                ':full_name' => $fullName,
                ':hospital_name' => $hospitalName,
                ':hospital_id' => $hospitalId,
                ':work_email' => $workEmail,

                // Nurses and admins don't need license numbers.
                ':license_number' =>
                    $role === 'doctor'
                    ? $licenseNumber
                    : null,

                ':password_hash' => $passwordHash
            ]);

            $userId = (int) $pdo->lastInsertId();

            AuditLogModel::record($pdo, null, 'signup.created', 'user', (string) $userId, [
                'role' => $role,
            ]);
            $pdo->commit();

            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Account created successfully. You can now log in.'
            ];

        } catch (Throwable $e) {

            if (
                isset($pdo) &&
                $pdo instanceof PDO &&
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            error_log(
                'Signup error: ' .
                $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Unable to create the account right now. Please try again later.'
            ];
        }
    }
}