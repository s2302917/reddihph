<?php
/**
 * dispatchController.php
 *
 * CONTROLLER — all logic and functions.
 * Pulls live data from dispatchModel,
 * applies display rules (which barangay cards are active, bar
 * percentages, relative time text, staff initials), and hands a
 * clean $data array to dispatchView.php. No HTML lives here.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/dispatchModel.php';

requireAuthenticated('hospital_admin');

class dispatchController
{
    private dispatchModel $model;

    public function __construct()
    {
        $this->model = new dispatchModel();
    }

    /**
     * Main entry point for the Incoming Dispatches page.
     */
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $profile = $this->model->getUserProfile($userId) ?: [];
        $barangays = $this->model->getBarangays($hospitalId);
        $facilityStatus = $this->model->getFacilityStatus($hospitalId);
        $staffOnDuty = $this->model->getStaffOnDuty($hospitalId);
        $activeDispatches = $this->model->getActiveDispatches($hospitalId);

        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'User');

        $data = [
            'pageTitle' => 'Incoming Dispatches',
            'hospitalName' => $_SESSION['hospital_name'] ?? 'Hospital Dashboard',
            'coordinatorName' => $coordinatorName,
            'coordinatorRole' => $this->formatRole($profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin')),
            'coordinatorInitials' => $this->initialsFromName($coordinatorName),
            'coordinatorImage' => $this->profileImageUrl($profile['profile_image'] ?? null),
            'barangays' => $this->prepareBarangayCards($barangays, $activeDispatches),
            'facilityStatus' => $this->prepareFacilityStatus($facilityStatus),
            'staffOnDuty' => $this->prepareStaffList($staffOnDuty),
            'activeDispatches' => $this->prepareActiveDispatches($activeDispatches),
            'alertCount' => count($activeDispatches),
        ];

        $this->render($data);
    }

    public function status(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
        $dispatches = $this->prepareActiveDispatches($this->model->getActiveDispatches($hospitalId));
        $facilityStatus = $this->prepareFacilityStatus($this->model->getFacilityStatus($hospitalId));
        $staffOnDuty = $this->prepareStaffList($this->model->getStaffOnDuty($hospitalId));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'count' => count($dispatches),
            'dispatches' => $dispatches,
            'facilityStatus' => $facilityStatus,
            'staffOnDuty' => $staffOnDuty,
        ]);
        exit;
    }

    public function incidentNotifications(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
        if ($hospitalId <= 0) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Hospital assignment required.']);
            exit;
        }

        try {
            $notifications = $this->model->getIncidentNotifications($hospitalId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'incidentNotifications' => $notifications]);
        } catch (Throwable $e) {
            error_log('[dispatchController] Incident notification query failed: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'New incident notifications are temporarily unavailable.']);
        }
        exit;
    }

    /* -----------------------------------------------------------
     * Private helpers — business rules / formatting live here
     * ----------------------------------------------------------- */

    /**
     * Decides card state (active/idle) and label text for each barangay tile.
     */
    private function prepareBarangayCards(array $barangays, array $dispatches = []): array
    {
        $preparedDispatches = $this->prepareActiveDispatches($dispatches);
        $byBarangay = [];
        foreach ($preparedDispatches as $dispatch) {
            if ($dispatch['barangayId'] !== null) {
                $byBarangay[$dispatch['barangayId']][] = $dispatch;
            }
        }

        return array_map(function ($b) use ($byBarangay) {
            $dispatches = $byBarangay[(int) $b['id']] ?? [];
            $count = count($dispatches);

            return [
                'id' => $b['id'],
                'slug' => $b['slug'],
                'name' => $b['name'],
                'count' => $count,
                'isActive' => $count > 0,
                'mapUrl' => assetUrl('app/controllers/livemappingController.php?barangay=' . rawurlencode((string) $b['slug'])),
                'dispatches' => $dispatches,
                'statusLabel' => $count > 0
                    ? sprintf('%d active %s', $count, $count === 1 ? 'dispatch' : 'dispatches')
                    : 'No active dispatch',
            ];
        }, $barangays);
    }

    /**
     * Formats facility metrics into percentage bars + display values.
     */
    private function prepareFacilityStatus(array $rows): array
    {
        $formatted = [];

        foreach ($rows as $row) {
            $current = (float) $row['current_value'];
            $max = (float) $row['max_value'];

            // Facility values report availability; bars show occupied capacity.
            $barValue = max(0, $max - $current);
            $percent = $max > 0
                ? round(($barValue / $max) * 100)
                : 0;

            // Keep percentage within valid progress-bar range
            $percent = max(0, min(100, $percent));

            // Determine capacity color class
            if ($percent >= 80) {
                $capacityLevel = 'high';
            } elseif ($percent >= 50) {
                $capacityLevel = 'medium';
            } else {
                $capacityLevel = 'low';
            }

            $formatted[$row['metric_key']] = [
                'label' => $this->metricLabel($row['metric_key']),

                'display' => $max > 0
                    ? sprintf('%d/%d', $current, $max)
                    : (($row['status_label'] ?? '') ?: 'Capacity not configured'),

                'current' => (int) $current,
                'max' => (int) $max,

                'percent' => $percent,

                // Color is decided by Controller
                'capacityClass' => 'incomingdispatch-metric-bar-fill--' . $capacityLevel,

                'statusText' => $row['status_label'] ?? null,
            ];
        }

        return $formatted;
    }

    private function metricLabel(string $key): string
    {
        $labels = [
            'bed_capacity' => 'Available Beds',
            'ambulance_bay' => 'Ambulance 1',
            'ptv' => 'PTV',
        ];

        return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Builds initials + availability dot state for each staff member.
     */
    private function prepareStaffList(array $staff): array
    {
        return array_map(function ($s) {
            return [
                'initials' => $this->initialsFromName($s['full_name']),
                'name' => $s['full_name'],
                'department' => $s['department'],
                'role' => $s['role'] ?? null,
                'dutyHours' => $this->formatDutyHours($s),
                'isAvailable' => (bool) $s['is_available'],
                'image' => $this->profileImageUrl($s['profile_image'] ?? null),
            ];
        }, $staff);
    }

    private function formatDutyHours(array $staff): ?string
    {
        if (empty($staff['duty_start']) || empty($staff['duty_end'])) {
            return null;
        }

        $timezone = $staff['duty_timezone'] ?? 'Asia/Manila';
        return date('g:i A', strtotime($staff['duty_start'])) . ' - '
            . date('g:i A', strtotime($staff['duty_end'])) . ' (GMT+8, ' . $timezone . ')';
    }

    private function prepareActiveDispatches(array $dispatches): array
    {
        return array_map(function (array $dispatch): array {
            $barangay = $dispatch['barangay_name'] ?: 'Incoming emergency';
            return [
                'id' => (int) $dispatch['incident_id'],
                'label' => $dispatch['dispatch_purpose'],
                'dispatchPurpose' => $dispatch['dispatch_purpose'] ?: 'Emergency dispatch',
                'patientName' => $dispatch['patient_name'] ?: 'Patient name not provided',
                'patientGender' => $dispatch['patient_gender'] ?: null,
                'patientAge' => $dispatch['patient_age'] !== null ? (int) $dispatch['patient_age'] : null,
                'location' => $dispatch['location'],
                'barangay' => $barangay,
                'barangayId' => $dispatch['barangay_id'] !== null ? (int) $dispatch['barangay_id'] : null,
                'barangaySlug' => $dispatch['barangay_slug'] ?: $this->slugFromName($barangay),
                'status' => ucwords($dispatch['status']),
                'eta' => $dispatch['eta_minutes'] !== null ? (int) $dispatch['eta_minutes'] : null,
                'vehicleName' => $dispatch['vehicle_name'] ?: 'Vehicle not assigned',
                'transportName' => $dispatch['vehicle_name'] ?: 'Vehicle not assigned',
                'transportType' => $dispatch['vehicle_type'] ?: 'Vehicle type not provided',
                'driverName' => $dispatch['driver_name'] ?: 'Driver not assigned',
                'reportedAt' => $dispatch['reported_at'],
                'mapUrl' => assetUrl(
                    'app/controllers/livemappingController.php?barangay=' .
                    rawurlencode($dispatch['barangay_slug'] ?: $this->slugFromName($barangay))
                ),
            ];
        }, $dispatches);
    }

    private function slugFromName(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)), '-');
    }

    private function profileImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $image) || str_starts_with($image, '/')) {
            return $image;
        }

        return assetUrl('assets/images/profiles/' . basename($image));
    }

    private function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $letters = array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), $parts);
        return implode('', array_slice($letters, 0, 2));
    }

    private function formatRole(string $role): string
    {
        switch ($role) {
            case 'hospital_admin':
                return 'Hospital Admin';
            case 'doctor':
                return 'Doctor';
            case 'nurse':
                return 'Nurse';
            default:
                return ucwords(str_replace('_', ' ', $role));
        }
    }

    /**
     * Extracts $data into scope and includes dispatchView.php.
     */
    private function render(array $data = []): void
    {
        extract($data);
        require_once __DIR__ . '/../views/dispatchView.php';
    }
}

/**
 * Bootstrap — runs the page directly from this file so no separate
 * index.php/router is needed. Visit dispatchController.php in the
 * browser at the deployed Hostinger application URL to see the page.
 */
$dispatchController = new dispatchController();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'incident_notifications') {
    $dispatchController->incidentNotifications();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'status') {
    $dispatchController->status();
}

$dispatchController->index();