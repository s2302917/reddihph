<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/AuditLogService.php';
require_once __DIR__ . '/../services/IncidentTriageCategory.php';
require_once __DIR__ . '/VolunteerModel.php';

/** Database operations for patients and responder actions on a filed incident. */
final class IncidentSceneArrivalUnavailableException extends RuntimeException
{
}

final class ResponderIncidentWorkflowModel
{
    private static array $columnCache = [];

    public const SCENE_CONDITIONS = [
        'conscious',
        'unconscious',
        'bleeding',
        'breathing_difficulty',
    ];

    public static function normalizeSceneConditions(mixed $conditions): array
    {
        if (is_string($conditions)) {
            $conditions = json_decode($conditions, true);
        }
        if (!is_array($conditions)) {
            throw new InvalidArgumentException('Patient scene conditions are invalid.');
        }
        foreach ($conditions as $condition) {
            if (!is_string($condition)
                || !in_array($condition, self::SCENE_CONDITIONS, true)) {
                throw new InvalidArgumentException('Choose valid patient scene conditions.');
            }
        }

        return array_values(array_unique($conditions));
    }

    public static function createReportWithPatients(
        array $report,
        array $patients,
        int $responderUserId,
        bool $requestAnotherVehicle,
        ?int $volunteerUserId = null
    ): int {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $firstPatient = $patients[0];
            $incident = $pdo->prepare(
                'INSERT INTO incidents
                    (incident_type, reported_by, status, triage_category, location, barangay_id, hospital_id,
                     latitude, longitude, responder_name, hospital_name, patient_name,
                     assigned_responder_user_id, assigned_ambulance_id,
                     patient_age, patient_gender,
                     nurse_heart_rate, nurse_blood_pressure,
                     nurse_spo2, notes, eta_minutes, transport_name, transport_type, driver_name)
                 VALUES
                    (:incident_type, :reported_by, \'open\', :triage_category, :location, :barangay_id, :hospital_id,
                     :latitude, :longitude, :responder_name, :hospital_name, :patient_name,
                     :responder_user_id, NULL, :patient_age, :patient_gender,
                     :heart_rate, :blood_pressure, :spo2, :notes,
                     :eta_minutes, :transport_name, :transport_type, :driver_name)'
            );
            $incident->execute([
                'incident_type' => $report['incident_type'],
                'reported_by' => $report['reported_by'],
                'triage_category' => $report['triage_category'],
                'location' => $report['location'],
                'barangay_id' => $report['barangay_id'],
                'hospital_id' => $report['hospital_id'],
                'latitude' => $report['latitude'],
                'longitude' => $report['longitude'],
                'responder_name' => $report['responder_name'],
                'hospital_name' => $report['hospital_name'],
                'patient_name' => $firstPatient['name'],
                'responder_user_id' => $responderUserId,
                'patient_age' => $firstPatient['age'],
                'patient_gender' => $firstPatient['gender'],
                'heart_rate' => $firstPatient['heart_rate'],
                'blood_pressure' => $firstPatient['nurse_blood_pressure'],
                'spo2' => $firstPatient['spo2'],
                'notes' => $firstPatient['notes'] ?? 'Filed by responder from the mobile app.',
                'eta_minutes' => $report['eta_minutes'],
                'transport_name' => $report['transport_name'],
                'transport_type' => $report['transport_type'],
                'driver_name' => $report['driver_name'],
            ]);
            $incidentId = (int) $pdo->lastInsertId();
            AuditLogService::record(
                $pdo,
                $responderUserId,
                'incident.created',
                'incident',
                (string) $incidentId
            );
            if ($volunteerUserId !== null) {
                VolunteerModel::requestAssistance(
                    $pdo,
                    $incidentId,
                    $volunteerUserId,
                    $responderUserId
                );
            }

            $patientInsert = $pdo->prepare(
                'INSERT INTO incident_patients
                    (incident_id, patient_name, patient_age, patient_gender, heart_rate,
                     spo2, nurse_blood_pressure, notes, photo_filename)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($patients as $patient) {
                $patientInsert->execute([
                    $incidentId,
                    $patient['name'],
                    $patient['age'],
                    $patient['gender'],
                    $patient['heart_rate'],
                    $patient['spo2'],
                    $patient['nurse_blood_pressure'],
                    $patient['notes'],
                    $patient['photo_filename'],
                ]);
            }

            $conditionInsert = $pdo->prepare(
                'INSERT INTO incident_patient_conditions (patient_id, condition_code) VALUES (?, ?)'
            );
            $patientLookup = $pdo->prepare(
                'SELECT id FROM incident_patients WHERE incident_id = ? ORDER BY id'
            );
            $patientLookup->execute([$incidentId]);
            $patientIds = $patientLookup->fetchAll(PDO::FETCH_COLUMN);
            foreach ($patients as $index => $patient) {
                AuditLogService::record(
                    $pdo,
                    $responderUserId,
                    'patient.created',
                    'patient',
                    (string) $patientIds[$index],
                    ['incident_id' => $incidentId]
                );
                foreach ($patient['scene_conditions'] ?? [] as $condition) {
                    $conditionInsert->execute([(int) $patientIds[$index], $condition]);
                }
            }

            if ($requestAnotherVehicle) {
                $pdo->prepare(
                    'INSERT INTO incident_vehicle_requests (incident_id, responder_user_id) VALUES (?, ?)'
                )->execute([$incidentId, $responderUserId]);
            }

            self::queueHospitalStaffAlerts($pdo, $incidentId, $report, $patients);
            $pdo->commit();

            return $incidentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function belongsToResponder(
        int $incidentId,
        int $responderUserId,
        ?int $hospitalId,
        string $responderName
    ): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM incidents
             WHERE incident_id = ?
               AND (assigned_responder_user_id = ?
                    OR (assigned_responder_user_id IS NULL
                        AND hospital_id <=> ? AND responder_name = ?))
             LIMIT 1'
        );
        $stmt->execute([$incidentId, $responderUserId, $hospitalId, $responderName]);

        return $stmt->fetchColumn() !== false;
    }

