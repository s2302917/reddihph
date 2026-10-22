<?php



/**
 * emergency_alertController.php
 *
 * Controller for the Pulse Alert Emergency Alerts page.
 * Responsibilities:
 * - Receive/filter request parameters.
 * - Ask the model for database data.
 * - Prepare presentation data.
 * - Handle Notify Staff and View Details actions.
 *
 * Database access and SQL remain inside emergency_alertModel.php.
 */

// TEMP DEBUG: uncomment these two lines while diagnosing a blank/broken
// page on a live host, then remove them again once things are working —
// leaving verbose errors on in production can leak file paths.
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/emergency_alertModel.php';
require_once __DIR__ . '/../models/livemappingModel.php';

requireAuthenticated('hospital_admin');


class EmergencyAlertController
{
    private EmergencyAlertModel $model;

    public function __construct()
    {
        $this->model = new EmergencyAlertModel();
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $summary = $this->model->getSummary();
        $rawAlerts = $this->model->getAlerts('all', '');
        $profile = $this->model->getUserProfile((int) ($_SESSION['user_id'] ?? 0)) ?: [];
        $hospitalName = $profile['hospital_name'] ?? ($_SESSION['hospital_name'] ?? $this->model->getHospitalName());
        $pageTitle = $hospitalName . ' : Admitting & Coordination';

        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator');
        $coordinatorRole = $this->formatRole($profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin'));
        $coordinatorInitials = $this->initialsFromName($coordinatorName);
        $coordinatorImage = userProfileImageUrl($_SESSION['profile_image'] ?? null);
        $alertCount = (int) ($summary['critical'] ?? 0);

        $alerts = array_map([$this, 'prepareAlert'], $rawAlerts);

        $initialData = [
            'success' => true,
            'pageTitle' => $pageTitle,
            'coordinatorName' => $coordinatorName,
            'coordinatorRole' => $coordinatorRole,
            'coordinatorInitials' => $coordinatorInitials,
            'coordinatorImage' => $coordinatorImage,
            'summary' => $summary,
            'alerts' => $alerts,
        ];

        require __DIR__ . '/../views/emergency_alertView.php';
    }

    public function data(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $status = $this->getRequestedStatus();
        $search = trim((string) ($_GET['search'] ?? ''));

        $summary = $this->model->getSummary();
        $rawAlerts = $this->model->getAlerts($status, $search);
        $profile = $this->model->getUserProfile((int) ($_SESSION['user_id'] ?? 0)) ?: [];
        $hospitalName = $profile['hospital_name'] ?? ($_SESSION['hospital_name'] ?? $this->model->getHospitalName());
        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator');
        $coordinatorRole = $this->formatRole($profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin'));
        $coordinatorImage = userProfileImageUrl($_SESSION['profile_image'] ?? null);

        $this->jsonResponse([
            'success' => true,
            'pageTitle' => $hospitalName . ' : Admitting & Coordination',
            'coordinatorName' => $coordinatorName,
            'coordinatorRole' => $coordinatorRole,
            'coordinatorInitials' => $this->initialsFromName($coordinatorName),
            'coordinatorImage' => $coordinatorImage,
            'summary' => [
                'all' => (int) ($summary['all'] ?? 0),
                'critical' => (int) ($summary['critical'] ?? 0),
                'urgent' => (int) ($summary['urgent'] ?? 0),
                'stable' => (int) ($summary['stable'] ?? 0),
            ],
            'alerts' => array_map(
                [$this, 'prepareAlert'],
                $rawAlerts
            ),
        ]);
    }

    public function notifyStaff(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid alert ID.'], 422);
        }

        try {
            $hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
            $incident = $this->model->getAlertById((int) $id);
            if (!$incident || !$hospitalId || (int) ($incident['hospital_id'] ?? 0) !== $hospitalId) {
                $this->jsonResponse(['success' => false, 'message' => 'Incident not found for this hospital.'], 404);
            }

            $preparedAlert = $this->prepareAlert($incident);
            $result = (new LivemappingModel())->sendStaffAlert([
                'incident_id' => (int) $id,
                'transport_name' => $preparedAlert['transport'],
                'message' => $this->buildStaffNotificationMessage($preparedAlert),
            ], $hospitalId);

            $this->jsonResponse([
                'success' => (bool) ($result['success'] ?? false),
                'message' => !empty($result['success'])
                    ? 'Incident details queued for ' . (int) ($result['count'] ?? 0) . ' on-duty staff member(s).'
                    : (string) ($result['message'] ?? 'Unable to queue the incident notification.'),
                'count' => (int) ($result['count'] ?? 0),
                'sent_to' => $result['sent_to'] ?? [],
            ], !empty($result['success']) ? 200 : 422);
        } catch (Throwable $e) {
            error_log('[EmergencyAlertController] notifyStaff: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Unable to send the staff notification.',
            ], 500);
        }
    }

