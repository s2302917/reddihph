<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\RolesModel;
use App\Models\AuditLogsModel;

class RolesController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            DashboardController::runPostAction('/roles', function (): string {
                $action = DashboardController::postString('action');
                $actorId = (int) $_SESSION['user']['id'];
                $key = trim(DashboardController::postString('role_key'));
                if ($action === 'create' || $action === 'update') {
                    $name = trim(DashboardController::postString('display_name'));
                    $description = trim(DashboardController::postString('description'));
                    if ($name === '' || strlen($name) > 100 || strlen($description) > 255) {
                        throw new \InvalidArgumentException('Enter a role name of 100 characters or fewer and a description of 255 characters or fewer.');
                    }
                    if ($action === 'create') {
                        RolesModel::create($key, $name, $description);
                    } else {
                        RolesModel::update($key, $name, $description);
                    }
                    AuditLogsModel::record($actorId, 'role.' . ($action === 'create' ? 'created' : 'updated'), 'role', $key);
                    return $action === 'create' ? 'Role created.' : 'Role updated.';
                }
                if ($action === 'delete') {
                    RolesModel::delete($key);
                    AuditLogsModel::record($actorId, 'role.deleted', 'role', $key);
                    return 'Role deleted.';
                }
                if ($action === 'permission') {
                    $permissionId = filter_var(DashboardController::postString('permission_id'), FILTER_VALIDATE_INT);
                    if ($key === '' || !$permissionId) {
                        throw new \InvalidArgumentException('Choose a role and permission.');
                    }
                    $granted = DashboardController::postString('granted') === '1';
                    RolesModel::setPermission($key, $permissionId, $granted, $actorId);
                    AuditLogsModel::record($actorId, $granted ? 'permission.granted' : 'permission.revoked', 'role', $key, ['permission_id' => $permissionId]);
                    return $granted ? 'Permission granted.' : 'Permission revoked.';
                }
                if ($action === 'permission_matrix') {
                    $submitted = $_POST['grants'] ?? null;
                    if (!is_array($submitted)) {
                        throw new \InvalidArgumentException('The permission matrix was not submitted correctly.');
                    }
                    $changes = RolesModel::savePermissionMatrix($submitted, $actorId);
                    foreach ($changes as $role => $roleChanges) {
                        AuditLogsModel::record(
                            $actorId,
                            'permission.matrix_updated',
                            'role',
                            $role,
                            ['changes' => $roleChanges]
                        );
                    }
                    return $changes ? 'Permission changes saved.' : 'No permission changes to save.';
                }
                throw new \InvalidArgumentException('Choose a valid role action.');
            });
        }
        DashboardController::renderDashboard('roles', RolesModel::data());
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new RolesController())->index();
}
