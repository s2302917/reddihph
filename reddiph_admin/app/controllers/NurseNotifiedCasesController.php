<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/NurseNotifiedCasesModel.php';

requireAuthenticated('nurse');

class NurseNotifiedCasesController
{
    private function nurseId(): int
    {
        return (int) $_SESSION['user_id'];
    }

    public function index(): void
    {
        $pageTitle = 'Notified Cases';
        $currentPage = 'notified-cases';
        $nurse = NurseNotifiedCasesModel::profile($this->nurseId());
        if (!$nurse) {
            http_response_code(404);
            exit('Nurse account not found.');
        }

        $_SESSION['nurse_cases_csrf_token'] ??= bin2hex(random_bytes(32));
        $_SESSION['profile_image'] = $nurse['profile_image'] ?? null;
        $cases = NurseNotifiedCasesModel::cases($this->nurseId());
        $summary = NurseNotifiedCasesModel::summary($this->nurseId());
        $nextArrival = NurseNotifiedCasesModel::nextArrival($this->nurseId());
        $freeBeds = NurseNotifiedCasesModel::bedAvailability($this->nurseId());
        $beds = NurseNotifiedCasesModel::beds($this->nurseId());

        require __DIR__ . '/../views/NurseNotifiedCasesView.php';
    }

    public function refresh(): void
    {
        try {
            $this->jsonResponse([
                'success' => true,
                'nurse' => NurseNotifiedCasesModel::profile($this->nurseId()),
                'cases' => NurseNotifiedCasesModel::cases($this->nurseId()),
                'summary' => NurseNotifiedCasesModel::summary($this->nurseId()),
                'nextArrival' => NurseNotifiedCasesModel::nextArrival($this->nurseId()),
                'freeBeds' => NurseNotifiedCasesModel::bedAvailability($this->nurseId()),
                'beds' => NurseNotifiedCasesModel::beds($this->nurseId()),
            ]);
        } catch (Throwable $exception) {
            error_log('[NurseNotifiedCasesController] Case refresh failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Notified cases could not be refreshed. Please try again.'], 500);
        }
    }

    public function updateAvailability(): void
    {
        $this->validateCsrf();
        $requestedStatus = $_POST['availability'] ?? null;
        if (!in_array($requestedStatus, ['on', 'off'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose a valid duty status.'], 422);
        }

        try {
            $available = $requestedStatus === 'on';
            NurseNotifiedCasesModel::setAvailability($this->nurseId(), $available);
            $this->jsonResponse([
                'success' => true,
                'available' => $available,
                'message' => $available ? 'You are now on duty.' : 'You are now off duty.',
            ]);
        } catch (Throwable $exception) {
            error_log('[NurseNotifiedCasesController] Duty status update failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Duty status could not be updated. Please try again.'], 500);
        }
    }

    public function updatePreparation(): void
    {
        $this->validateCsrf();
        $incidentId = filter_var($_POST['incident_id'] ?? null, FILTER_VALIDATE_INT);
        if ($incidentId === false || $incidentId === null || $incidentId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose a valid incident.'], 422);
        }
        $bedInput = trim((string) ($_POST['bed_id'] ?? ''));
        $bedId = $bedInput === '' ? null : filter_var($bedInput, FILTER_VALIDATE_INT);
        if ($bedId === false || ($bedId !== null && $bedId <= 0)) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose a valid hospital bed.'], 422);
        }

        $heartRate = trim((string) ($_POST['heart_rate'] ?? ''));
        $bloodPressure = trim((string) ($_POST['blood_pressure'] ?? ''));
        $spo2 = trim((string) ($_POST['spo2'] ?? ''));
        $gcs = trim((string) ($_POST['gcs'] ?? ''));
        $nurseNote = trim((string) ($_POST['nurse_note'] ?? ''));
        $prepValue = (string) ($_POST['prep_completed'] ?? '');

        if ($heartRate !== '' && (!ctype_digit($heartRate) || (int) $heartRate < 1 || (int) $heartRate > 300)) {
            $this->jsonResponse(['success' => false, 'message' => 'Enter a heart rate from 1 to 300 bpm.'], 422);
        }
        if ($bloodPressure !== '') {
            if (!preg_match('/^(\\d{1,3})\\/(\\d{1,3})$/', $bloodPressure, $bloodPressureValues)) {
                $this->jsonResponse(['success' => false, 'message' => 'Enter blood pressure as systolic/diastolic, for example 120/80.'], 422);
            }
            if ((int) $bloodPressureValues[1] < 20 || (int) $bloodPressureValues[1] > 300 ||
                (int) $bloodPressureValues[2] < 10 || (int) $bloodPressureValues[2] > 200) {
                $this->jsonResponse(['success' => false, 'message' => 'Enter a blood pressure between 20/10 and 300/200 mmHg.'], 422);
            }
        }
        if ($spo2 !== '' && (!ctype_digit($spo2) || (int) $spo2 < 0 || (int) $spo2 > 100)) {
            $this->jsonResponse(['success' => false, 'message' => 'Enter an SpO₂ value from 0 to 100%.'], 422);
        }
        if ($gcs !== '' && (!ctype_digit($gcs) || (int) $gcs < 3 || (int) $gcs > 15)) {
            $this->jsonResponse(['success' => false, 'message' => 'Enter a GCS value from 3 to 15.'], 422);
        }
        if (strlen($nurseNote) > 2000) {
            $this->jsonResponse(['success' => false, 'message' => 'Nurse notes must be 2,000 characters or fewer.'], 422);
        }
        if (!in_array($prepValue, ['0', '1'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose whether preparation is complete.'], 422);
        }

        try {
            $updated = NurseNotifiedCasesModel::updatePreparation(
                $this->nurseId(),
                (int) $incidentId,
                [
                    'heartRate' => $heartRate,
                    'bloodPressure' => $bloodPressure,
                    'spo2' => $spo2,
                    'gcs' => $gcs,
                    'nurseNote' => $nurseNote,
                    'bedId' => $bedId === null ? null : (int) $bedId,
                    'prepCompleted' => $prepValue === '1',
                ]
            );
            if (!$updated) {
                $this->jsonResponse(['success' => false, 'message' => 'This incident is no longer available to your account. Refresh the page and try again.'], 409);
            }

            $this->jsonResponse([
                'success' => true,
                'message' => $prepValue === '1' ? 'Patient preparation saved as complete.' : 'Patient preparation saved.',
                'summary' => NurseNotifiedCasesModel::summary($this->nurseId()),
                'cases' => NurseNotifiedCasesModel::cases($this->nurseId()),
                'beds' => NurseNotifiedCasesModel::beds($this->nurseId()),
                'freeBeds' => NurseNotifiedCasesModel::bedAvailability($this->nurseId()),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            error_log('[NurseNotifiedCasesController] Patient preparation update failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Patient preparation could not be saved. Please try again.'], 500);
        }
    }

    private function validateCsrf(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['nurse_cases_csrf_token']) ||
            !hash_equals((string) $_SESSION['nurse_cases_csrf_token'], $token)
        ) {
            $this->jsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $controller = new NurseNotifiedCasesController();
    $action = (string) ($_GET['action'] ?? '');

    if ($action === 'refresh' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->refresh();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'update_preparation') {
            $controller->updatePreparation();
        }
        if ($action === 'update_availability') {
            $controller->updateAvailability();
        }
    }

    if ($action !== '') {
        http_response_code(405);
        exit('Unsupported notified-cases request.');
    }

    $controller->index();
}