    private function buildStaffNotificationMessage(array $alert): string
    {
        $coordinates = $alert['latitude'] !== null && $alert['longitude'] !== null
            ? $alert['latitude'] . ', ' . $alert['longitude']
            : '—';

        return implode("\n", [
            'Emergency incident notification',
            'Status: ' . ($alert['status'] ?: '—'),
            'Incident: ' . ($alert['incidentType'] ?: '—'),
            'Patient: ' . ($alert['patientName'] ?: $alert['patientInfo'] ?: '—'),
            'Location: ' . ($alert['location'] ?: '—'),
            'Barangay: ' . ($alert['barangay'] ?: '—'),
            'Gender / Age: ' . trim(($alert['gender'] ?: '—') . ' / ' . ($alert['age'] !== null ? $alert['age'] : '—')),
            'Assigned responder: ' . ($alert['responder'] ?: '—'),
            'Transport: ' . ($alert['transport'] ?: '—'),
            'Driver: ' . ($alert['driver'] ?: '—'),
            'Hospital: ' . ($alert['hospital'] ?: '—'),
            'ETA: ' . ($alert['eta'] ?: '—'),
            'Reported: ' . ($alert['reportedAt'] ?: '—'),
            'Resolved: ' . ($alert['resolvedAt'] ?: '—'),
            'Coordinates: ' . $coordinates,
            'Notes: ' . ($alert['notes'] ?: '—'),
        ]);
    }

    public function details(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid alert ID.'], 422);
        }

        $alert = $this->model->getAlertById((int) $id);

        if (!$alert) {
            $this->jsonResponse(['success' => false, 'message' => 'Alert not found.'], 404);
        }