    public static function updateIncidentType(
        int $incidentId,
        int $responderUserId,
        ?int $hospitalId,
        string $responderName,
        string $incidentType
    ): bool {
        $pdo = Database::connection();
        $where = "incident_id = ?
            AND status IN ('open', 'dispatched', 'referred')
            AND (assigned_responder_user_id = ?
                OR (assigned_responder_user_id IS NULL
                    AND hospital_id <=> ? AND responder_name = ?))";
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE incidents SET incident_type = ? WHERE {$where}");
            $stmt->execute([
                $incidentType,
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
            ]);
            if ($stmt->rowCount() > 0) {
                AuditLogService::record(
                    $pdo,
                    $responderUserId,
                    'incident.updated',
                    'incident',
                    (string) $incidentId
                );
                $pdo->commit();
                return true;
            }

            $verify = $pdo->prepare(
                "SELECT 1 FROM incidents WHERE {$where} AND incident_type = ? LIMIT 1"
            );
            $verify->execute([
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
                $incidentType,
            ]);
            $unchanged = $verify->fetchColumn() !== false;
            $pdo->commit();
            return $unchanged;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function updateIncidentDetails(
        int $incidentId,
        int $responderUserId,
        ?int $hospitalId,
        string $responderName,
        string $incidentType,
        string $triageCategory
    ): bool {
        if (!IncidentTriageCategory::isValid($triageCategory)) {
            throw new InvalidArgumentException(
                'Choose a valid emergency category.'
            );
        }
        $pdo = Database::connection();
        $where = "incident_id = ?
            AND hospital_sent_at IS NOT NULL
            AND status IN ('dispatched', 'referred')
            AND (assigned_responder_user_id = ?
                OR (assigned_responder_user_id IS NULL
                    AND hospital_id <=> ? AND responder_name = ?))";
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE incidents SET incident_type = ?, triage_category = ? WHERE {$where}"
            );
            $stmt->execute([
                $incidentType,
                $triageCategory,
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
            ]);
            if ($stmt->rowCount() > 0) {
                AuditLogService::record(
                    $pdo,
                    $responderUserId,
                    'incident.updated',
                    'incident',
                    (string) $incidentId
                );
                $pdo->commit();
                return true;
            }

            $verify = $pdo->prepare(
                "SELECT 1 FROM incidents
                 WHERE {$where} AND incident_type = ? AND triage_category = ? LIMIT 1"
            );
            $verify->execute([
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
                $incidentType,
                $triageCategory,
            ]);
            $unchanged = $verify->fetchColumn() !== false;
            $pdo->commit();
            return $unchanged;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function sendIncidentToHospital(
        int $incidentId,
        int $responderUserId,
        ?int $hospitalId,
        string $responderName,
        string $triageCategory,
        ?int $volunteerUserId
    ): array {
        if (!IncidentTriageCategory::isValid($triageCategory)) {
            throw new InvalidArgumentException(
                'Choose a valid emergency category.'
            );
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $incidentQuery = $pdo->prepare(
                "SELECT i.incident_type, i.location, i.latitude, i.longitude,
                        i.triage_category,
                        COALESCE(referred_hospital.id, i.hospital_id) AS destination_hospital_id,
                        destination.name AS hospital_name,
                        destination.latitude AS hospital_latitude,
                        destination.longitude AS hospital_longitude
                 FROM incidents i
                 LEFT JOIN referrals active_referral ON active_referral.id = (
                    SELECT r.id FROM referrals r
                    WHERE r.incident_id = i.incident_id
                    ORDER BY r.created_at DESC, r.id DESC
                    LIMIT 1
                 ) AND active_referral.status IN ('pending', 'accepted')
                 LEFT JOIN hospitals referred_hospital
                    ON referred_hospital.id = active_referral.receiving_hospital_id
                 INNER JOIN hospitals destination
                    ON destination.id = COALESCE(referred_hospital.id, i.hospital_id)
                 WHERE i.incident_id = ?
                   AND i.status IN ('open', 'dispatched', 'referred')
                   AND (i.assigned_responder_user_id = ?
                        OR (i.assigned_responder_user_id IS NULL
                            AND i.hospital_id <=> ? AND i.responder_name = ?))
                 LIMIT 1 FOR UPDATE"
            );
            $incidentQuery->execute([
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
            ]);
            $incident = $incidentQuery->fetch();
            if ($incident === false) {
                throw new InvalidArgumentException(
                    'This incident has no registered hospital assignment.'
                );
            }
            if ($incident['hospital_latitude'] === null
                || $incident['hospital_longitude'] === null) {
                throw new InvalidArgumentException(
                    'The assigned hospital has no routing coordinates.'
                );
            }
            $patients = $pdo->prepare(
                'SELECT patient_name, patient_age, patient_gender
                 FROM incident_patients WHERE incident_id = ? ORDER BY id'
            );
            $patients->execute([$incidentId]);
            $patientRows = $patients->fetchAll();
            $statusUpdate = $pdo->prepare(
                'UPDATE incidents
                 SET status = \'dispatched\', triage_category = ?,
                     hospital_sent_at = COALESCE(hospital_sent_at, NOW())
                 WHERE incident_id = ?'
            );
            $statusUpdate->execute([$triageCategory, $incidentId]);

            if ($volunteerUserId !== null) {
                $existingAssistance = $pdo->prepare(
                    "SELECT volunteer_user_id FROM volunteer_assistances
                     WHERE incident_id = ? AND status IN ('requested', 'accepted')
                     LIMIT 1 FOR UPDATE"
                );
                $existingAssistance->execute([$incidentId]);
                $assignedVolunteer = $existingAssistance->fetchColumn();
                if ($assignedVolunteer === false) {
                    VolunteerModel::requestAssistance(
                        $pdo,
                        $incidentId,
                        $volunteerUserId,
                        $responderUserId
                    );
                } elseif ((int) $assignedVolunteer !== $volunteerUserId) {
                    throw new InvalidArgumentException(
                        'A volunteer is already assigned to this incident.'
                    );
                }
            }

            self::queueExistingIncidentHospitalAlerts(
                $pdo,
                $incidentId,
                $incident,
                $patientRows,
                $triageCategory
            );
            if ($statusUpdate->rowCount() > 0) {
                AuditLogService::record(
                    $pdo,
                    $responderUserId,
                    'incident.status_updated',
                    'incident',
                    (string) $incidentId,
                    ['status' => 'dispatched', 'triage_category' => $triageCategory]
                );
            }
            $pdo->commit();

            $distanceKm = DispatchModel::distanceKm(
                (float) $incident['latitude'],
                (float) $incident['longitude'],
                (float) $incident['hospital_latitude'],
                (float) $incident['hospital_longitude']
            );

            return [
                'hospital' => [
                    'id' => (int) $incident['destination_hospital_id'],
                    'name' => (string) $incident['hospital_name'],
                    'latitude' => (float) $incident['hospital_latitude'],
                    'longitude' => (float) $incident['hospital_longitude'],
                ],
                'estimated_road_distance_km' => round($distanceKm * 1.3, 1),
                'eta_minutes' => DispatchModel::etaMinutes(
                    (float) $incident['latitude'],
                    (float) $incident['longitude'],
                    (float) $incident['hospital_latitude'],
                    (float) $incident['hospital_longitude']
                ),
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function routableHospitals(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, latitude, longitude
             FROM hospitals
             WHERE 1 = 1'
             . self::activeHospitalFilter()
             . ' ORDER BY name, id'
        );

        return array_map(static function (array $hospital): array {
            $latitude = $hospital['latitude'] === null
                ? null
                : (float) $hospital['latitude'];
            $longitude = $hospital['longitude'] === null
                ? null
                : (float) $hospital['longitude'];

            return [
                'id' => (int) $hospital['id'],
                'name' => (string) $hospital['name'],
                'can_route' => $latitude !== null
                    && $longitude !== null
                    && $latitude >= -90
                    && $latitude <= 90
                    && $longitude >= -180
                    && $longitude <= 180,
            ];
        }, $statement->fetchAll());
    }

    public static function rerouteIncident(
        int $incidentId,
        int $responderUserId,
        int $hospitalId,
        ?int $assignedHospitalId,
        string $responderName
    ): array {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $incidentQuery = $pdo->prepare(
                "SELECT i.incident_type, i.location, i.latitude, i.longitude,
                        i.status,
                        i.triage_category,
                        COALESCE(active_hospital.id, i.hospital_id) AS current_hospital_id
                 FROM incidents i
                 LEFT JOIN referrals latest_referral ON latest_referral.id = (
                    SELECT r.id FROM referrals r
                    WHERE r.incident_id = i.incident_id
                    ORDER BY r.created_at DESC, r.id DESC
                    LIMIT 1
                 )
                 LEFT JOIN hospitals active_hospital
                    ON active_hospital.id = latest_referral.receiving_hospital_id
                    AND latest_referral.status IN ('pending', 'accepted')
                 INNER JOIN hospitals current_hospital
                    ON current_hospital.id = COALESCE(active_hospital.id, i.hospital_id)
                 WHERE i.incident_id = ?
                   AND i.status IN ('dispatched', 'referred')
                   AND i.hospital_sent_at IS NOT NULL
                   AND (i.assigned_responder_user_id = ?
                        OR (i.assigned_responder_user_id IS NULL
                            AND i.hospital_id <=> ? AND i.responder_name = ?))
                 LIMIT 1 FOR UPDATE"
            );
            $incidentQuery->execute([
                $incidentId,
                $responderUserId,
                $assignedHospitalId,
                $responderName,
            ]);
            $incident = $incidentQuery->fetch();
            if ($incident === false) {
                throw new InvalidArgumentException(
                    'This sent incident is no longer assigned to you.'
                );
            }

            $targetQuery = $pdo->prepare(
                'SELECT id, name, latitude, longitude
                 FROM hospitals WHERE id = ?'
                 . self::activeHospitalFilter()
                 . ' LIMIT 1 FOR UPDATE'
            );
            $targetQuery->execute([$hospitalId]);
            $hospital = $targetQuery->fetch();
            if ($hospital === false) {
                throw new InvalidArgumentException('Choose a registered hospital.');
            }
            $latitude = $hospital['latitude'] === null
                ? null
                : (float) $hospital['latitude'];
            $longitude = $hospital['longitude'] === null
                ? null
                : (float) $hospital['longitude'];
            if ($latitude === null || $longitude === null
                || $latitude < -90 || $latitude > 90
                || $longitude < -180 || $longitude > 180) {
                throw new InvalidArgumentException(
                    'This hospital has no routing coordinates yet.'
                );
            }
            if ((int) $incident['current_hospital_id'] === $hospitalId) {
                $pdo->commit();
                return [
                    'hospital' => [
                        'id' => $hospitalId,
                        'name' => (string) $hospital['name'],
                    ],
                    'changed' => false,
                ];
            }

            $referral = $pdo->prepare(
                "INSERT INTO referrals
                    (incident_id, receiving_hospital_id, referral_reason, status, created_by_user_id)
                 VALUES (?, ?, 'Responder rerouted incident to selected hospital.', 'pending', ?)"
            );
            $referral->execute([$incidentId, $hospitalId, $responderUserId]);
            $referralId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                "UPDATE incidents SET status = 'referred' WHERE incident_id = ?"
            )->execute([$incidentId]);

            $patientsQuery = $pdo->prepare(
                'SELECT patient_name, patient_age, patient_gender
                 FROM incident_patients WHERE incident_id = ? ORDER BY id'
            );
            $patientsQuery->execute([$incidentId]);
            $incident['destination_hospital_id'] = $hospitalId;
            $incident['hospital_name'] = (string) $hospital['name'];
            self::queueExistingIncidentHospitalAlerts(
                $pdo,
                $incidentId,
                $incident,
                $patientsQuery->fetchAll(),
                (string) ($incident['triage_category'] ?? ''),
                true
            );
            AuditLogService::record(
                $pdo,
                $responderUserId,
                'referral.created',
                'referral',
                (string) $referralId,
                [
                    'incident_id' => $incidentId,
                    'hospital_id' => $hospitalId,
                    'operation' => 'responder_reroute',
                ]
            );
            $pdo->commit();

            return [
                'hospital' => [
                    'id' => $hospitalId,
                    'name' => (string) $hospital['name'],
                ],
                'changed' => true,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function queueExistingIncidentHospitalAlerts(
        PDO $pdo,
        int $incidentId,
        array $incident,
        array $patients,
        string $status,
        bool $rerouted = false
    ): void {
        $hospitalId = (int) $incident['destination_hospital_id'];
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $recipients = $pdo->prepare(
            "SELECT u.id AS user_id, u.role, s.department AS specialization,
                    shift.shift_date, shift.shift_start, shift.shift_end
             FROM user_type u
             INNER JOIN staff s ON s.id = (
                 SELECT candidate.id
                 FROM staff candidate
                 WHERE candidate.user_id = u.id
                   AND (candidate.hospital_id = u.hospital_id OR candidate.hospital_id IS NULL)
                 ORDER BY (candidate.hospital_id = u.hospital_id) DESC, candidate.id DESC
                 LIMIT 1
             )
             INNER JOIN staff_shifts shift ON shift.staff_id = s.id
               AND shift.shift_date IN (?, ?)
               AND shift.shift_status = 'on_duty'
             WHERE u.hospital_id = ? AND u.role IN ('doctor', 'nurse')"
        );
        $recipients->execute([
            $now->format('Y-m-d'),
            $now->modify('-1 day')->format('Y-m-d'),
            $hospitalId,
        ]);

        $patientSummary = array_map(
            static fn (array $patient): string => sprintf(
                '%s, age %d (%s)',
                $patient['patient_name'],
                (int) $patient['patient_age'],
                ucfirst((string) $patient['patient_gender'])
            ),
            $patients
        );
        $message = implode("\n", [
            $rerouted
                ? 'Responder changed the destination hospital for incident #' . $incidentId . '.'
                : 'Responder sent incident #' . $incidentId . ' to the hospital.',
            'Incident: ' . $incident['incident_type'],
            'Emergency category: ' . IncidentTriageCategory::label($status),
            'Location: ' . $incident['location'],
            'Hospital: ' . $incident['hospital_name'],
            'Patients: ' . ($patientSummary === []
                ? 'Awaiting responder assessment.'
                : implode('; ', $patientSummary)),
        ]);
        $existingAlert = $pdo->prepare(
            "SELECT 1 FROM staff_alerts
             WHERE incident_id = ? AND recipient_user_id = ?
               AND channel = 'admin' AND message = ?
             LIMIT 1"
        );
        $insert = $pdo->prepare(
            "INSERT INTO staff_alerts
                (incident_id, recipient_user_id, recipient_role, specialization, message, channel, status)
             VALUES (?, ?, ?, ?, ?, 'admin', 'queued')"
        );
        $notifiedIds = [];
        foreach ($recipients->fetchAll() as $recipient) {
            $userId = (int) $recipient['user_id'];
            if (isset($notifiedIds[$userId])
                || !self::isShiftActive($recipient, $now)) {
                continue;
            }
            $notifiedIds[$userId] = true;
            $existingAlert->execute([$incidentId, $userId, $message]);
            if ($existingAlert->fetchColumn() !== false) {
                continue;
            }
            $insert->execute([
                $incidentId,
                $userId,
                $recipient['role'],
                $recipient['specialization'],
                $message,
            ]);
        }
    }

    public static function incidentDestinationForResponder(
        int $incidentId,
        int $responderUserId,
        ?int $hospitalId,
        string $responderName
    ): ?array {
        $stmt = Database::connection()->prepare(
            "SELECT location, latitude, longitude
             FROM incidents
             WHERE incident_id = ?
               AND (assigned_responder_user_id = ?
                    OR (assigned_responder_user_id IS NULL
                        AND hospital_id <=> ? AND responder_name = ?))
               AND status IN ('open', 'dispatched', 'referred')
             LIMIT 1"
        );
        $stmt->execute([$incidentId, $responderUserId, $hospitalId, $responderName]);
        $incident = $stmt->fetch();
        if ($incident === false
            || $incident['latitude'] === null
            || $incident['longitude'] === null) {
            return null;
        }

        return [
            'name' => (string) $incident['location'],
            'latitude' => (float) $incident['latitude'],
            'longitude' => (float) $incident['longitude'],
        ];
    }

    public static function markSceneArrived(
        int $incidentId,
        ?int $hospitalId,
        string $responderName,
        int $responderUserId
    ): bool {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $incidentQuery = $pdo->prepare(
                "SELECT incident_id, incident_type, location, barangay_id,
                        responder_arrived_at
                 FROM incidents
                 WHERE incident_id = ?
                   AND (assigned_responder_user_id = ?
                        OR (assigned_responder_user_id IS NULL
                            AND hospital_id <=> ? AND responder_name = ?))
                   AND status IN ('open', 'dispatched', 'referred')
                 LIMIT 1 FOR UPDATE"
            );
            $incidentQuery->execute([
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
            ]);
            $incident = $incidentQuery->fetch();
            if (!$incident) {
                $pdo->rollBack();
                return false;
            }
            if ($incident['barangay_id'] === null) {
                throw new IncidentSceneArrivalUnavailableException(
                    'This incident is not assigned to a barangay.'
                );
            }

            $admins = $pdo->prepare(
                "SELECT id FROM user_type
                 WHERE role = 'barangay_admin' AND barangay_id = ?"
            );
            $admins->execute([(int) $incident['barangay_id']]);
            $adminIds = array_map('intval', $admins->fetchAll(PDO::FETCH_COLUMN));
            if ($adminIds === []) {
                throw new IncidentSceneArrivalUnavailableException(
                    'No Barangay Admin is assigned to this incident location.'
                );
            }

            $arrivedAt = (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Manila')
            ))->format('Y-m-d H:i:s');
            $arrivalUpdate = $pdo->prepare(
                'UPDATE incidents
                 SET responder_arrived_at = COALESCE(responder_arrived_at, ?)
                 WHERE incident_id = ?'
            );
            $arrivalUpdate->execute([$arrivedAt, $incidentId]);

            $alert = $pdo->prepare(
                "INSERT IGNORE INTO incident_scene_arrival_alerts
                    (incident_id, responder_user_id, barangay_admin_user_id, message)
                 VALUES (?, ?, ?, ?)"
            );
            $message = sprintf(
                'Responder arrived at %s for %s (Incident #%d).',
                $incident['location'],
                $incident['incident_type'],
                $incidentId
            );
            foreach ($adminIds as $adminId) {
                $alert->execute([$incidentId, $responderUserId, $adminId, $message]);
            }

            if ($arrivalUpdate->rowCount() > 0) {
                AuditLogService::record(
                    $pdo,
                    $responderUserId,
                    'incident.arrival_recorded',
                    'incident',
                    (string) $incidentId,
                    ['arrival_stage' => 'scene']
                );
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function pendingSceneArrivalAlerts(int $barangayId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT alert.id, alert.incident_id, alert.message, alert.created_at,
                    i.incident_type, i.location, responder.full_name AS responder_name
             FROM incident_scene_arrival_alerts alert
             INNER JOIN incidents i ON i.incident_id = alert.incident_id
             INNER JOIN user_type admin ON admin.id = alert.barangay_admin_user_id
             INNER JOIN user_type responder ON responder.id = alert.responder_user_id
             WHERE admin.role = 'barangay_admin' AND admin.barangay_id = ?
               AND alert.status = 'queued'
             ORDER BY alert.created_at DESC, alert.id DESC"
        );
        $stmt->execute([$barangayId]);

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['incident_id'] = (int) $row['incident_id'];
            return $row;
        }, $stmt->fetchAll());
    }

    public static function acknowledgeSceneArrivalAlert(
        int $alertId,
        int $adminUserId,
        int $barangayId
    ): bool {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE incident_scene_arrival_alerts alert
                 INNER JOIN user_type admin ON admin.id = alert.barangay_admin_user_id
                 SET alert.status = 'read', alert.read_at = NOW()
                 WHERE alert.id = ? AND alert.barangay_admin_user_id = ?
                   AND admin.role = 'barangay_admin' AND admin.barangay_id = ?
                   AND alert.status = 'queued'"
            );
            $stmt->execute([$alertId, $adminUserId, $barangayId]);
            if ($stmt->rowCount() !== 1) {
                $pdo->commit();
                return false;
            }
            AuditLogService::record(
                $pdo,
                $adminUserId,
                'staff.alert_acknowledged',
                'incident_scene_arrival_alert',
                (string) $alertId
            );
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function addIncidentNoteUpdate(
        int $incidentId,
        int $authorUserId,
        ?int $hospitalId,
        string $authorName,
        string $note,
        bool $reporterOwnsIncident = false
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $incident = $pdo->prepare($reporterOwnsIncident
                ? 'SELECT i.incident_type, i.status, i.location, i.latitude, i.longitude,
                          COALESCE(referred_hospital.name, i.hospital_name) AS hospital_name,
                          i.eta_minutes, i.notes,
                          COALESCE(referred_hospital.id, i.hospital_id) AS hospital_id
                   FROM incidents i
                   LEFT JOIN referrals latest_referral ON latest_referral.id = (
                       SELECT r.id FROM referrals r
                       WHERE r.incident_id = i.incident_id
                       ORDER BY r.created_at DESC, r.id DESC
                       LIMIT 1
                   ) AND latest_referral.status IN (\'pending\', \'accepted\')
                   LEFT JOIN hospitals referred_hospital
                     ON referred_hospital.id = latest_referral.receiving_hospital_id
                   WHERE i.incident_id = ? AND i.reporter_user_id = ?
                   FOR UPDATE'
                : 'SELECT i.incident_type, i.status, i.location, i.latitude, i.longitude,
                          COALESCE(referred_hospital.name, i.hospital_name) AS hospital_name,
                          i.eta_minutes, i.notes,
                          COALESCE(referred_hospital.id, i.hospital_id) AS hospital_id
                   FROM incidents i
                   LEFT JOIN referrals latest_referral ON latest_referral.id = (
                       SELECT r.id FROM referrals r
                       WHERE r.incident_id = i.incident_id
                       ORDER BY r.created_at DESC, r.id DESC
                       LIMIT 1
                   ) AND latest_referral.status IN (\'pending\', \'accepted\')
                   LEFT JOIN hospitals referred_hospital
                     ON referred_hospital.id = latest_referral.receiving_hospital_id
                   WHERE i.incident_id = ?
                     AND (i.assigned_responder_user_id = ?
                          OR (i.assigned_responder_user_id IS NULL
                              AND i.hospital_id <=> ? AND i.responder_name = ?))
                   FOR UPDATE');
            $incident->execute($reporterOwnsIncident
                ? [$incidentId, $authorUserId]
                : [$incidentId, $authorUserId, $hospitalId, $authorName]);
            $row = $incident->fetch();
            if ($row === false) {
                throw new InvalidArgumentException('Incident not found.');
            }

            $pdo->prepare(
                'INSERT INTO incident_note_updates
                    (incident_id, author_user_id, note)
                 VALUES (?, ?, ?)'
            )->execute([$incidentId, $authorUserId, $note]);

            $combinedNotes = trim((string) ($row['notes'] ?? ''));
            $combinedNotes = $combinedNotes === ''
                ? $note
                : $combinedNotes . "\n\n" . $note;
            $pdo->prepare(
                'UPDATE incidents SET notes = ? WHERE incident_id = ?'
            )->execute([$combinedNotes, $incidentId]);

            self::queueIncidentUpdateAlerts(
                $pdo,
                $incidentId,
                $row,
                $note,
                $authorName,
                $reporterOwnsIncident
            );
            AuditLogService::record(
                $pdo,
                $authorUserId,
                'incident.note_added',
                'incident',
                (string) $incidentId
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function patients(int $incidentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, patient_name, patient_age, patient_gender, heart_rate, spo2,
                nurse_blood_pressure, notes, photo_filename, created_at
             FROM incident_patients
             WHERE incident_id = ?
             ORDER BY id'
        );
        $stmt->execute([$incidentId]);
        $patients = $stmt->fetchAll();
        if ($patients === []) {
            return [];
        }

        $patientIds = array_map(
            static fn (array $patient): int => (int) $patient['id'],
            $patients
        );
        $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
        $conditions = Database::connection()->prepare(
            "SELECT patient_id, condition_code
             FROM incident_patient_conditions
             WHERE patient_id IN ({$placeholders})
             ORDER BY patient_id, condition_code"
        );
        $conditions->execute($patientIds);
        $conditionsByPatient = [];
        foreach ($conditions->fetchAll() as $condition) {
            $conditionsByPatient[(int) $condition['patient_id']][] = $condition['condition_code'];
        }

        foreach ($patients as &$patient) {
            $patient['scene_conditions'] = $conditionsByPatient[(int) $patient['id']] ?? [];
        }
        unset($patient);

        return $patients;
    }

    public static function addPatient(int $incidentId, array $patient, int $authorUserId): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO incident_patients
                    (incident_id, patient_name, patient_age, patient_gender, heart_rate,
                     spo2, nurse_blood_pressure, notes, photo_filename)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $incidentId,
                $patient['name'],
                $patient['age'],
                $patient['gender'],
                $patient['heart_rate'],
                $patient['spo2'],
                $patient['nurse_blood_pressure'],
                $patient['notes'],
                $patient['photo_filename'],
            ]);
            $patientId = (int) $pdo->lastInsertId();
            if (!empty($patient['scene_conditions'])) {
                $conditionInsert = $pdo->prepare(
                    'INSERT INTO incident_patient_conditions (patient_id, condition_code) VALUES (?, ?)'
                );
                foreach ($patient['scene_conditions'] as $condition) {
                    $conditionInsert->execute([$patientId, $condition]);
                }
            }

            $update = $pdo->prepare(
                'UPDATE incidents SET patient_name = ?, patient_age = ?, patient_gender = ?,
                    nurse_heart_rate = ?, nurse_spo2 = ?, nurse_blood_pressure = ?, notes = ?
                 WHERE incident_id = ?'
            );
            $update->execute([
                $patient['name'],
                $patient['age'],
                $patient['gender'],
                $patient['heart_rate'],
                $patient['spo2'],
                $patient['nurse_blood_pressure'],
                $patient['notes'],
                $incidentId,
            ]);
            AuditLogService::record(
                $pdo,
                $authorUserId,
                'patient.created',
                'patient',
                (string) $patientId,
                ['incident_id' => $incidentId]
            );
            $pdo->commit();

            return $patientId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function deletePatient(int $incidentId, int $patientId, int $authorUserId): string|false|null
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT photo_filename FROM incident_patients WHERE id = ? AND incident_id = ? FOR UPDATE'
            );
            $stmt->execute([$patientId, $incidentId]);
            $filename = $stmt->fetchColumn();
            if ($filename === false) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare('DELETE FROM incident_patients WHERE id = ?')->execute([$patientId]);
            $remaining = $pdo->prepare(
                'SELECT patient_name, patient_age, patient_gender, heart_rate, spo2,
                        nurse_blood_pressure, notes
                 FROM incident_patients WHERE incident_id = ? ORDER BY id DESC LIMIT 1'
            );
            $remaining->execute([$incidentId]);
            $latest = $remaining->fetch();
            if ($latest) {
                $sync = $pdo->prepare(
                    'UPDATE incidents SET patient_name = ?, patient_age = ?, patient_gender = ?,
                        nurse_heart_rate = ?, nurse_spo2 = ?, nurse_blood_pressure = ?, notes = ?
                     WHERE incident_id = ?'
                );
                $sync->execute([
                    $latest['patient_name'], $latest['patient_age'], $latest['patient_gender'],
                    $latest['heart_rate'], $latest['spo2'], $latest['nurse_blood_pressure'],
                    $latest['notes'], $incidentId,
                ]);
            } else {
                $pdo->prepare(
                    "UPDATE incidents SET patient_name = NULL, patient_age = NULL,
                        patient_gender = 'unknown', nurse_heart_rate = NULL, nurse_spo2 = NULL,
                        nurse_blood_pressure = NULL, notes = 'Filed by responder from the mobile app.'
                     WHERE incident_id = ?"
                )->execute([$incidentId]);
            }
            AuditLogService::record(
                $pdo,
                $authorUserId,
                'patient.deleted',
                'patient',
                (string) $patientId,
                ['incident_id' => $incidentId]
            );
            $pdo->commit();

            return $filename === null ? null : (string) $filename;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function markPickedUp(
        int $incidentId,
        int $responderUserId,
        string $responderName
    ): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pickedUpAt = (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Manila')
            ))->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                "UPDATE incidents
                 SET status = 'dispatched',
                     patient_picked_up_at = COALESCE(patient_picked_up_at, ?)
                 WHERE incident_id = ?
                   AND (assigned_responder_user_id = ?
                        OR (assigned_responder_user_id IS NULL
                            AND responder_name = ?))
                   AND status IN ('dispatched', 'referred')
                 LIMIT 1"
            );
            $stmt->execute([
                $pickedUpAt,
                $incidentId,
                $responderUserId,
                $responderName,
            ]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare(
                    "SELECT status FROM incidents
                     WHERE incident_id = ?
                       AND (assigned_responder_user_id = ?
                            OR (assigned_responder_user_id IS NULL
                                AND responder_name = ?))
                     LIMIT 1"
                );
                $check->execute([$incidentId, $responderUserId, $responderName]);
                if ($check->fetchColumn() !== 'dispatched') {
                    throw new RuntimeException('Incident is not available for pickup.');
                }
            } else {
                AuditLogService::record(
                    $pdo,
                    $responderUserId,
                    'incident.status_updated',
                    'incident',
                    (string) $incidentId,
                    ['status' => 'dispatched']
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function markTransportArrived(
        int $incidentId,
        int $responderUserId,
        int $hospitalId,
        string $responderName
    ): array|false {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $incident = $pdo->prepare(
                "SELECT i.incident_id, i.transport_name, i.assigned_ambulance_id,
                        i.hospital_sent_at, i.patient_picked_up_at,
                        i.hospital_arrived_at, i.reported_at,
                        destination.name AS hospital_name,
                        va.status AS volunteer_assistance_status,
                        va.volunteer_user_id,
                        volunteer.full_name AS volunteer_name,
                        volunteer_profile.vehicle_type AS volunteer_vehicle_type,
                        volunteer_review.rating AS volunteer_review_rating
                 FROM incidents i
                 LEFT JOIN referrals active_referral ON active_referral.id = (
                    SELECT r.id
                    FROM referrals r
                    WHERE r.incident_id = i.incident_id
                    ORDER BY r.created_at DESC, r.id DESC
                    LIMIT 1
                 ) AND active_referral.status IN ('pending', 'accepted')
                 LEFT JOIN hospitals destination
                    ON destination.id = COALESCE(
                        active_referral.receiving_hospital_id,
                        i.hospital_id
                    )
                 LEFT JOIN volunteer_assistances va
                    ON va.incident_id = i.incident_id
                 LEFT JOIN user_type volunteer
                    ON volunteer.id = va.volunteer_user_id
                 LEFT JOIN volunteer_profiles volunteer_profile
                    ON volunteer_profile.user_id = va.volunteer_user_id
                 LEFT JOIN volunteer_assistance_reviews volunteer_review
                    ON volunteer_review.assistance_id = va.id
                    AND volunteer_review.reviewer_user_id = ?
                 WHERE i.incident_id = ?
                   AND (i.assigned_responder_user_id = ?
                        OR (i.assigned_responder_user_id IS NULL
                            AND i.hospital_id = ? AND i.responder_name = ?))
                   AND i.status IN ('open', 'dispatched', 'referred')
                 LIMIT 1 FOR UPDATE"
            );
            $incident->execute([
                $responderUserId,
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
            ]);
            $assignment = $incident->fetch();
            if (!$assignment) {
                $pdo->rollBack();
                return false;
            }

            $arrivedAt = (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Manila')
            ))->format('Y-m-d H:i:s');
            $recordedArrivalAt = $assignment['hospital_arrived_at'] !== null
                ? (string) $assignment['hospital_arrived_at']
                : $arrivedAt;
            $newArrival = $assignment['hospital_arrived_at'] === null;
            $volunteerStatus = (string) (
                $assignment['volunteer_assistance_status'] ?? ''
            );
            if ($volunteerStatus === 'accepted') {
                $pdo->prepare(
                    "UPDATE volunteer_assistances
                     SET status = 'completed'
                     WHERE incident_id = ? AND status = 'accepted'"
                )->execute([$incidentId]);
                $pdo->prepare(
                    'UPDATE volunteer_profiles SET is_available = 1 WHERE user_id = ?'
                )->execute([(int) $assignment['volunteer_user_id']]);
                $volunteerStatus = 'completed';
            }
            $pdo->prepare(
                'UPDATE incidents
                 SET hospital_arrived_at = COALESCE(hospital_arrived_at, ?),
                     resolved_at = COALESCE(resolved_at, ?)
                 WHERE incident_id = ?'
            )->execute([$recordedArrivalAt, $recordedArrivalAt, $incidentId]);
            if ($newArrival) {
                AuditLogService::record(
                    $pdo,
                    $responderUserId,
                    'incident.arrival_recorded',
                    'incident',
                    (string) $incidentId,
                    ['arrival_stage' => 'hospital']
                );
            }

            $transportName = trim((string) ($assignment['transport_name'] ?? ''));
            if ($transportName !== '') {
                $vehicleId = $assignment['assigned_ambulance_id'] !== null
                    ? (int) $assignment['assigned_ambulance_id']
                    : null;
                $vehicle = $pdo->prepare(
                    "UPDATE ambulances
                     SET status = 'arrived', arrived_at = ?, eta_minutes = 0
                     WHERE " . ($vehicleId === null
                        ? 'call_sign = ? AND hospital_id = ?'
                        : 'id = ?')
                );
                $vehicle->execute($vehicleId === null
                    ? [$arrivedAt, $transportName, $hospitalId]
                    : [$arrivedAt, $vehicleId]);
            }

            $pdo->commit();
            return [
                'hospital_name' => (string) ($assignment['hospital_name'] ?? 'Hospital'),
                'dispatched_at' => (string) (
                    $assignment['hospital_sent_at'] ?? $assignment['reported_at']
                ),
                'patient_picked_up_at' => $assignment['patient_picked_up_at'] !== null
                    ? (string) $assignment['patient_picked_up_at']
                    : null,
                'arrived_at' => $recordedArrivalAt,
                'volunteer_name' => in_array(
                    $volunteerStatus,
                    ['accepted', 'completed'],
                    true
                ) ? (string) $assignment['volunteer_name'] : null,
                'volunteer_vehicle_type' => in_array(
                    $volunteerStatus,
                    ['accepted', 'completed'],
                    true
                ) && $assignment['volunteer_vehicle_type'] !== null
                    ? (string) $assignment['volunteer_vehicle_type']
                    : null,
                'volunteer_assistance_status' => in_array(
                    $volunteerStatus,
                    ['accepted', 'completed'],
                    true
                ) ? $volunteerStatus : null,
                'volunteer_review_rating' => $assignment['volunteer_review_rating'] !== null
                    ? (int) $assignment['volunteer_review_rating']
                    : null,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function thankVolunteer(
        int $incidentId,
        int $responderUserId,
        ?int $hospitalId,
        string $responderName,
        int $rating
    ): void {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Choose a rating from 1 to 5.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $assistance = $pdo->prepare(
                "SELECT va.id
                 FROM incidents i
                 INNER JOIN volunteer_assistances va
                    ON va.incident_id = i.incident_id
                 WHERE i.incident_id = ?
                   AND (i.assigned_responder_user_id = ?
                        OR (i.assigned_responder_user_id IS NULL
                            AND i.hospital_id <=> ? AND i.responder_name = ?))
                   AND i.hospital_arrived_at IS NOT NULL
                   AND va.status = 'completed'
                 LIMIT 1 FOR UPDATE"
            );
            $assistance->execute([
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
            ]);
            $assistanceId = $assistance->fetchColumn();
            if ($assistanceId === false) {
                throw new InvalidArgumentException(
                    'Volunteer thanks are available after hospital arrival.'
                );
            }

            $pdo->prepare(
                'INSERT INTO volunteer_assistance_reviews
                    (assistance_id, reviewer_user_id, rating, review)
                 VALUES (?, ?, ?, ?)'
            )->execute([
                (int) $assistanceId,
                $responderUserId,
                $rating,
                'Thank you for helping transport the patient.',
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) $e->getCode() === 23000) {
                throw new InvalidArgumentException(
                    'This volunteer has already received thanks.'
                );
            }
            throw $e;
        }
    }

    public static function updateTransportLocation(
        int $incidentId,
        int $responderUserId,
        int $hospitalId,
        string $responderName,
        float $latitude,
        float $longitude
    ): array {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Valid GPS coordinates are required.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $incident = $pdo->prepare(
                "SELECT i.transport_name, i.assigned_ambulance_id,
                        destination.latitude AS hospital_latitude,
                        destination.longitude AS hospital_longitude
                 FROM incidents i
                 LEFT JOIN referrals latest_referral ON latest_referral.id = (
                    SELECT r.id
                    FROM referrals r
                    WHERE r.incident_id = i.incident_id
                    ORDER BY r.created_at DESC, r.id DESC
                    LIMIT 1
                 )
                 LEFT JOIN hospitals referred_hospital
                    ON referred_hospital.id = latest_referral.receiving_hospital_id
                    AND latest_referral.status IN ('pending', 'accepted')
                 INNER JOIN hospitals destination
                    ON destination.id = COALESCE(referred_hospital.id, i.hospital_id)
                 WHERE i.incident_id = ?
                   AND (i.assigned_responder_user_id = ?
                        OR (i.assigned_responder_user_id IS NULL
                            AND i.hospital_id = ? AND i.responder_name = ?))
                   AND i.status IN ('dispatched', 'referred')
                 LIMIT 1 FOR UPDATE"
            );
            $incident->execute([
                $incidentId,
                $responderUserId,
                $hospitalId,
                $responderName,
            ]);
            $assignment = $incident->fetch();
            $transportName = trim((string) ($assignment['transport_name'] ?? ''));
            if ($assignment === false || $transportName === '') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'No active transport is assigned to this incident.'];
            }

            $recordedAt = (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Manila')
            ))->format('Y-m-d H:i:s');
            $distanceKm = null;
            if ($assignment['hospital_latitude'] !== null
                && $assignment['hospital_longitude'] !== null) {
                $distanceKm = self::distanceKm(
                    $latitude,
                    $longitude,
                    (float) $assignment['hospital_latitude'],
                    (float) $assignment['hospital_longitude']
                );
            }
            $arrived = $distanceKm !== null && $distanceKm <= 0.15;

