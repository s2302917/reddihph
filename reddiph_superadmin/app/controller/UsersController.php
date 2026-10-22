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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            DashboardController::runPostAction('/users', function (): string {
                $action = DashboardController::postString('action');
                $actorId = (int) $_SESSION['user']['id'];

                if ($action === 'disable_login' || $action === 'enable_login') {
                    $id = filter_var(DashboardController::postString('user_id'), FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid account.');
                    }
                    if ($id === $actorId) {
                        throw new \RuntimeException('You cannot disable or enable your own login.');
                    }
                    $disabled = $action === 'disable_login';
                    UsersModel::setLoginDisabled($id, $disabled);
                    AuditLogsModel::record(
                        $actorId,
                        $disabled ? 'user.login_disabled' : 'user.login_enabled',
                        'user',
                        (string) $id
                    );
                    return $disabled
                        ? 'Login disabled. The user will be signed out on their next request.'
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
                    $temporaryPassword = bin2hex(random_bytes(8));
                    UsersModel::setTemporaryPassword($id, password_hash($temporaryPassword, PASSWORD_DEFAULT));
                    AuditLogsModel::record($actorId, 'user.password_reset', 'user', (string) $id);
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
                $hospitalId = self::optionalId('hospital_id');
                $barangayId = self::optionalId('barangay_id');
                $hospitals = UsersModel::hospitals();
                if ($barangayId !== null && $hospitalId === null) {
                    throw new \InvalidArgumentException('Select the hospital assigned to the chosen barangay.');
                }
                $barangays = UsersModel::barangays($hospitalId);
                if ($hospitalId !== null && !array_key_exists($hospitalId, $hospitals)) {
                    throw new \InvalidArgumentException('Select an active hospital.');
                }
                if ($barangayId !== null && !array_key_exists($barangayId, $barangays)) {
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
                    'hospital_name' => $hospitalId !== null ? $hospitals[$hospitalId] : 'Not assigned',
                    'hospital_id' => $hospitalId,
                    'barangay_id' => $barangayId,
                ];

                if ($action === 'create') {
                    $temporaryPassword = bin2hex(random_bytes(8));
                    $data['password_hash'] = password_hash($temporaryPassword, PASSWORD_DEFAULT);
                    $id = UsersModel::create($data);
                    AuditLogsModel::record($actorId, 'user.created', 'user', (string) $id);
                    return 'Account created. Temporary password: ' . $temporaryPassword . ' — share it securely; it expires in 24 hours.';
                }

                $id = filter_var(DashboardController::postString('user_id'), FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new \InvalidArgumentException('Choose a valid account to update.');
                }
                if ($id === $actorId && $role !== 'super_admin') {
                    throw new \RuntimeException('You cannot change your own Super Admin role.');
                }
                UsersModel::update($id, $data);
                AuditLogsModel::record($actorId, 'user.updated', 'user', (string) $id);
                return 'Account details updated.';
            });
        }

        $roleCounts = UsersModel::roleCounts();
        $role = $_GET['role'] ?? '';
        if (!is_string($role) || !array_key_exists($role, $roleCounts)) {
            $role = '';
        }

        $users = UsersModel::all($role !== '' ? $role : null);
        $selectedId = filter_input(INPUT_GET, 'account', FILTER_VALIDATE_INT);
        $selectedUser = $selectedId ? UsersModel::find($selectedId) : ($users[0] ?? null);
        if ($selectedUser && $role !== '' && $selectedUser['role'] !== $role) {
            $selectedUser = $users[0] ?? null;
        }

        DashboardController::renderDashboard('users', [
            'users' => $users,
            'roleCounts' => $roleCounts,
            'roleLabels' => UsersModel::roleLabels(),
            'hospitals' => UsersModel::hospitals(),
            'barangays' => UsersModel::barangays(),
            'selectedRole' => $role,
            'selectedUser' => $selectedUser,
        ]);
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
