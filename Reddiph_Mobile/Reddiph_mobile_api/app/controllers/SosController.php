<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/DispatchModel.php';
require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../models/ResponderIncidentWorkflowModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';

/**
 * JSON controller that turns an SOS press into an incident for the admin dashboards.
 */
final class SosController extends ApiController
{
    public static function preview(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }
        $reporter = UserModel::findReporter($userId);
        if ($reporter === null || $reporter['role'] !== 'resident') {
            self::respond(403, ['success' => false, 'message' => 'Only residents can preview a hospital assignment.']);
        }
        $errors = IncidentModel::validateSos($data);
        if ($errors !== []) {
            self::respond(422, ['success' => false, 'message' => $errors[0]]);
        }

        try {
            $latitude = (float) $data['latitude'];
            $longitude = (float) $data['longitude'];
            $hospital = $reporter['hospital_id'] !== null
                ? IncidentModel::hospitalById((int) $reporter['hospital_id'])
                : IncidentModel::nearestHospital($latitude, $longitude);
            if ($hospital === null) {
                self::respond(422, ['success' => false, 'message' => 'No hospital with map coordinates is available.']);
            }
            $distanceKm = $hospital['latitude'] !== null && $hospital['longitude'] !== null
                ? DispatchModel::distanceKm(
                    $latitude,
                    $longitude,
                    $hospital['latitude'],
                    $hospital['longitude']
                )
                : null;

            self::respond(200, [
                'success' => true,
                'hospital' => $hospital,
                'estimated_road_distance_km' => $distanceKm === null
                    ? null
                    : round($distanceKm * 1.3, 1),
                'eta_minutes' => $distanceKm === null
                    ? null
                    : DispatchModel::etaMinutes(
                        $latitude,
                        $longitude,
                        $hospital['latitude'],
                        $hospital['longitude']
                    ),
            ]);
        } catch (Throwable $e) {
            self::fail('resident hospital preview', $e, 'Unable to load the hospital assignment.');
        }
    }

    public static function create(): void
    {
        self::requireMethod('POST');
        $data = $_POST !== [] ? $_POST : self::readJsonBody();
        $storedPatientPhotos = [];

        $errors = IncidentModel::validateSos($data);
        if ($errors !== []) {
            self::respond(422, ['success' => false, 'message' => $errors[0]]);
        }

        try {
            $latitude = (float) $data['latitude'];
            $longitude = (float) $data['longitude'];
            $reporter = null;
            $userId = null;
            $residentReport = array_key_exists('patient_name', $data)
                || array_key_exists('patients', $data)
                || array_key_exists('volunteer_user_id', $data);
            // A token that is sent must be genuine; no token means an anonymous SOS.
            if (isset($data['token'])) {
                $userId = TokenService::verify($data['token']);
                if ($userId === null) {
                    self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
                }
                $reporter = UserModel::findReporter($userId);
                if ($reporter !== null && $reporter['banned_until'] !== null
                    && strtotime($reporter['banned_until']) > time()) {
                    self::respond(403, [
                        'success' => false,
                        'message' => 'Your account is temporarily suspended until '
                            . $reporter['banned_until'] . '. Contact your Barangay Admin for help.',
                    ]);
                }
            }

            $patientReport = [];
            if ($residentReport) {
                if ($reporter === null || $reporter['role'] !== 'resident') {
                    self::respond(403, ['success' => false, 'message' => 'Only residents can submit a patient report.']);
                }
                foreach (['incident_type', 'status'] as $field) {
                    if (!is_string($data[$field] ?? null)) {
                        self::respond(422, ['success' => false, 'message' => 'Enter valid patient and incident details.']);
                    }
                }
                $reportedBy = $data['reported_by'] ?? 'resident_sos';
                if (!is_string($reportedBy)
                    || !in_array($reportedBy, ['on_scene', 'resident_sos'], true)) {
                    self::respond(422, ['success' => false, 'message' => 'Choose who reported the incident.']);
                }
                $incidentType = trim($data['incident_type']);
                $status = $data['status'];
                if ($incidentType === '' || strlen($incidentType) > 120
                    || !in_array($status, ['critical', 'urgent', 'stable'], true)) {
                    self::respond(422, ['success' => false, 'message' => 'Enter valid patient and incident details.']);
                }
                $patients = $data['patients'] ?? null;
                if (is_string($patients)) {
                    $patients = json_decode($patients, true);
                }
                if ($patients === null && isset($data['patient_name'])) {
                    $patients = [[
                        'patient_name' => $data['patient_name'],
                        'patient_age' => $data['patient_age'] ?? null,
                        'patient_gender' => $data['patient_gender'] ?? null,
                        'heart_rate' => $data['heart_rate'] ?? null,
                        'spo2' => $data['spo2'] ?? null,
                        'blood_pressure' => $data['blood_pressure'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'scene_conditions' => $data['scene_conditions'] ?? [],
                    ]];
                }
                if (!is_array($patients) || $patients === []
                    || count($patients) > 20
                    || array_values($patients) !== $patients) {
                    self::respond(422, ['success' => false, 'message' => 'Add between one and 20 patients to this report.']);
                }
                $patientRecords = [];
                foreach ($patients as $patient) {
                    if (!is_array($patient)) {
                        self::respond(422, ['success' => false, 'message' => 'Patient details are invalid.']);
                    }
                    if (!is_string($patient['patient_name'] ?? null)
                        || !is_string($patient['notes'] ?? '') 
                        || !is_string($patient['blood_pressure'] ?? '')) {
                        self::respond(422, ['success' => false, 'message' => 'Enter valid details for every patient.']);
                    }
                    $patientName = trim($patient['patient_name']);
                    $ageValue = $patient['patient_age'] ?? null;
                    if (!is_scalar($ageValue)) {
                        self::respond(422, ['success' => false, 'message' => 'Enter valid details for every patient.']);
                    }
                    $patientAge = filter_var($ageValue, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 0, 'max_range' => 130],
                    ]);
                    $patientGender = $patient['patient_gender'] ?? null;
                    $notes = trim($patient['notes'] ?? '');
                    $bloodPressure = trim($patient['blood_pressure'] ?? '');
                    if ($patientName === '' || strlen($patientName) > 160
                        || $patientAge === false
                        || !is_string($patientGender)
                        || !in_array($patientGender, ['male', 'female', 'other', 'unknown'], true)
                        || strlen($notes) > 10000 || strlen($bloodPressure) > 15) {
                        self::respond(422, ['success' => false, 'message' => 'Enter valid details for every patient.']);
                    }
                    try {
                        $sceneConditions = ResponderIncidentWorkflowModel::normalizeSceneConditions(
                            $patient['scene_conditions'] ?? []
                        );
                    } catch (InvalidArgumentException $exception) {
                        self::respond(422, ['success' => false, 'message' => $exception->getMessage()]);
                    }
                    $patientRecords[] = [
                        'name' => $patientName,
                        'age' => $patientAge,
                        'gender' => $patientGender,
                        'heart_rate' => self::optionalInteger($patient['heart_rate'] ?? null, 0, 65535),
                        'spo2' => self::optionalInteger($patient['spo2'] ?? null, 0, 100),
                        'blood_pressure' => $bloodPressure !== '' ? $bloodPressure : null,
                        'notes' => $notes !== '' ? $notes : null,
                        'scene_conditions' => $sceneConditions,
                        'photo_filename' => null,
                    ];
                }
                $requestAnotherVehicle = $data['request_another_vehicle'] ?? '0';
                if (!in_array($requestAnotherVehicle, ['0', '1', 0, 1, false, true], true)) {
                    self::respond(422, ['success' => false, 'message' => 'Choose a valid vehicle request option.']);
                }
                $patientReport = [
                    'incident_type' => $incidentType,
                    'status' => $status,
                    'reported_by' => $reportedBy,
                    'request_another_vehicle' => (bool) $requestAnotherVehicle,
                    'patient_records' => $patientRecords,
                    'patient_name' => $patientRecords[0]['name'],
                    'patient_age' => $patientRecords[0]['age'],
                    'patient_gender' => $patientRecords[0]['gender'],
                    'notes' => 'Resident report sent from the Reddiph mobile app.',
                ];
                if (array_key_exists('volunteer_user_id', $data)
                    && $data['volunteer_user_id'] !== null) {
                    $volunteerId = filter_var($data['volunteer_user_id'], FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1],
                    ]);
                    if ($volunteerId === false) {
                        self::respond(422, ['success' => false, 'message' => 'Choose a valid volunteer.']);
                    }
                    $patientReport['volunteer_user_id'] = $volunteerId;
                }
                foreach ($patientReport['patient_records'] as $index => &$record) {
                    $photo = $_FILES['photo_' . $index] ?? ($index === 0 ? ($_FILES['photo'] ?? null) : null);
                    $filename = self::storePatientPhoto($photo);
                    $record['photo_filename'] = $filename;
                    if ($filename !== null) {
                        $storedPatientPhotos[] = $filename;
                    }
                }
                unset($record);
            }

            // A known reporter keeps their own hospital; anyone else gets the nearest one.
            $hospital = $reporter !== null && $reporter['hospital_id'] !== null
                ? IncidentModel::hospitalById((int) $reporter['hospital_id'])
                : IncidentModel::nearestHospital($latitude, $longitude);

            $barangay = $transport = $responder = $responderName = $eta = null;
            if ($reporter !== null && $reporter['barangay_id'] !== null) {
                $barangay = DispatchModel::barangayById((int) $reporter['barangay_id']);
            }
            if ($hospital !== null) {
                $barangay ??= DispatchModel::nearestBarangay($hospital['id'], $latitude, $longitude);
            }

            $pdo = Database::connection();
            $pdo->beginTransaction();
            if ($userId !== null) {
                $pdo->prepare('SELECT id FROM user_type WHERE id = ? FOR UPDATE')
                    ->execute([$userId]);
                $activeIncidentId = IncidentModel::activeForReporter($userId);
                if ($activeIncidentId !== null) {
                    if ($residentReport) {
                        $pdo->rollBack();
                        foreach ($storedPatientPhotos as $filename) {
                            self::deletePatientPhoto($filename);
                        }
                        self::respond(409, [
                            'success' => false,
                            'message' => 'You already have an active incident. You can report for someone else after it is closed.',
                        ]);
                    }
                    $pdo->commit();
                    self::respond(200, [
                        'success' => true,
                        'incident_id' => $activeIncidentId,
                        'existing' => true,
                    ]);
                }
            }
            $responder = DispatchModel::availableResponder(
                $barangay['id'] ?? null,
                $userId,
                $latitude,
                $longitude
            );
            $responderName = $responder['full_name'] ?? null;
            if ($responder !== null && $responder['hospital_id'] !== null) {
                $transport = DispatchModel::availableTransport($responder['hospital_id']);
            } elseif ($hospital !== null) {
                $transport = DispatchModel::availableTransport((int) $hospital['id']);
            }
            if ($responder !== null
                && $responder['latitude'] !== null
                && $responder['longitude'] !== null) {
                $eta = DispatchModel::etaMinutes(
                    $responder['latitude'],
                    $responder['longitude'],
                    $latitude,
                    $longitude
                );
            } elseif ($hospital !== null
                && $hospital['latitude'] !== null
                && $hospital['longitude'] !== null) {
                $eta = DispatchModel::etaMinutes(
                    $hospital['latitude'],
                    $hospital['longitude'],
                    $latitude,
                    $longitude
                );
            }

            $incidentId = IncidentModel::createSos([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'reporter' => $reporter,
                'reporter_user_id' => $userId,
                'hospital' => $hospital,
                'barangay' => $barangay,
                'transport' => $transport,
                'responder' => $responder,
                'responder_name' => $responderName,
                'eta_minutes' => $eta,
            ] + $patientReport);
            $pdo->commit();

            self::respond(201, ['success' => true, 'incident_id' => $incidentId]);
        } catch (InvalidArgumentException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($storedPatientPhotos as $filename) {
                self::deletePatientPhoto($filename);
            }
            self::respond(422, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($storedPatientPhotos as $filename) {
                self::deletePatientPhoto($filename);
            }
            self::fail('sos', $e, 'Unable to send the SOS alert.');
        }
    }

    private static function optionalInteger(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            self::respond(422, ['success' => false, 'message' => 'Patient vital signs are invalid.']);
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
        if ($number === false) {
            self::respond(422, ['success' => false, 'message' => 'Patient vital signs are invalid.']);
        }

        return $number;
    }

    private static function storePatientPhoto(?array $photo): ?string
    {
        if ($photo === null || ($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($photo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
            || !isset($photo['tmp_name'], $photo['size'])
            || (int) $photo['size'] > 5242880
            || !is_uploaded_file($photo['tmp_name'])) {
            throw new InvalidArgumentException('Patient photo failed to upload or exceeded 5 MB.');
        }
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
        ];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('Use a JPEG, PNG, WebP or HEIC patient photo.');
        }

        $directory = dirname(__DIR__, 2) . '/private_uploads/incident_patients';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Patient photo storage could not be created.');
        }
        $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($photo['tmp_name'], $directory . '/' . $filename)) {
            throw new RuntimeException('Patient photo could not be stored.');
        }

        return $filename;
    }

    private static function deletePatientPhoto(string $filename): void
    {
        if (!preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp|heic|heif)\z/', $filename)) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/private_uploads/incident_patients/' . $filename;
        if (is_file($path) && !unlink($path)) {
            error_log('Mobile API: unable to remove a resident patient photo.');
        }
    }

    /** Returns the live details of an incident to the person who reported it. */
    public static function status(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();

        $incidentId = filter_var($data['incident_id'] ?? null, FILTER_VALIDATE_INT);
        if ($incidentId === false || $incidentId < 1) {
            self::respond(422, ['success' => false, 'message' => 'A valid incident is required.']);
        }

        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        try {
            $details = IncidentModel::details($incidentId);
            if ($details === null) {
                self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
            }

            if ($details['reporter_user_id'] === null
                || (int) $details['reporter_user_id'] !== $userId) {
                self::respond(403, ['success' => false, 'message' => 'This incident belongs to another user.']);
            }
            unset($details['reporter_user_id']);
            unset($details['patient_name']);

            self::respond(200, ['success' => true, 'incident' => $details]);
        } catch (Throwable $e) {
            self::fail('sos status', $e, 'Unable to load the emergency details.');
        }
    }

    /** Returns the authenticated resident's current open SOS, if any. */
    public static function active(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        try {
            $incidentId = IncidentModel::activeForReporter($userId);
            $incident = $incidentId === null ? null : IncidentModel::details($incidentId);
            if ($incident !== null) {
                unset($incident['reporter_user_id'], $incident['patient_name']);
            }
            self::respond(200, ['success' => true, 'incident' => $incident]);
        } catch (Throwable $e) {
            self::fail('active SOS', $e, 'Unable to load your active emergency.');
        }
    }

    /** Returns every incident submitted by the authenticated resident. */
    public static function history(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        $reporter = UserModel::findReporter($userId);
        if ($reporter === null || $reporter['role'] !== 'resident') {
            self::respond(403, ['success' => false, 'message' => 'Only residents can view report history.']);
        }

        try {
            self::respond(200, [
                'success' => true,
                'incidents' => IncidentModel::historyForReporter($userId),
            ]);
        } catch (Throwable $e) {
            self::fail('resident incident history', $e, 'Unable to load your report history.');
        }
    }
}