            if ($arrived) {
                $pdo->prepare(
                    'UPDATE incidents
                     SET hospital_arrived_at = COALESCE(hospital_arrived_at, ?),
                         resolved_at = COALESCE(resolved_at, ?)
                     WHERE incident_id = ?'
                )->execute([$recordedAt, $recordedAt, $incidentId]);
                $vehicleId = $assignment['assigned_ambulance_id'] !== null
                    ? (int) $assignment['assigned_ambulance_id']
                    : null;
                $vehicle = $pdo->prepare(
                    "UPDATE ambulances
                     SET latitude = ?, longitude = ?, location_recorded_at = ?,
                         status = 'arrived', arrived_at = ?, eta_minutes = 0
                     WHERE " . ($vehicleId === null
                        ? 'call_sign = ? AND hospital_id = ?'
                        : 'id = ?')
                );
                $vehicle->execute($vehicleId === null
                    ? [
                        $latitude,
                        $longitude,
                        $recordedAt,
                        $recordedAt,
                        $transportName,
                        $hospitalId,
                    ]
                    : [
                        $latitude,
                        $longitude,
                        $recordedAt,
                        $recordedAt,
                        $vehicleId,
                    ]);
            } else {
                $etaMinutes = $distanceKm === null
                    ? null
                    : max(1, (int) ceil(($distanceKm * 1.3 / 35) * 60));
                $vehicleId = $assignment['assigned_ambulance_id'] !== null
                    ? (int) $assignment['assigned_ambulance_id']
                    : null;
                $vehicle = $pdo->prepare(
                    "UPDATE ambulances
                     SET latitude = ?, longitude = ?, location_recorded_at = ?,
                         status = 'en_route', eta_minutes = COALESCE(?, eta_minutes)
                     WHERE " . ($vehicleId === null
                        ? 'call_sign = ? AND hospital_id = ?'
                        : 'id = ?') . " AND status <> 'arrived'"
                );
                $vehicle->execute($vehicleId === null
                    ? [
                        $latitude,
                        $longitude,
                        $recordedAt,
                        $etaMinutes,
                        $transportName,
                        $hospitalId,
                    ]
                    : [
                        $latitude,
                        $longitude,
                        $recordedAt,
                        $etaMinutes,
                        $vehicleId,
                    ]);
            }

            if ($vehicle->rowCount() === 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'The assigned vehicle could not be updated.'];
            }

            $pdo->commit();
            return [
                'success' => true,
                'arrived' => $arrived,
                'status' => $arrived ? 'arrived' : 'en_route',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function distanceKm(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2))
            * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    public static function requestVehicle(int $incidentId, int $responderId): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pending = $pdo->prepare(
                "SELECT id FROM incident_vehicle_requests
                 WHERE incident_id = ? AND status = 'pending' LIMIT 1 FOR UPDATE"
            );
            $pending->execute([$incidentId]);
            $existing = $pending->fetchColumn();
            if ($existing !== false) {
                $pdo->commit();
                return (int) $existing;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO incident_vehicle_requests (incident_id, responder_user_id)
                 VALUES (?, ?)'
            );
            $stmt->execute([$incidentId, $responderId]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function queueHospitalStaffAlerts(
        PDO $pdo,
        int $incidentId,
        array $report,
        array $patients
    ): void {
        $hospitalId = (int) ($report['hospital_id'] ?? 0);
        if ($hospitalId <= 0) {
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $staff = $pdo->prepare(
            "SELECT u.id AS user_id, u.role, s.department AS specialization,
                    shift.shift_date, shift.shift_start, shift.shift_end
             FROM user_type u
             INNER JOIN staff s ON s.id = (
                 SELECT candidate.id
                 FROM staff candidate
                 WHERE candidate.user_id = u.id
                   AND (candidate.hospital_id = u.hospital_id OR candidate.hospital_id IS NULL)
                 ORDER BY (candidate.hospital_id = u.hospital_id) DESC, candidate.id DESC
                 LIMIT 1
             )
             INNER JOIN staff_shifts shift ON shift.staff_id = s.id
               AND shift.shift_date IN (:today, :yesterday)
               AND shift.shift_status = 'on_duty'
             WHERE u.hospital_id = :hospital_id
               AND u.role IN ('doctor', 'nurse')
             ORDER BY u.id, shift.shift_date DESC"
        );
        $staff->execute([
            'today' => $now->format('Y-m-d'),
            'yesterday' => $now->modify('-1 day')->format('Y-m-d'),
            'hospital_id' => $hospitalId,
        ]);

        $message = implode("\n", [
            'New incident #' . $incidentId . ': ' . $report['incident_type'],
            'Status: ' . ucfirst((string) $report['status']),
            'Location: ' . $report['location'],
            'Coordinates: ' . $report['latitude'] . ', ' . $report['longitude'],
            'Hospital: ' . $report['hospital_name'],
            'Estimated arrival: ' . ($report['eta_minutes'] === null
                ? 'Unavailable'
                : $report['eta_minutes'] . ' min'),
        ]);
        $insert = $pdo->prepare(
            "INSERT INTO staff_alerts
                (incident_id, recipient_user_id, recipient_role, specialization, message, channel, status)
             VALUES (?, ?, ?, ?, ?, 'admin', 'queued')"
        );
        $notifiedIds = [];

        foreach ($staff->fetchAll() as $recipient) {
            $userId = (int) $recipient['user_id'];
            if (isset($notifiedIds[$userId]) || !self::isShiftActive($recipient, $now)) {
                continue;
            }
            $notifiedIds[$userId] = true;
            $insert->execute([
                $incidentId,
                $userId,
                $recipient['role'],
                $recipient['specialization'],
                $message,
            ]);
        }
    }

    private static function queueIncidentUpdateAlerts(
        PDO $pdo,
        int $incidentId,
        array $incident,
        string $note,
        string $authorName,
        bool $reporterOwnsIncident
    ): void {
        if ((int) ($incident['hospital_id'] ?? 0) <= 0) {
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $staff = $pdo->prepare(
            "SELECT u.id AS user_id, u.role, s.department AS specialization,
                    shift.shift_date, shift.shift_start, shift.shift_end
             FROM user_type u
             INNER JOIN staff s ON s.id = (
                 SELECT candidate.id FROM staff candidate
                 WHERE candidate.user_id = u.id
                   AND (candidate.hospital_id = u.hospital_id OR candidate.hospital_id IS NULL)
                 ORDER BY (candidate.hospital_id = u.hospital_id) DESC, candidate.id DESC
                 LIMIT 1
             )
             INNER JOIN staff_shifts shift ON shift.staff_id = s.id
               AND shift.shift_date IN (:today, :yesterday)
               AND shift.shift_status = 'on_duty'
             WHERE u.hospital_id = :hospital_id
               AND u.role IN ('doctor', 'nurse')
             ORDER BY u.id, shift.shift_date DESC"
        );
        $staff->execute([
            'today' => $now->format('Y-m-d'),
            'yesterday' => $now->modify('-1 day')->format('Y-m-d'),
            'hospital_id' => (int) $incident['hospital_id'],
        ]);

        $authorRole = $reporterOwnsIncident ? 'Reporter' : 'Responder';
        $message = implode("\n", [
            'Incident #' . $incidentId . ' update: ' . $incident['incident_type'],
            'Status: ' . ucfirst((string) $incident['status']),
            'Location: ' . $incident['location'],
            'Coordinates: ' . $incident['latitude'] . ', ' . $incident['longitude'],
            'Hospital: ' . $incident['hospital_name'],
            'Estimated arrival: ' . ($incident['eta_minutes'] === null
                ? 'Unavailable'
                : $incident['eta_minutes'] . ' min'),
            $authorRole . ': ' . $authorName,
            'Additional note: ' . $note,
        ]);
        $insert = $pdo->prepare(
            "INSERT INTO staff_alerts
                (incident_id, recipient_user_id, recipient_role, specialization, message, channel, status)
             VALUES (?, ?, ?, ?, ?, 'admin', 'queued')"
        );
        $notifiedIds = [];
        foreach ($staff->fetchAll() as $recipient) {
            $userId = (int) $recipient['user_id'];
            if (isset($notifiedIds[$userId]) || !self::isShiftActive($recipient, $now)) {
                continue;
            }
            $notifiedIds[$userId] = true;
            $insert->execute([
                $incidentId,
                $userId,
                $recipient['role'],
                $recipient['specialization'],
                $message,
            ]);
        }
    }

    private static function isShiftActive(array $shift, DateTimeImmutable $now): bool
    {
        if (empty($shift['shift_start']) || empty($shift['shift_end'])) {
            return false;
        }

        $start = new DateTimeImmutable(
            $shift['shift_date'] . ' ' . $shift['shift_start'],
            $now->getTimezone()
        );
        $end = new DateTimeImmutable(
            $shift['shift_date'] . ' ' . $shift['shift_end'],
            $now->getTimezone()
        );
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        return $now >= $start && $now < $end;
    }

    private static function activeHospitalFilter(): string
    {
        $pdo = Database::connection();
        $conditions = [];
        foreach (['is_active', 'archived_at'] as $column) {
            if (!self::hasColumn($pdo, 'hospitals', $column)) {
                continue;
            }
            $conditions[] = $column === 'is_active'
                ? 'is_active = 1'
                : 'archived_at IS NULL';
        }

        return $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (!array_key_exists($cacheKey, self::$columnCache)) {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
            );
            $statement->execute([$table, $column]);
            self::$columnCache[$cacheKey] = (int) $statement->fetchColumn() > 0;
        }

        return self::$columnCache[$cacheKey];
    }
}
