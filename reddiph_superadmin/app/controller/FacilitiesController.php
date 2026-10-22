<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

use App\Models\FacilitiesModel;
use App\Models\AuditLogsModel;
use App\Models\UsersModel;

class FacilitiesController
{
    public function index(): void
    {
        DashboardController::requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            DashboardController::runPostAction('/facilities', function (): string {
                $action = DashboardController::postString('action');
                $id = filter_var(DashboardController::postString('id'), FILTER_VALIDATE_INT);
                $actorId = (int) $_SESSION['user']['id'];
                if ($action === 'set_barangay_visibility') {
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid barangay.');
                    }
                    $visibility = DashboardController::postString('is_active');
                    if (!in_array($visibility, ['0', '1'], true)) {
                        throw new \InvalidArgumentException('Choose whether the barangay should be visible.');
                    }
                    $isActive = $visibility === '1';
                    FacilitiesModel::setBarangayVisibility($id, $isActive);
                    AuditLogsModel::record(
                        $actorId,
                        $isActive ? 'facility.barangay_shown' : 'facility.barangay_hidden',
                        'barangay',
                        (string) $id
                    );
                    return $isActive
                        ? 'Barangay is visible on the Reddiph admin site.'
                        : 'Barangay is hidden from the Reddiph admin site.';
                }
                if (in_array($action, ['archive_hospital', 'archive_barangay', 'restore_hospital', 'restore_barangay'], true)) {
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid directory record.');
                    }
                    switch ($action) {
                        case 'archive_hospital':
                            FacilitiesModel::archiveHospital($id);
                            break;
                        case 'archive_barangay':
                            FacilitiesModel::archiveBarangay($id);
                            break;
                        case 'restore_hospital':
                            FacilitiesModel::restoreHospital($id);
                            break;
                        case 'restore_barangay':
                            FacilitiesModel::restoreBarangay($id);
                            break;
                    }
                    AuditLogsModel::record($actorId, 'facility.' . $action, 'facility', (string) $id);
                    return str_starts_with($action, 'restore_') ? 'Directory record restored.' : 'Directory record archived.';
                }

                if (in_array($action, ['create_hospital', 'update_hospital'], true)) {
                    $name = trim(DashboardController::postString('name'));
                    $address = trim(DashboardController::postString('address'));
                    $specialties = trim(DashboardController::postString('specialties'));
                    $totalBeds = filter_var(DashboardController::postString('total_beds'), FILTER_VALIDATE_INT);
                    $availableBeds = filter_var(DashboardController::postString('available_beds'), FILTER_VALIDATE_INT);
                    if ($name === '' || strlen($name) > 150 || strlen($address) > 255 || strlen($specialties) > 255 ||
                        $totalBeds === false || $availableBeds === false || $totalBeds < 0 ||
                        $availableBeds < 0 || $availableBeds > $totalBeds) {
                        throw new \InvalidArgumentException('Enter a hospital name, valid details, and bed counts where available beds do not exceed total beds.');
                    }
                    $data = [
                        'name' => $name,
                        'address' => $address !== '' ? $address : null,
                        'specialties' => $specialties,
                        'available_beds' => $availableBeds,
                        'total_beds' => $totalBeds,
                    ];
                    if ($action === 'create_hospital') {
                        $id = FacilitiesModel::createHospital($data);
                    } else {
                        if (!$id) {
                            throw new \InvalidArgumentException('Choose a valid hospital to update.');
                        }
                        FacilitiesModel::updateHospital($id, $data);
                    }
                    AuditLogsModel::record($actorId, 'facility.hospital_' . ($action === 'create_hospital' ? 'created' : 'updated'), 'hospital', (string) $id);
                    return $action === 'create_hospital' ? 'Hospital added.' : 'Hospital details updated.';
                }

                if (in_array($action, ['create_barangay', 'update_barangay'], true)) {
                    $name = trim(DashboardController::postString('name'));
                    $hospitalId = filter_var(DashboardController::postString('hospital_id'), FILTER_VALIDATE_INT);
                    $sortOrder = filter_var(DashboardController::postString('sort_order', '0'), FILTER_VALIDATE_INT);
                    $slug = strtolower(trim(DashboardController::postString('slug')));
                    if ($slug === '') {
                        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-');
                    }
                    if ($name === '' || strlen($name) > 100 || !$hospitalId || $sortOrder === false ||
                        $sortOrder < 0 || strlen($slug) > 120 || $slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
                        throw new \InvalidArgumentException('Enter a barangay name, valid slug, hospital, and non-negative sort order.');
                    }
                    $hospitalIds = array_keys(UsersModel::hospitals());
                    if (!in_array($hospitalId, $hospitalIds, true)) {
                        throw new \InvalidArgumentException('Select an active hospital.');
                    }
                    $data = ['name' => $name, 'slug' => $slug, 'hospital_id' => $hospitalId, 'sort_order' => $sortOrder];
                    if ($action === 'create_barangay') {
                        $id = FacilitiesModel::createBarangay($data);
                    } else {
                        if (!$id) {
                            throw new \InvalidArgumentException('Choose a valid barangay to update.');
                        }
                        FacilitiesModel::updateBarangay($id, $data);
                    }
                    AuditLogsModel::record($actorId, 'facility.barangay_' . ($action === 'create_barangay' ? 'created' : 'updated'), 'barangay', (string) $id);
                    return $action === 'create_barangay' ? 'Barangay added.' : 'Barangay details updated.';
                }
                throw new \InvalidArgumentException('Choose a valid directory action.');
            });
        }
        DashboardController::renderDashboard('facilities', FacilitiesModel::catalog() + [
            'archivedFacilities' => FacilitiesModel::archived(),
            'hospitalsForForms' => UsersModel::hospitals(),
        ]);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new FacilitiesController())->index();
}
