<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\UsersModel;
use App\Models\AuditLogsModel;

class UsersController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        if (
            $_SERVER['REQUEST_METHOD'] === 'GET' &&
            ($_GET['action'] ?? '') === 'barangays'
        ) {
            $this->barangays();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            DashboardController::runPostAction('/users', function (): string {
                $action = DashboardController::postString('action');
                $actorId = (int) $_SESSION['user']['id'];

                if ($action === 'archive_account') {
                    $id = filter_var(DashboardController::postString('user_id'), FILTER_VALIDATE_INT);
                    if (!$id || $id === $actorId) {
                        throw new \RuntimeException('You cannot archive your own account.');
                    }
                    $reason = trim(DashboardController::postString('reason'));
                    $account = UsersModel::find($id);
                    UsersModel::archive($id, $reason);
                    AuditLogsModel::record(
                        $actorId,
                        'user.account_archived',
                        'user',
                        (string) $id,
                        [
                            'display_name' => $account['name'] ?? '',
                            'role' => $account['role'] ?? '',
                            'reason' => $reason,
                        ]
                    );
                    return 'Account archived. Its history has been preserved.';
                }

                if ($action === 'restore_account') {
                    $id = filter_var(DashboardController::postString('user_id'), FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid archived account.');
                    }
                    $account = UsersModel::find($id, true);
                    UsersModel::restore($id);
                    AuditLogsModel::record($actorId, 'user.account_restored', 'user', (string) $id, [
                        'display_name' => $account['name'] ?? '',
                        'role' => $account['role'] ?? '',
                    ]);
                    return 'Account restored. Its prior login status has been preserved.';
                }

                if ($action === 'disable_login' || $action === 'enable_login') {
                    $id = filter_var(DashboardController::postString('user_id'), FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid account.');
                    }
                    if ($id === $actorId) {
                        throw new \RuntimeException('You cannot disable or enable your own login.');
                    }
                    $disabled = $action === 'disable_login';
                    $reason = $disabled ? trim(DashboardController::postString('reason')) : null;
                    if ($disabled && ($reason === '' || strlen($reason) > 500)) {
                        throw new \InvalidArgumentException('Enter a reason of up to 500 characters before disabling login.');
                    }
                    $account = UsersModel::find($id);
                    UsersModel::setLoginDisabled($id, $disabled, $reason);
                    AuditLogsModel::record(
                        $actorId,
                        $disabled ? 'user.login_disabled' : 'user.login_enabled',
                        'user',
                        (string) $id,
                        [
                            'display_name' => $account['name'] ?? '',
                            'reason' => $disabled ? $reason : '',
                        ]
                    );
                    return $disabled
                        ? 'Login disabled. The user will see the reason when they try to sign in.'
                        : 'Login enabled. The user can sign in again.';
                }

                if ($action !== 'create' && $action !== 'update' && $action !== 'reset_password') {
                    throw new \InvalidArgumentException('Choose a valid user action.');
                }

                if ($action === 'reset_password') {
                    $id = filter_var(DashboardController::postString('user_id'), FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid account.');
                    }
                    $account = UsersModel::find($id);
                    $temporaryPassword = bin2hex(random_bytes(8));
                    UsersModel::setTemporaryPassword($id, password_hash($temporaryPassword, PASSWORD_DEFAULT));
                    AuditLogsModel::record($actorId, 'user.password_reset', 'user', (string) $id, [
                        'display_name' => $account['name'] ?? '',
                    ]);
                    return 'Temporary password: ' . $temporaryPassword . ' — share it securely; it expires in 24 hours.';
                }

                $name = trim(DashboardController::postString('name'));
                $email = trim(DashboardController::postString('email'));
                $phone = trim(DashboardController::postString('phone'));
                $role = trim(DashboardController::postString('role'));
                if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                    throw new \InvalidArgumentException('Enter a name (up to 150 characters) and a valid email address.');
                }
                if (strlen($phone) > 32) {
                    throw new \InvalidArgumentException('Phone numbers must be 32 characters or fewer.');
                }
                if (!UsersModel::validRole($role)) {
                    throw new \InvalidArgumentException('Select a valid account role.');
                }
                $id = null;
                $existing = null;
                if ($action === 'update') {
                    $id = filter_var(DashboardController::postString('user_id'), FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid account to update.');
                    }
                    if ($id === $actorId && $role !== 'super_admin') {
                        throw new \RuntimeException('You cannot change your own Super Admin role.');
                    }
                    $existing = UsersModel::find($id);
                    if (!$existing) {
                        throw new \RuntimeException('The selected account no longer exists.');
                    }
                }
                $hospitalId = self::optionalId('hospital_id');
                $barangayId = self::optionalId('barangay_id');
                $hospitals = UsersModel::hospitals();
                if ($barangayId !== null && $hospitalId === null) {
                    throw new \InvalidArgumentException('Select the hospital assigned to the chosen barangay.');
                }
                $barangays = UsersModel::barangays($hospitalId);
                $keepCurrentHospital = $existing !== null
                    && $hospitalId !== null
                    && $hospitalId === (int) ($existing['hospital_id'] ?? 0)
                    && !array_key_exists($hospitalId, $hospitals);
                if ($hospitalId !== null
                    && !array_key_exists($hospitalId, $hospitals)
                    && !$keepCurrentHospital) {
                    throw new \InvalidArgumentException('Select an active hospital.');
                }
                $keepCurrentBarangay = $existing !== null
                    && $barangayId !== null
                    && $barangayId === (int) ($existing['barangay_id'] ?? 0)
                    && $hospitalId === (int) ($existing['hospital_id'] ?? 0)
                    && !array_key_exists($barangayId, $barangays);
                if ($barangayId !== null
                    && !array_key_exists($barangayId, $barangays)
                    && !$keepCurrentBarangay) {
                    throw new \InvalidArgumentException('Select a barangay assigned to the selected hospital.');
                }
                if ($role === 'barangay_admin' && $barangayId === null) {
                    throw new \InvalidArgumentException('Choose a barangay for a Barangay Admin account.');
                }
                $data = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => $role,
                    'hospital_name' => $hospitalId === null
                        ? 'Not assigned'
                        : ($hospitals[$hospitalId] ?? (string) ($existing['hospital_name'] ?? 'Not assigned')),
                    'hospital_id' => $hospitalId,
                    'barangay_id' => $barangayId,
                ];

                if ($action === 'create') {
                    $temporaryPassword = bin2hex(random_bytes(8));
                    $data['password_hash'] = password_hash($temporaryPassword, PASSWORD_DEFAULT);
                    $id = UsersModel::create($data);
                    AuditLogsModel::record($actorId, 'user.created', 'user', (string) $id, [
                        'display_name' => $name,
                        'role' => $role,
                        'hospital_name' => $data['hospital_name'],
                    ]);
                    return 'Account created. Temporary password: ' . $temporaryPassword . ' — share it securely; it expires in 24 hours.';
                }

                $fieldMap = [
                    'name' => 'name',
                    'email' => 'email',
                    'phone' => 'phone_number',
                    'role' => 'role',
                    'hospital_name' => 'hospital_name',
                    'hospital_id' => 'hospital_id',
                    'barangay_id' => 'barangay_id',
                ];
                $changedFields = [];
                foreach ($fieldMap as $dataKey => $accountKey) {
                    if ((string) ($existing[$accountKey] ?? '') !== (string) ($data[$dataKey] ?? '')) {
                        $changedFields[] = match ($dataKey) {
                            'name' => 'name',
                            'email' => 'email',
                            'phone' => 'phone number',
                            'hospital_name' => 'hospital',
                            'hospital_id' => 'hospital assignment',
                            'barangay_id' => 'barangay assignment',
                            default => $dataKey,
                        };
                    }
                }
                UsersModel::update($id, $data);
                AuditLogsModel::record($actorId, 'user.updated', 'user', (string) $id, [
                    'display_name' => $name,
                    'changed_fields' => $changedFields,
                ]);
                return 'Account details updated.';
            });
        }

        try {
            $status = $_GET['status'] ?? 'active';
            $includeArchived = is_string($status) && $status === 'archived';
            $status = $includeArchived ? 'archived' : 'active';
            $roleCounts = UsersModel::roleCounts($includeArchived);
            $role = $_GET['role'] ?? '';
            if (!is_string($role) || !array_key_exists($role, $roleCounts)) {
                $role = '';
            }

            $search = $_GET['q'] ?? '';
            $search = is_string($search) ? trim($search) : '';
            $search = substr($search, 0, 100);
            $totalUsers = UsersModel::count($role !== '' ? $role : null, $search, $includeArchived);
            $pageSize = 15;
            $totalPages = max(1, (int) ceil($totalUsers / $pageSize));
            $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $currentPage = max(1, min($totalPages, is_int($requestedPage) ? $requestedPage : 1));
            $users = UsersModel::all(
                $role !== '' ? $role : null,
                $pageSize,
                ($currentPage - 1) * $pageSize,
                $search,
                $includeArchived
            );
            $selectedId = filter_var($_GET['account'] ?? null, FILTER_VALIDATE_INT);
            $selectedUser = $selectedId
                ? UsersModel::find($selectedId, $includeArchived)
                : ($users[0] ?? null);
            if (
                $selectedUser &&
                (
                    ($role !== '' && $selectedUser['role'] !== $role) ||
                    ($search !== '' && stripos(
                        implode(' ', [
                            $selectedUser['name'],
                            $selectedUser['email'],
                            $selectedUser['role'],
                            $selectedUser['hospital_name'],
                        ]),
                        $search
                    ) === false)
                )
            ) {
                $selectedUser = $users[0] ?? null;
            }

            $data = [
                'users' => $users,
                'roleCounts' => $roleCounts,
                'roleLabels' => UsersModel::roleLabels(),
                'hospitals' => UsersModel::hospitals(),
                'barangays' => UsersModel::barangays(),
                'selectedRole' => $role,
                'selectedUser' => $selectedUser,
                'searchQuery' => $search,
                'selectedStatus' => $status,
                'totalUsers' => $totalUsers,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'pageSize' => $pageSize,
                'activeAccountCount' => UsersModel::count(null, '', false),
                'archivedAccountCount' => UsersModel::count(null, '', true),
            ];
        } catch (\PDOException | \RuntimeException $exception) {
            error_log('Super Admin users page could not load: ' . $exception->getMessage());
            $missingReasonColumn = str_contains($exception->getMessage(), 'login_disabled_reason');
            $data = [
                'pageError' => 'User data could not be loaded. Check the shared database connection and verify that the backup schema is available.',
            ];
            if ($missingReasonColumn) {
                $data['pageError'] = 'The database is missing the login-disabled-reason column. Import config/migrations/20261023_login_disabled_reason.sql, then reload this page.';
            } elseif (str_contains($exception->getMessage(), 'account_archived_at')) {
                $data['pageError'] = 'The database is missing the account archive columns. Import config/migrations/20261024_user_account_archive.sql, then reload this page.';
            }
        }

        DashboardController::renderDashboard('users', $data);
    }

    private function barangays(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $hospitalId = filter_var($_GET['hospital_id'] ?? null, FILTER_VALIDATE_INT);
        if ($hospitalId === false || $hospitalId === null || $hospitalId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Select a valid hospital.',
            ], 422);
        }

        try {
            $hospitals = UsersModel::hospitals();
            if (!array_key_exists($hospitalId, $hospitals)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'The selected hospital is unavailable.',
                ], 404);
            }

            $barangays = UsersModel::barangays($hospitalId);
            $items = [];
            foreach ($barangays as $id => $name) {
                $items[] = ['id' => (int) $id, 'name' => (string) $name];
            }

            $this->jsonResponse([
                'success' => true,
                'barangays' => $items,
            ]);
        } catch (\Throwable $exception) {
            error_log('Super Admin barangays could not be loaded: ' . $exception->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Barangays could not be loaded. Please try again.',
            ], 500);
        }
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    private static function optionalId(string $key): ?int
    {
        $value = trim(DashboardController::postString($key));
        if ($value === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            throw new \InvalidArgumentException('Choose a valid facility.');
        }
        return $id;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new UsersController())->index();
}
