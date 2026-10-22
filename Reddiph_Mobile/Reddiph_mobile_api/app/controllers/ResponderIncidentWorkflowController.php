<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/ResponderDashboardModel.php';
require_once __DIR__ . '/../models/ResponderIncidentWorkflowModel.php';
require_once __DIR__ . '/../services/TokenService.php';

/** Authenticated endpoints for patient records and responder incident actions. */
final class ResponderIncidentWorkflowController extends ApiController
{
    private const MAX_PHOTO_BYTES = 5242880;
    private const PHOTO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    public static function patients(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        [$incidentId] = self::authorizedIncident($data);
        $action = (string) ($data['action'] ?? 'list');

        try {
            if ($action === 'list') {
                self::respond(200, [
                    'success' => true,
                    'patients' => array_map(
                        static fn (array $patient): array => self::patientJson($patient),
                        ResponderIncidentWorkflowModel::patients($incidentId)
                    ),
                ]);
            }
            if ($action === 'delete') {
                $patientId = filter_var($data['patient_id'] ?? null, FILTER_VALIDATE_INT);
                if ($patientId === false || $patientId < 1) {
                    self::respond(422, ['success' => false, 'message' => 'A valid patient is required.']);
                }
                $filename = ResponderIncidentWorkflowModel::deletePatient($incidentId, $patientId);
                if ($filename === false) {
                    self::respond(404, ['success' => false, 'message' => 'Patient record not found.']);
                }
                if (is_string($filename)) {
                    self::deletePhoto($filename);
                }
                self::respond(200, ['success' => true]);
            }
            self::respond(422, ['success' => false, 'message' => 'Unknown patient action.']);
        } catch (Throwable $e) {
            if ($e instanceof PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('Mobile API responder patient schema is missing a required table or column: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'Patient report storage is not set up. Run migrations 006_responder_incident_patients.sql and 007_patient_scene_conditions.sql.',
                ]);
            }
            self::fail('responder patients', $e, 'Unable to update patient records.');
        }
    }

    public static function savePatient(): void
    {
        self::requireMethod('POST');
        $data = $_POST;
        [$incidentId] = self::authorizedIncident($data);

        $name = trim((string) ($data['patient_name'] ?? ''));
        $age = filter_var($data['patient_age'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 130]]);
        $gender = (string) ($data['patient_gender'] ?? '');
        if ($name === '' || strlen($name) > 160 || $age === false
            || !in_array($gender, ['male', 'female', 'other', 'unknown'], true)) {
            self::respond(422, ['success' => false, 'message' => 'Enter a patient name, valid age and sex.']);
        }

        $heartRate = self::optionalInteger($data['heart_rate'] ?? null, 0, 65535, 'Heart rate');
        $spo2 = self::optionalInteger($data['spo2'] ?? null, 0, 100, 'SpO2');
        $bloodPressure = trim((string) ($data['blood_pressure'] ?? ''));
        if (strlen($bloodPressure) > 15) {
            self::respond(422, ['success' => false, 'message' => 'Blood pressure is too long.']);
        }
        $notes = trim((string) ($data['notes'] ?? ''));
        if (strlen($notes) > 10000) {
            self::respond(422, ['success' => false, 'message' => 'Notes are too long.']);
        }
        try {
            $sceneConditions = ResponderIncidentWorkflowModel::normalizeSceneConditions(
                $data['scene_conditions'] ?? []
            );
        } catch (InvalidArgumentException $exception) {
            self::respond(422, ['success' => false, 'message' => $exception->getMessage()]);
        }

        $photoFilename = null;
        try {
            $photoFilename = self::storePhoto($_FILES['photo'] ?? null);
            $patientId = ResponderIncidentWorkflowModel::addPatient($incidentId, [
                'name' => $name,
                'age' => $age,
                'gender' => $gender,
                'heart_rate' => $heartRate,
                'spo2' => $spo2,
                'nurse_blood_pressure' => $bloodPressure !== '' ? $bloodPressure : null,
                'notes' => $notes !== '' ? $notes : null,
                'scene_conditions' => $sceneConditions,
                'photo_filename' => $photoFilename,
            ]);
            self::respond(201, [
                'success' => true,
                'patient' => [
                    'id' => $patientId,
                    'patient_name' => $name,
                    'patient_age' => $age,
                    'patient_gender' => $gender,
                    'heart_rate' => $heartRate,
                    'spo2' => $spo2,
                    'nurse_blood_pressure' => $bloodPressure !== '' ? $bloodPressure : null,
                    'notes' => $notes !== '' ? $notes : null,
                    'scene_conditions' => $sceneConditions,
                    'has_photo' => $photoFilename !== null,
                ],
            ]);
        } catch (Throwable $e) {
            if ($photoFilename !== null) {
                self::deletePhoto($photoFilename);
            }
            self::fail('responder patient save', $e, 'Unable to save this patient.');
        }
    }

    public static function action(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        [$incidentId, $profile, $userId] = self::authorizedIncident($data);
        $action = (string) ($data['action'] ?? '');

        try {
            if ($action === 'mark_scene_arrived') {
                try {
                    $success = ResponderIncidentWorkflowModel::markSceneArrived(
                        $incidentId,
                        $profile['hospital_id'],
                        (string) $profile['full_name'],
                        $userId
                    );
                } catch (IncidentSceneArrivalUnavailableException $e) {
                    self::respond(409, ['success' => false, 'message' => $e->getMessage()]);
                }
                if (!$success) {
                    self::respond(409, [
                        'success' => false,
                        'message' => 'This incident is no longer active.',
                    ]);
                }
                self::respond(200, ['success' => true, 'scene_arrived' => true]);
            }
            if ($action === 'update_incident_type') {
                $incidentType = trim((string) ($data['incident_type'] ?? ''));
                if ($incidentType === '' || strlen($incidentType) > 120) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'Enter a valid incident type (up to 120 characters).',
                    ]);
                }
                $updated = ResponderIncidentWorkflowModel::updateIncidentType(
                    $incidentId,
                    $userId,
                    $profile['hospital_id'],
                    $profile['full_name'],
                    $incidentType
                );
                if (!$updated) {
                    self::respond(409, [
                        'success' => false,
                        'message' => 'This incident is no longer assigned to you.',
                    ]);
                }
                self::respond(200, [
                    'success' => true,
                    'incident_type' => $incidentType,
                ]);
            }
            if ($action === 'send_to_hospital') {
                $status = (string) ($data['status'] ?? '');
                if (!in_array($status, ['critical', 'urgent', 'stable'], true)) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'Choose Critical, Urgent or Stable for the incident condition.',
                    ]);
                }
                $volunteerUserId = null;
                if (isset($data['volunteer_user_id'])) {
                    $volunteerUserId = filter_var(
                        $data['volunteer_user_id'],
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );
                    if ($volunteerUserId === false) {
                        self::respond(422, [
                            'success' => false,
                            'message' => 'Choose a valid volunteer.',
                        ]);
                    }
                }
                $result = ResponderIncidentWorkflowModel::sendIncidentToHospital(
                    $incidentId,
                    $userId,
                    $profile['hospital_id'],
                    $profile['full_name'],
                    $status,
                    $volunteerUserId === null ? null : (int) $volunteerUserId
                );
                self::respond(200, ['success' => true] + $result);
            }
            if ($action === 'transport_arrived') {
                $arrival = ResponderIncidentWorkflowModel::markTransportArrived(
                    $incidentId,
                    $userId,
                    (int) ($profile['hospital_id'] ?? 0),
                    (string) $profile['full_name']
                );
                if ($arrival === false) {
                    self::respond(409, [
                        'success' => false,
                        'message' => 'This incident is no longer active.',
                    ]);
                }
                self::respond(200, [
                    'success' => true,
                    'arrived' => true,
                    'status' => 'arrived',
                    'arrival' => $arrival,
                ]);
            }
            if ($action === 'thank_volunteer') {
                $rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 5],
                ]);
                if ($rating === false) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'Choose a rating from 1 to 5.',
                    ]);
                }
                ResponderIncidentWorkflowModel::thankVolunteer(
                    $incidentId,
                    $userId,
                    $profile['hospital_id'],
                    (string) $profile['full_name'],
                    $rating
                );
                self::respond(201, ['success' => true, 'thanks_sent' => true]);
            }
            if ($action === 'update_transport_location') {
                $latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
                $longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($latitude === false || $longitude === false) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'Valid GPS coordinates are required.',
                    ]);
                }
                $result = ResponderIncidentWorkflowModel::updateTransportLocation(
                    $incidentId,
                    $userId,
                    (int) ($profile['hospital_id'] ?? 0),
                    (string) $profile['full_name'],
                    (float) $latitude,
                    (float) $longitude
                );
                self::respond($result['success'] ? 200 : 409, $result);
            }
            if ($action === 'patient_picked_up') {
                if (ResponderIncidentWorkflowModel::patients($incidentId) === []) {
                    self::respond(422, ['success' => false, 'message' => 'Add at least one patient before confirming pickup.']);
                }
                ResponderIncidentWorkflowModel::markPickedUp(
                    $incidentId,
                    $userId,
                    $profile['full_name']
                );
                self::respond(200, ['success' => true, 'status' => 'dispatched']);
            }
            if ($action === 'request_vehicle') {
                $requestId = ResponderIncidentWorkflowModel::requestVehicle($incidentId, $userId);
                self::respond(200, ['success' => true, 'request_id' => $requestId]);
            }
            if ($action === 'update_notes') {
                $note = $data['notes'] ?? null;
                if (!is_string($note) || trim($note) === '' || strlen(trim($note)) > 2000) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'Enter an update with no more than 2000 characters.',
                    ]);
                }
                ResponderIncidentWorkflowModel::addIncidentNoteUpdate(
                    $incidentId,
                    $userId,
                    $profile['hospital_id'],
                    $profile['full_name'],
                    trim($note)
                );
                self::respond(201, ['success' => true, 'message' => 'Hospital staff were notified.']);
            }
            self::respond(422, ['success' => false, 'message' => 'Unknown incident action.']);
        } catch (InvalidArgumentException $e) {
            self::respond(422, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            self::fail('responder incident action', $e, 'Unable to complete that incident action.');
        }
    }

    private static function authorizedIncident(array $data): array
    {
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }
        $incidentId = filter_var($data['incident_id'] ?? null, FILTER_VALIDATE_INT);
        if ($incidentId === false || $incidentId < 1) {
            self::respond(422, ['success' => false, 'message' => 'A valid incident is required.']);
        }
        try {
            $profile = ResponderDashboardModel::profile((int) $userId);
            if ($profile === null) {
                self::respond(403, ['success' => false, 'message' => 'This account is not a responder.']);
            }
            if (!ResponderIncidentWorkflowModel::belongsToResponder(
                $incidentId,
                (int) $userId,
                $profile['hospital_id'],
                $profile['full_name']
            )) {
                self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
            }
        } catch (Throwable $e) {
            self::fail('responder incident authorization', $e, 'Unable to verify this incident.');
        }

        return [(int) $incidentId, $profile, (int) $userId];
    }

    private static function optionalInteger(mixed $value, int $min, int $max, string $label): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($number === false) {
            self::respond(422, ['success' => false, 'message' => "{$label} is invalid."]);
        }

        return $number;
    }

    private static function storePhoto(?array $photo): ?string
    {
        if ($photo === null || ($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($photo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
            || !isset($photo['tmp_name'], $photo['size'])
            || (int) $photo['size'] > self::MAX_PHOTO_BYTES
            || !is_uploaded_file($photo['tmp_name'])) {
            self::respond(422, ['success' => false, 'message' => 'Photo upload failed or exceeded 5 MB.']);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
        if (!isset(self::PHOTO_MIMES[$mime])) {
            self::respond(422, ['success' => false, 'message' => 'Use a JPEG, PNG, WebP or HEIC photo.']);
        }

        $directory = dirname(__DIR__, 2) . '/private_uploads/incident_patients';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Incident photo storage could not be created.');
        }
        $filename = bin2hex(random_bytes(24)) . '.' . self::PHOTO_MIMES[$mime];
        if (!move_uploaded_file($photo['tmp_name'], $directory . '/' . $filename)) {
            throw new RuntimeException('Incident photo could not be stored.');
        }

        return $filename;
    }

    private static function deletePhoto(string $filename): void
    {
        if (!preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp|heic|heif)\z/', $filename)) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/private_uploads/incident_patients/' . $filename;
        if (is_file($path) && !unlink($path)) {
            error_log('Mobile API: unable to remove an incident patient photo.');
        }
    }

    private static function patientJson(array $patient): array
    {
        return [
            'id' => (int) $patient['id'],
            'patient_name' => $patient['patient_name'],
            'patient_age' => (int) $patient['patient_age'],
            'patient_gender' => $patient['patient_gender'],
            'heart_rate' => $patient['heart_rate'] === null ? null : (int) $patient['heart_rate'],
            'spo2' => $patient['spo2'] === null ? null : (int) $patient['spo2'],
            'nurse_blood_pressure' => $patient['nurse_blood_pressure'],
            'notes' => $patient['notes'],
            'scene_conditions' => $patient['scene_conditions'] ?? [],
            'has_photo' => $patient['photo_filename'] !== null,
        ];
    }

}
