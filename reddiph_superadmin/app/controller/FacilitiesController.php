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
            $returnType = $_GET['type'] ?? 'hospitals';
            $returnStatus = $_GET['status'] ?? 'active';
            $returnSearch = $_GET['q'] ?? '';
            $redirectParameters = [
                'type' => is_string($returnType) && in_array($returnType, ['hospitals', 'barangays'], true)
                    ? $returnType
                    : 'hospitals',
                'status' => is_string($returnStatus) && $returnStatus === 'archived' ? 'archived' : 'active',
            ];
            if (is_string($returnSearch) && trim($returnSearch) !== '') {
                $redirectParameters['q'] = substr(trim($returnSearch), 0, 100);
            }
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
                    $barangay = FacilitiesModel::findDirectoryRecord('barangays', $id);
                    $isActive = $visibility === '1';
                    FacilitiesModel::setBarangayVisibility($id, $isActive);
                    AuditLogsModel::record(
                        $actorId,
                        $isActive ? 'facility.barangay_shown' : 'facility.barangay_hidden',
                        'barangay',
                        (string) $id,
                        ['name' => $barangay['name'] ?? '']
                    );
                    return $isActive
                        ? 'Barangay is visible on the Reddiph admin site.'
                        : 'Barangay is hidden from the Reddiph admin site.';
                }
                if (in_array($action, ['archive_hospital', 'archive_barangay', 'restore_hospital', 'restore_barangay'], true)) {
                    if (!$id) {
                        throw new \InvalidArgumentException('Choose a valid directory record.');
                    }
                    $facilityType = str_contains($action, 'hospital') ? 'hospitals' : 'barangays';
                    $recordType = $facilityType === 'hospitals' ? 'hospital' : 'barangay';
                    $record = FacilitiesModel::findDirectoryRecord($facilityType, $id, true);
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
                    AuditLogsModel::record(
                        $actorId,
                        'facility.' . $action,
                        $recordType,
                        (string) $id,
                        ['name' => $record['name'] ?? '']
                    );
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
                    AuditLogsModel::record(
                        $actorId,
                        'facility.hospital_' . ($action === 'create_hospital' ? 'created' : 'updated'),
                        'hospital',
                        (string) $id,
                        ['name' => $name]
                    );
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
                    $hospitalNames = UsersModel::hospitals();
                    $hospitalIds = array_keys($hospitalNames);
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
                    AuditLogsModel::record(
                        $actorId,
                        'facility.barangay_' . ($action === 'create_barangay' ? 'created' : 'updated'),
                        'barangay',
                        (string) $id,
                        [
                            'name' => $name,
                            'hospital_name' => $hospitalNames[$hospitalId] ?? '',
                        ]
                    );
                    return $action === 'create_barangay' ? 'Barangay added.' : 'Barangay details updated.';
                }
                throw new \InvalidArgumentException('Choose a valid directory action.');
            }, $redirectParameters);
        }
        try {
            $directoryType = $_GET['type'] ?? 'hospitals';
            if (!is_string($directoryType) || !in_array($directoryType, ['hospitals', 'barangays'], true)) {
                $directoryType = 'hospitals';
            }
            $supportsArchive = FacilitiesModel::supportsArchive();
            $supportsWrites = FacilitiesModel::supportsAuditLogging();
            $status = $_GET['status'] ?? 'active';
            $status = $supportsArchive && is_string($status) && $status === 'archived' ? 'archived' : 'active';
            $search = $_GET['q'] ?? '';
            $search = is_string($search) ? substr(trim($search), 0, 100) : '';
            $totalRecords = FacilitiesModel::countDirectory($directoryType, $status, $search);
            $pageSize = 15;
            $totalPages = max(1, (int) ceil($totalRecords / $pageSize));
            $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $currentPage = max(1, min($totalPages, is_int($requestedPage) ? $requestedPage : 1));
            $records = FacilitiesModel::listDirectory(
                $directoryType,
                $status,
                $search,
                $pageSize,
                ($currentPage - 1) * $pageSize
            );
            $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
            $editRecord = $supportsWrites && $status === 'active' && is_int($editId) && $editId > 0
                ? FacilitiesModel::findDirectoryRecord($directoryType, $editId)
                : null;
            $hospitalCounts = FacilitiesModel::directoryCounts('hospitals');
            $barangayCounts = FacilitiesModel::directoryCounts('barangays');
            $data = [
                'directoryType' => $directoryType,
                'directoryStatus' => $status,
                'searchQuery' => $search,
                'totalRecords' => $totalRecords,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'pageSize' => $pageSize,
                'records' => $records,
                'supportsArchive' => $supportsArchive,
                'supportsWrites' => $supportsWrites,
                'hospitalCounts' => $hospitalCounts,
                'barangayCounts' => $barangayCounts,
                'hospitalsForForms' => FacilitiesModel::activeHospitalsForForms(),
                'editRecord' => $editRecord,
            ];
        } catch (\PDOException | \RuntimeException $exception) {
            error_log('Super Admin facilities page could not load: ' . $exception->getMessage());
            $pageError = str_contains($exception->getMessage(), 'archived_at')
                || str_contains($exception->getMessage(), 'is_active')
                ? 'The directory archive schema is missing. After taking a database backup, review and apply reddiph_superadmin/config/migrations/20261022_portal_pages.sql.'
                : 'Hospital and barangay data could not be loaded. Check the shared database connection and verify that the backup schema is available.';
            $data = [
                'pageError' => $pageError,
            ];
        }
        DashboardController::renderDashboard('facilities', $data);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    DashboardController::initialize();
    (new FacilitiesController())->index();
}
