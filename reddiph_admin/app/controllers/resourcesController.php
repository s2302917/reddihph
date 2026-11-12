<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/resourcesModel.php';

requireAuthenticated('hospital_admin');

class ResourcesController
{
    private ResourcesModel $model;

    public function __construct()
    {
        $this->model = new ResourcesModel();
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isJsonRequest = $this->isJsonRequest();

        $requestedSection = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? ($_POST['resource_section'] ?? '')
            : ($_GET['section'] ?? '');
        $resourceSection = is_string($requestedSection)
            && in_array($requestedSection, ['bed-capacity', 'ambulance', 'ptv'], true)
                ? $requestedSection
                : 'bed-capacity';

        $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
        $profile = $this->model->getUserProfile((int) ($_SESSION['user_id'] ?? 0)) ?: [];
        $canManageResources = ($profile['role'] ?? ($_SESSION['role'] ?? '')) === 'hospital_admin';
        $resourceMessage = $_SESSION['resources_flash'] ?? '';
        $resourceMessageType = $_SESSION['resources_flash_type'] ?? 'success';
        unset($_SESSION['resources_flash']);
        unset($_SESSION['resources_flash_type']);

        if (empty($_SESSION['resources_csrf'])) {
            $_SESSION['resources_csrf'] = bin2hex(random_bytes(32));
        }
        $resourcesCsrf = $_SESSION['resources_csrf'];

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array(
            $_GET['action'] ?? '',
            ['incident_search', 'staff_search', 'bed_details'],
            true
        )) {
            if (!$canManageResources || !$hospitalId) {
                $this->jsonResponse(['success' => false, 'message' => 'Resource details are not authorized.'], 403);
            }
            try {
                if (($_GET['action'] ?? '') === 'bed_details') {
                    $bedId = filter_var($_GET['bed_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($bedId === false || $bedId <= 0) {
                        throw new InvalidArgumentException('Choose a valid bed record.');
                    }
                    $bed = $this->model->getHospitalBed($hospitalId, $bedId);
                    if ($bed === null) {
                        $this->jsonResponse(['success' => false, 'message' => 'Bed record was not found.'], 404);
                    }
                    $this->jsonResponse(['success' => true, 'bed' => $bed], 200);
                }
                $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
                if ($page === false || $page < 1) {
                    throw new InvalidArgumentException('Choose a valid results page.');
                }
                $query = $_GET['q'] ?? '';
                if (!is_string($query)) {
                    throw new InvalidArgumentException('Enter a valid search term.');
                }
                if (($_GET['action'] ?? '') === 'staff_search') {
                    $role = $_GET['role'] ?? '';
                    if (!is_string($role)) {
                        throw new InvalidArgumentException('Choose a valid staff category.');
                    }
                    $staff = $this->model->searchHospitalStaff(
                        $hospitalId,
                        $role,
                        $query,
                        $page
                    );
                    $this->jsonResponse(['success' => true, 'staff' => $staff], 200);
                }
                $bedId = filter_var($_GET['bed_id'] ?? null, FILTER_VALIDATE_INT);
                if ($bedId === false || $bedId <= 0) {
                    throw new InvalidArgumentException('Save the bed before searching for incidents.');
                }
                $incidents = $this->model->searchHospitalIncidents(
                    $hospitalId,
                    $query,
                    $page,
                    5,
                    $bedId
                );
                $this->jsonResponse(['success' => true, 'incidents' => $incidents], 200);
            } catch (InvalidArgumentException $e) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            } catch (Throwable $e) {
                error_log('[ResourcesController] Resource data lookup failed: ' . $e->getMessage());
                $this->jsonResponse(['success' => false, 'message' => 'Resource data could not be loaded. Please try again.'], 500);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManageResources) {
                if ($isJsonRequest) {
                    $this->jsonResponse(['success' => false, 'message' => 'Only hospital administrators can manage facility capacity.'], 403);
                }
                http_response_code(403);
                exit('Only hospital administrators can manage facility capacity.');
            }
            if (!hash_equals($resourcesCsrf, (string) ($_POST['csrf_token'] ?? ''))) {
                if ($isJsonRequest) {
                    $this->jsonResponse(['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.'], 403);
                }
                http_response_code(403);
                exit('Invalid request token. Refresh the page and try again.');
            }
            if (!$hospitalId) {
                if ($isJsonRequest) {
                    $this->jsonResponse(['success' => false, 'message' => 'No hospital is associated with this account.'], 400);
                }
                http_response_code(400);
                exit('No hospital is associated with this account.');
            }

            try {
                $action = (string) ($_POST['action'] ?? '');
                $actionBedId = 0;
                if ($action === 'adjust_bed') {
                    $change = filter_var($_POST['change'] ?? null, FILTER_VALIDATE_INT);
                    if ($change === false || !in_array($change, [-1, 1], true)) {
                        throw new InvalidArgumentException('Choose to add or subtract exactly one available bed.');
                    }
                    $this->model->adjustAvailableBeds($hospitalId, $change, (int) $_SESSION['user_id']);
                    $_SESSION['resources_flash'] = $change > 0 ? 'One available bed added.' : 'One available bed removed.';
                } elseif ($action === 'update_bed_max') {
                    $newMaximum = filter_var($_POST['max_beds'] ?? null, FILTER_VALIDATE_INT);
                    if ($newMaximum === false) {
                        throw new InvalidArgumentException('Maximum bed capacity must be a whole number.');
                    }
                    $this->model->updateBedMaximum($hospitalId, $newMaximum, (int) $_SESSION['user_id']);
                    $_SESSION['resources_flash'] = 'Maximum bed capacity updated.';
                } elseif ($action === 'bed_save') {
                    $bedIdValue = trim((string) ($_POST['bed_id'] ?? ''));
                    $bedId = $bedIdValue === ''
                        ? null
                        : filter_var($bedIdValue, FILTER_VALIDATE_INT);
                    if ($bedId === false) {
                        throw new InvalidArgumentException('Choose valid bed and staff records.');
                    }
                    $doctorIds = $this->parseStaffIds($_POST['doctor_ids'] ?? null, $_POST['doctor_id'] ?? '');
                    $nurseIds = $this->parseStaffIds($_POST['nurse_ids'] ?? null, $_POST['nurse_id'] ?? '');
                    $bedNumber = filter_var($_POST['bed_number'] ?? null, FILTER_VALIDATE_INT);
                    if ($bedNumber === false) {
                        throw new InvalidArgumentException('Bed number must be a whole number.');
                    }
                    $savedBedId = $this->model->saveBed(
                        $hospitalId,
                        $bedId,
                        (string) ($_POST['bed_type'] ?? ''),
                        $bedNumber,
                        (string) ($_POST['room_number'] ?? ''),
                        $doctorIds,
                        $nurseIds,
                        (int) $_SESSION['user_id'],
                        (string) ($_POST['bed_status'] ?? 'ready')
                    );
                    $_SESSION['resources_flash'] = $bedId === null
                        ? 'Bed registered.'
                        : 'Bed details updated.';
                    $actionBedId = $savedBedId;
                } elseif ($action === 'incident_assign' || $action === 'incident_unassign') {
                    if ($resourceSection !== 'bed-capacity') {
                        throw new InvalidArgumentException('Incidents can only be assigned from Bed Capacity.');
                    }
                    $bedId = filter_var($_POST['bed_id'] ?? null, FILTER_VALIDATE_INT);
                    $incidentId = $action === 'incident_unassign'
                        ? null
                        : filter_var($_POST['incident_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($bedId === false || $bedId <= 0
                        || ($action === 'incident_assign' && ($incidentId === false || $incidentId <= 0))) {
                        throw new InvalidArgumentException('Choose a valid bed and incident.');
                    }
                    $this->model->assignIncidentToBed(
                        $hospitalId,
                        $bedId,
                        $incidentId,
                        (int) $_SESSION['user_id']
                    );
                    $bed = $this->model->getHospitalBed($hospitalId, $bedId);
                    if ($bed === null) {
                        throw new RuntimeException('The bed assignment was saved but could not be reloaded.');
                    }
                    $this->jsonResponse([
                        'success' => true,
                        'message' => $incidentId === null ? 'Incident removed from the bed.' : 'Incident assigned to the bed.',
                        'bed' => $bed,
                    ], 200);
                } elseif ($action === 'vehicle_save') {
                    $vehicleIdValue = trim((string) ($_POST['vehicle_id'] ?? ''));
                    $vehicleId = $vehicleIdValue === '' ? null : filter_var($vehicleIdValue, FILTER_VALIDATE_INT);
                    if ($vehicleId === false || ($vehicleId !== null && $vehicleId <= 0)) {
                        throw new InvalidArgumentException('Invalid vehicle record.');
                    }
                    $routeSection = $_GET['section'] ?? '';
                    if (
                        !in_array($routeSection, ['ambulance', 'ptv'], true)
                        || $routeSection !== $resourceSection
                    ) {
                        throw new InvalidArgumentException('Register or edit vehicles from the Ambulance or PTV section.');
                    }
                    $vehicleStatus = $vehicleId === null
                        ? null
                        : (string) ($_POST['status'] ?? '');
                    $this->model->saveVehicle(
                        $hospitalId,
                        $vehicleId,
                        $routeSection === 'ptv' ? 'PTV' : 'Ambulance',
                        (string) ($_POST['call_sign'] ?? ''),
                        (string) ($_POST['plate_number'] ?? ''),
                        (string) ($_POST['driver_name'] ?? ''),
                        $vehicleStatus,
                        (int) $_SESSION['user_id']
                    );
                    $_SESSION['resources_flash'] = $vehicleId === null ? 'Vehicle added.' : 'Vehicle updated.';
                } elseif ($action === 'vehicle_delete') {
                    $vehicleId = filter_var($_POST['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($vehicleId === false || $vehicleId <= 0) {
                        throw new InvalidArgumentException('Invalid vehicle record.');
                    }
                    $this->model->deleteVehicle($hospitalId, $vehicleId, (int) $_SESSION['user_id']);
                    $_SESSION['resources_flash'] = 'Vehicle removed.';
                } else {
                    throw new InvalidArgumentException('Invalid resource action.');
                }
                $_SESSION['resources_flash_type'] = 'success';
                if ($isJsonRequest && $action === 'bed_save') {
                    $bed = $this->model->getHospitalBed($hospitalId, $actionBedId);
                    if ($bed === null) {
                        throw new RuntimeException('The bed was saved but could not be reloaded.');
                    }
                    $this->jsonResponse([
                        'success' => true,
                        'message' => $_SESSION['resources_flash'],
                        'bed' => $bed,
                    ], 200);
                }
                header('Location: resourcesController.php?section=' . rawurlencode($resourceSection), true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                $resourceMessage = $e->getMessage();
                $resourceMessageType = 'error';
                if ($isJsonRequest) {
                    $this->jsonResponse(['success' => false, 'message' => $resourceMessage], 422);
                }
            } catch (PDOException $e) {
                if ($action === 'vehicle_save' && $e->getCode() === '23000') {
                    $resourceMessage = 'That call sign or plate number is already in use at this hospital.';
                } elseif ($action === 'bed_save' && $e->getCode() === '23000') {
                    $resourceMessage = 'That bed number or staff assignment conflicts with an existing record.';
                } else {
                    error_log('[ResourcesController] Resource update failed: ' . $e->getMessage());
                    $resourceMessage = 'Resource record could not be saved. Please try again.';
                }
                $resourceMessageType = 'error';
                if ($isJsonRequest) {
                    $this->jsonResponse(['success' => false, 'message' => $resourceMessage], $e->getCode() === '23000' ? 409 : 500);
                }
            } catch (Throwable $e) {
                error_log('[ResourcesController] Facility update failed: ' . $e->getMessage());
                $resourceMessage = 'Resource update failed. Please try again.';
                $resourceMessageType = 'error';
                if ($isJsonRequest) {
                    $this->jsonResponse(['success' => false, 'message' => $resourceMessage], 500);
                }
            }
        }

        $resourceData = $this->model->getHospitalResources($hospitalId);

        $hospitalName = $_SESSION['hospital_name'] ?? 'DSB District Hospital';
        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator');
        $coordinatorRole = $profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin');
        $coordinatorInitials = $this->initials($coordinatorName);
        $coordinatorImage = userProfileImageUrl($_SESSION['profile_image'] ?? null);
        $bedAreas = $resourceData['bedAreas'] ?? [];
        $staffOptions = $resourceData['staffOptions'] ?? ['doctors' => [], 'nurses' => []];
        $vehicleCounts = $resourceData['vehicleCounts'] ?? [];
        $fleet = $resourceData['fleet'] ?? [];
        $resourceAlerts = $resourceData['alerts'] ?? [];
        $bedAssignmentsAvailable = (bool) ($resourceData['bedAssignmentsAvailable'] ?? false);
        $multipleBedStaffAvailable = (bool) ($resourceData['multipleBedStaffAvailable'] ?? false);
        $resourceDataError = $resourceData['dataError'] ?? null;

        require __DIR__ . '/../views/resourcesView.php';
    }

    private function isJsonRequest(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
    }

    private function jsonResponse(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function parseStaffIds($postedIds, $legacyId): array
    {
        if ($postedIds === null) {
            $postedIds = trim((string) $legacyId) === '' ? [] : [$legacyId];
        }
        if (!is_array($postedIds)) {
            throw new InvalidArgumentException('Choose valid assigned staff members.');
        }
        $staffIds = [];
        foreach ($postedIds as $postedId) {
            $staffId = filter_var($postedId, FILTER_VALIDATE_INT);
            if ($staffId === false || $staffId <= 0) {
                throw new InvalidArgumentException('Choose valid assigned staff members.');
            }
            $staffIds[] = $staffId;
        }
        return array_values(array_unique($staffIds));
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach (array_slice($parts ?: [], 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials !== '' ? $initials : 'HA';
    }
}

(new ResourcesController())->index();