        $this->jsonResponse([
            'success' => true,
            'alert' => $this->prepareAlert($alert),
        ]);
    }

    public function updateStatus(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $status = strtolower(trim((string) ($_POST['status'] ?? '')));

        if (!$id || !in_array($status, ['critical', 'urgent', 'stable'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid incident or status.'], 422);
        }

        $updated = $this->model->updateStatus((int) $id, $status);
        $this->jsonResponse([
            'success' => $updated,
            'message' => $updated ? 'Incident status updated.' : 'Unable to update the incident status.',
        ], $updated ? 200 : 404);
    }

    private function formatRole(string $role): string
    {
        return strtolower(trim($role)) === 'hospital_admin'
            ? 'Hospital Admin'
            : ucwords(str_replace('_', ' ', $role));
    }

    private function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return 'HA';
        }

        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials;
    }

    private function getRequestedStatus(): string
    {
        $status = strtolower(trim((string) ($_GET['status'] ?? 'all')));

        return in_array($status, ['all', 'critical', 'urgent', 'stable'], true)
            ? $status
            : 'all';
    }

    private function prepareAlert(array $alert): array
    {
        $status = strtolower((string) ($alert['status'] ?? 'stable'));
        if ($status === 'dispatched') {
            $status = 'urgent';
        }

        $gender = strtolower((string) ($alert['patient_gender'] ?? ''));

        // NOTE: was written as a match() expression, which requires PHP 8.0+.
        // Rewritten as a switch so this controller runs on PHP 7.4+ as well.
        switch ($gender) {
            case 'male':
                $genderLabel = 'MALE';
                break;
            case 'female':
                $genderLabel = 'FEMALE';
                break;
            case 'other':
                $genderLabel = 'OTHER';
                break;
            default:
                $genderLabel = '';
        }

        $age = $alert['patient_age'] !== null
            ? (int) $alert['patient_age']
            : null;

        $eta = $alert['eta_minutes'] !== null
            ? (int) $alert['eta_minutes']
            : null;

        $patientInfo = '';

        if ($genderLabel !== '' && $age !== null) {
            $patientInfo = $genderLabel . ', ' . $age;

            if ($eta !== null) {
                $patientInfo .= ' · ETA ' . $eta . ' MINS';
            } elseif ($status === 'stable') {
                $patientInfo .= ' · VITALS STABLE';
            }
        }

        return [
            'id' => (int) ($alert['incident_id'] ?? 0),

            'status' => ucfirst($status),

            'incidentType' => (string) (
                $alert['incident_type'] ?? 'Unknown Incident'
            ),

            'patientInfo' => $patientInfo,

            'location' => (string) (
                $alert['location'] ?? 'Unknown Location'
            ),

            'barangay' => (string) ($alert['barangay_name'] ?? ''),

            'patientName' => (string) ($alert['patient_name'] ?? ''),

            'gender' => $genderLabel,

            'age' => $age,

            'latitude' => $alert['latitude'] ?? null,

            'longitude' => $alert['longitude'] ?? null,

            'hospital' => (string) (
                $alert['hospital_name'] ?? ''
            ),

            'timeAgo' => $this->formatTimeAgo(
                $alert['reported_at'] ?? null
            ),

            'responder' => (string) (
                $alert['assigned_responder_name'] ?? ''
            ),

            'transport' => (string) ($alert['transport_name'] ?? ''),

            'transportType' => (string) ($alert['transport_type'] ?? ''),

            'driver' => (string) ($alert['driver_display_name'] ?? ''),

            'eta' => $eta !== null
                ? $eta . ' mins'
                : '',

            'notes' => (string) (
                $alert['notes'] ?? ''
            ),

            'reportedAt' => (string) (
                $alert['reported_at'] ?? ''
            ),

            'resolvedAt' => $alert['resolved_at'] ?? null,

            'createdAt' => (string) ($alert['created_at'] ?? ''),

            'updatedAt' => (string) ($alert['updated_at'] ?? ''),

            'vitals' => $this->prepareVitals($alert),

            'nurseNotes' => (string) ($alert['nurse_notes'] ?? ''),

            'nursePrep' => !empty($alert['nurse_prep_completed'])
                ? 'Completed' . ($alert['nurse_prep_completed_at'] ? ' ' . $alert['nurse_prep_completed_at'] : '')
                : 'Not completed',

            'action' => $status === 'critical'
                ? 'Notify Staff'
                : 'View Details',
        ];
    }

    /**
     * Nurse-recorded vitals as one readable line, or an empty string when none were taken.
     */
    private function prepareVitals(array $alert): string
    {
        $parts = [];
        if ($alert['nurse_heart_rate'] !== null) {
            $parts[] = 'HR ' . (int) $alert['nurse_heart_rate'] . ' bpm';
        }
        if (!empty($alert['nurse_blood_pressure'])) {
            $parts[] = 'BP ' . $alert['nurse_blood_pressure'];
        }
        if ($alert['nurse_spo2'] !== null) {
            $parts[] = 'SpO2 ' . (int) $alert['nurse_spo2'] . '%';
        }
        if ($alert['nurse_gcs'] !== null) {
            $parts[] = 'GCS ' . (int) $alert['nurse_gcs'];
        }

        return implode(' · ', $parts);
    }

    /**
     * NOTE: the `mixed` type hint requires PHP 8.0+. Dropped the type
     * hint entirely (still works exactly the same, just untyped) so
     * this runs on PHP 7.4+ too.
     */
    private function formatTimeAgo($value): string
    {
        if (is_numeric($value)) {
            $minutes = max(0, (int) $value);
        } elseif (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            $minutes = $timestamp ? max(0, (int) floor((time() - $timestamp) / 60)) : 0;
        } else {
            $minutes = 0;
        }

        if ($minutes < 1) {
            return 'Just now';
        }

        if ($minutes < 60) {
            return $minutes . 'm ago';
        }

        $hours = (int) floor($minutes / 60);

        if ($hours < 24) {
            return $hours . 'h ago';
        }

        return (int) floor($hours / 24) . 'd ago';
    }

    /**
     * NOTE: the `: never` return type requires PHP 8.1+. Changed to
     * `: void` (behaves identically here since every call path ends in
     * exit;) so this runs on PHP 7.4+ too.
     */
    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$controller = new EmergencyAlertController();

$action = strtolower(trim((string) ($_GET['action'] ?? 'index')));

switch ($action) {
    case 'data':
        $controller->data();
        break;

    case 'notify':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }
        $controller->notifyStaff();
        break;

    case 'details':
        $controller->details();
        break;

    case 'status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }
        $controller->updateStatus();
        break;

    default:
        $controller->index();
        break;
}
?>