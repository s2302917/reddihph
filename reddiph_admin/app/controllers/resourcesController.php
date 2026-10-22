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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManageResources) {
                http_response_code(403);
                exit('Only hospital administrators can manage facility capacity.');
            }
            if (!hash_equals($resourcesCsrf, (string) ($_POST['csrf_token'] ?? ''))) {
                http_response_code(403);
                exit('Invalid request token. Refresh the page and try again.');
            }
            if (!$hospitalId) {
                http_response_code(400);
                exit('No hospital is associated with this account.');
            }

            try {
                $action = $_POST['action'] ?? '';
                if ($action === 'adjust_bed') {
                    $change = filter_var($_POST['change'] ?? null, FILTER_VALIDATE_INT);
                    if ($change === false || !in_array($change, [-1, 1], true)) {
                        throw new InvalidArgumentException('Choose to add or subtract exactly one available bed.');
                    }
                    $this->model->adjustAvailableBeds($hospitalId, $change);
                    $_SESSION['resources_flash'] = $change > 0 ? 'One available bed added.' : 'One available bed removed.';
                } elseif ($action === 'update_bed_max') {
                    $newMaximum = filter_var($_POST['max_beds'] ?? null, FILTER_VALIDATE_INT);
                    if ($newMaximum === false) {
                        throw new InvalidArgumentException('Maximum bed capacity must be a whole number.');
                    }
                    $this->model->updateBedMaximum($hospitalId, $newMaximum);
                    $_SESSION['resources_flash'] = 'Maximum bed capacity updated.';
                } elseif ($action === 'vehicle_save') {
                    $vehicleIdValue = trim((string) ($_POST['vehicle_id'] ?? ''));
                    $vehicleId = $vehicleIdValue === '' ? null : filter_var($vehicleIdValue, FILTER_VALIDATE_INT);
                    if ($vehicleId === false || ($vehicleId !== null && $vehicleId <= 0)) {
                        throw new InvalidArgumentException('Invalid vehicle record.');
                    }
                    $this->model->saveVehicle(
                        $hospitalId,
                        $vehicleId,
                        (string) ($_POST['vehicle_type'] ?? ''),
                        (string) ($_POST['call_sign'] ?? ''),
                        (string) ($_POST['plate_number'] ?? ''),
                        (string) ($_POST['driver_name'] ?? ''),
                        (string) ($_POST['status'] ?? 'available')
                    );
                    $_SESSION['resources_flash'] = $vehicleId === null ? 'Vehicle added.' : 'Vehicle updated.';
                } elseif ($action === 'vehicle_delete') {
                    $vehicleId = filter_var($_POST['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($vehicleId === false || $vehicleId <= 0) {
                        throw new InvalidArgumentException('Invalid vehicle record.');
                    }
                    $this->model->deleteVehicle($hospitalId, $vehicleId);
                    $_SESSION['resources_flash'] = 'Vehicle removed.';
                } else {
                    throw new InvalidArgumentException('Invalid resource action.');
                }
                $_SESSION['resources_flash_type'] = 'success';
                header('Location: resourcesController.php', true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                $resourceMessage = $e->getMessage();
                $resourceMessageType = 'error';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $resourceMessage = 'That call sign or plate number is already in use at this hospital.';
                } else {
                    error_log('[ResourcesController] Vehicle update failed: ' . $e->getMessage());
                    $resourceMessage = 'Vehicle record could not be saved. Please try again.';
                }
                $resourceMessageType = 'error';
            } catch (Throwable $e) {
                error_log('[ResourcesController] Facility update failed: ' . $e->getMessage());
                $resourceMessage = 'Resource update failed. Please try again.';
                $resourceMessageType = 'error';
            }
        }

        $resourceData = $this->model->getHospitalResources($hospitalId);

        $hospitalName = $_SESSION['hospital_name'] ?? 'DSB District Hospital';
        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator');
        $coordinatorRole = $profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin');
        $coordinatorInitials = $this->initials($coordinatorName);
        $coordinatorImage = userProfileImageUrl($_SESSION['profile_image'] ?? null);
        $summaryCards = $resourceData['summary'] ?? [];
        $resourceCards = $resourceData['cards'] ?? [];
        $fleet = $resourceData['fleet'] ?? [];
        $resourceAlerts = $resourceData['alerts'] ?? [];

        require __DIR__ . '/../views/resourcesView.php';
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
