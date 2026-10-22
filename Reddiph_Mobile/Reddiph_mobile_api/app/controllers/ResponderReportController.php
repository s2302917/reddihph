<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/DispatchModel.php';
require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../models/ResponderDashboardModel.php';
require_once __DIR__ . '/../models/ResponderIncidentWorkflowModel.php';
require_once __DIR__ . '/../services/TokenService.php';

/** Creates an incident report from the responder Incoming page. */
final class ResponderReportController extends ApiController
{
    private const STATUSES = ['critical', 'urgent', 'stable'];
    private const REPORTERS = ['on_scene', 'resident_sos'];

    public static function preview(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        [$latitude, $longitude] = self::coordinates($data);

        try {
            [$profile, $hospital] = self::resolveAssignment((int) $userId, $latitude, $longitude);
            if ($profile === null) {
                self::respond(403, ['success' => false, 'message' => 'This account is not a responder.']);
            }
            if ($hospital === null) {
                self::respond(422, ['success' => false, 'message' => 'No hospital is assigned to this incident location.']);
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
            self::fail('responder report preview', $e, 'Unable to load the hospital assignment.');
        }
    }

    public static function create(): void
    {
        self::requireMethod('POST');
        $data = $_POST;
        $volunteerUserId = null;
        if (isset($data['volunteer_user_id']) && $data['volunteer_user_id'] !== '') {
            $volunteerUserId = filter_var(
                $data['volunteer_user_id'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($volunteerUserId === false) {
                self::respond(422, ['success' => false, 'message' => 'Choose a valid volunteer.']);
            }
        }

        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        [$latitude, $longitude] = self::coordinates($data);

        $type = trim((string) ($data['incident_type'] ?? ''));
        if ($type === '' || strlen($type) > 120) {
            self::respond(422, ['success' => false, 'message' => 'Choose an incident type or enter a specific type.']);
        }
        $status = (string) ($data['status'] ?? '');
        $reportedBy = (string) ($data['reported_by'] ?? '');
        if (!in_array($status, self::STATUSES, true) || !in_array($reportedBy, self::REPORTERS, true)) {
            self::respond(422, ['success' => false, 'message' => 'Choose a valid condition and reporter.']);
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location === '' || strlen($location) > 160) {
            self::respond(422, ['success' => false, 'message' => 'Pin a valid incident location.']);
        }

        $patients = json_decode((string) ($data['patients'] ?? ''), true);
        if (!is_array($patients) || $patients === []) {
            self::respond(422, ['success' => false, 'message' => 'Add at least one patient before confirming pickup.']);
        }
        $normalizedPatients = [];
        try {
            foreach ($patients as $index => $patient) {
                if (!is_array($patient)) {
                    self::respond(422, ['success' => false, 'message' => 'Patient details are invalid.']);
                }
                $name = trim((string) ($patient['patient_name'] ?? ''));
                $age = filter_var($patient['patient_age'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 130],
                ]);
                $gender = (string) ($patient['patient_gender'] ?? '');
                $bloodPressure = trim((string) ($patient['blood_pressure'] ?? ''));
                $notes = trim((string) ($patient['notes'] ?? ''));
                $sceneConditions = ResponderIncidentWorkflowModel::normalizeSceneConditions(
                    $patient['scene_conditions'] ?? []
                );
                if ($name === '' || strlen($name) > 160 || $age === false
                    || !in_array($gender, ['male', 'female', 'other', 'unknown'], true)
                    || strlen($bloodPressure) > 15 || strlen($notes) > 10000) {
                    self::respond(422, ['success' => false, 'message' => 'Enter valid details for every patient.']);
                }
                $normalizedPatients[] = [
                    'name' => $name,
                    'age' => $age,
                    'gender' => $gender,
                    'heart_rate' => self::optionalInteger($patient['heart_rate'] ?? null, 0, 65535),
                    'spo2' => self::optionalInteger($patient['spo2'] ?? null, 0, 100),
                    'nurse_blood_pressure' => $bloodPressure !== '' ? $bloodPressure : null,
                    'notes' => $notes !== '' ? $notes : null,
                    'scene_conditions' => $sceneConditions,
                    'photo_filename' => null,
                    'upload_index' => $index,
                ];
            }
        } catch (InvalidArgumentException $e) {
            self::respond(422, ['success' => false, 'message' => $e->getMessage()]);
        }

        $storedPhotos = [];
        try {
            [$profile, $hospital, $barangay] = self::resolveAssignment(
                (int) $userId,
                (float) $latitude,
                (float) $longitude
            );
            if ($profile === null) {
                self::respond(403, ['success' => false, 'message' => 'This account is not a responder.']);
            }
            if ($hospital === null) {
                self::respond(422, ['success' => false, 'message' => 'No hospital is assigned to this incident location.']);
            }

            $report = [
                'incident_type' => $type,
                'reported_by' => $reportedBy,
                'status' => $status,
                'location' => $location,
                'barangay_id' => $profile['barangay_id'] ?? ($barangay['id'] ?? null),
                'hospital_id' => $hospital['id'] ?? $profile['hospital_id'],
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'eta_minutes' => $hospital['latitude'] !== null && $hospital['longitude'] !== null
                    ? DispatchModel::etaMinutes(
                        (float) $latitude,
                        (float) $longitude,
                        $hospital['latitude'],
                        $hospital['longitude']
                    )
                    : null,
                'responder_name' => $profile['full_name'],
                'hospital_name' => $hospital['name'],
                'transport_name' => $profile['vehicle']['call_sign'] ?? null,
                'transport_type' => $profile['vehicle']['vehicle_type'] ?? null,
                'driver_name' => $profile['vehicle'] === null
                    ? null
                    : $profile['full_name'],
            ];
            foreach ($normalizedPatients as $index => &$patient) {
                $uploadIndex = $patient['upload_index'];
                unset($patient['upload_index']);
                $filename = self::storeReportPhoto($_FILES['photo_' . $uploadIndex] ?? null);
                $patient['photo_filename'] = $filename;
                if ($filename !== null) {
                    $storedPhotos[] = $filename;
                }
            }
            unset($patient);

            $incidentId = ResponderIncidentWorkflowModel::createReportWithPatients(
                $report,
                $normalizedPatients,
                (int) $userId,
                ($data['request_another_vehicle'] ?? '0') === '1',
                $volunteerUserId === null ? null : (int) $volunteerUserId
            );

            self::respond(201, ['success' => true, 'incident_id' => $incidentId]);
        } catch (Throwable $e) {
            foreach ($storedPhotos as $filename) {
                self::deleteReportPhoto($filename);
            }
            if ($e instanceof InvalidArgumentException) {
                self::respond(422, ['success' => false, 'message' => $e->getMessage()]);
            }
            if ($e instanceof PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('Mobile API responder report schema is missing a required table or column: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'Patient report storage is not set up. Run migrations 006_responder_incident_patients.sql and 007_patient_scene_conditions.sql.',
                ]);
            }

            self::fail('responder report', $e, 'Unable to save the incident report.');
        }
    }

    private static function coordinates(array $data): array
    {
        $latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($latitude === false || abs($latitude) > 90
            || $longitude === false || abs($longitude) > 180) {
            self::respond(422, ['success' => false, 'message' => 'Pin a valid incident location before continuing.']);
        }

        return [(float) $latitude, (float) $longitude];
    }

    private static function resolveAssignment(int $userId, float $latitude, float $longitude): array
    {
        $profile = ResponderDashboardModel::profile($userId);
        if ($profile === null) {
            return [null, null, null];
        }

        $hospital = $profile['hospital_id'] !== null
            ? IncidentModel::hospitalById((int) $profile['hospital_id'])
            : IncidentModel::nearestHospital($latitude, $longitude);
        $barangay = $hospital === null
            ? null
            : DispatchModel::nearestBarangay((int) $hospital['id'], $latitude, $longitude);

        return [$profile, $hospital, $barangay];
    }

    private static function optionalInteger(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
        if ($number === false) {
            throw new InvalidArgumentException('Patient vital signs are invalid.');
        }

        return $number;
    }

    private static function storeReportPhoto(?array $photo): ?string
    {
        if ($photo === null || ($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($photo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
            || !isset($photo['tmp_name'], $photo['size'])
            || (int) $photo['size'] > 5242880
            || !is_uploaded_file($photo['tmp_name'])) {
            throw new InvalidArgumentException('A patient photo failed to upload or exceeded 5 MB.');
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
            throw new RuntimeException('Incident photo storage could not be created.');
        }
        $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($photo['tmp_name'], $directory . '/' . $filename)) {
            throw new RuntimeException('Incident photo could not be stored.');
        }

        return $filename;
    }

    private static function deleteReportPhoto(string $filename): void
    {
        if (!preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp|heic|heif)\z/', $filename)) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/private_uploads/incident_patients/' . $filename;
        if (is_file($path) && !unlink($path)) {
            error_log('Mobile API: unable to remove an incident patient photo.');
        }
    }
}
