<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/hospitalnetworkModel.php';

requireAuthenticated('hospital_admin');

class HospitalnetworkController
{
    private HospitalnetworkModel $model;

    public function __construct()
    {
        $this->model = new HospitalnetworkModel();
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $profile = $this->model->getUserProfile((int) ($_SESSION['user_id'] ?? 0)) ?: [];
        $hospitalName = $_SESSION['hospital_name'] ?? 'DSB District Hospital';
        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator');
        $coordinatorRole = $profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin');
        $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
        $coordinatorImage = userProfileImageUrl($_SESSION['profile_image'] ?? null);
        $hospitals = $this->model->getHospitals();
        $recentHospitals = $this->model->getRecentHospitals();
        $coordinatorInitials = $this->initials($coordinatorName);
        $incidents = $this->model->getReferralCandidates($hospitalId);
        $requestedIncidentId = filter_input(INPUT_GET, 'incident_id', FILTER_VALIDATE_INT) ?: 0;

        require __DIR__ . '/../views/hospitalnetworkView.php';
    }

    public function refer(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $incidentId = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
        $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if (!$incidentId || !$hospitalId || $reason === '') {
            $this->json(['success' => false, 'message' => 'Please select a hospital and provide a referral reason.'], 422);
        }

        $referralId = $this->model->createReferral((int) $incidentId, (int) $hospitalId, $reason, isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
        $referral = $referralId !== null ? $this->model->getReferralStatus($referralId) : null;
        $success = $referralId !== null && $referral !== null;
        $this->json([
            'success' => $success,
            'referralId' => $referralId,
            'referral' => $referral,
            'message' => $success ? 'Referral sent successfully.' : 'Unable to create referral.'
        ], $success ? 201 : 500);
    }

    private function json(array $payload, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach (array_slice($words ?: [], 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials !== '' ? $initials : 'HA';
    }
}

$controller = new HospitalnetworkController();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refer') {
    $controller->refer();
}

$controller->index();