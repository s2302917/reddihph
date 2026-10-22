<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/DispatchModel.php';
require_once __DIR__ . '/VolunteerModel.php';

/**
 * Validation and data access for SOS incidents stored in the shared incidents table.
 */
final class IncidentModel
{
    private const GENDERS = ['male', 'female', 'other', 'unknown'];
    private const UNASSIGNED_HOSPITAL = 'Unassigned';

    /**
     * @return string[] validation error messages
     */
    public static function validateSos(array $data): array
    {
        $latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($latitude === false || $latitude < -90 || $latitude > 90
            || $longitude === false || $longitude < -180 || $longitude > 180) {
            return ['A valid device location is required.'];
        }

        return [];
    }

    /** Returns the hospital closest to the coordinates, or null when none has coordinates. */
    public static function nearestHospital(float $latitude, float $longitude): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, latitude, longitude, ' . DispatchModel::DISTANCE_SQL . ' AS distance_km
             FROM hospitals
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY distance_km
             LIMIT 1'
        );
        $stmt->execute(['lat1' => $latitude, 'lat2' => $latitude, 'lng' => $longitude]);

        return self::hospitalRow($stmt->fetch());
    }

    public static function hospitalById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, latitude, longitude FROM hospitals WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return self::hospitalRow($stmt->fetch());
    }

    /**
     * Inserts an SOS incident and any selected volunteer request atomically.
     *
     * @param array{latitude:float,longitude:float,reporter_user_id:?int,barangay:?array,hospital:?array,reporter:?array,
     *              eta_minutes:?int,responder_name:?string,transport:?array} $report
     */
    public static function createSos(array $report): int
    {
        $reporter = $report['reporter'];
        $transport = $report['transport'];
        $gender = $reporter['gender'] ?? null;

        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            if ($report['reporter_user_id'] !== null) {
                $pdo->prepare('SELECT id FROM user_type WHERE id = ? FOR UPDATE')
                    ->execute([(int) $report['reporter_user_id']]);
                $active = self::activeForReporter((int) $report['reporter_user_id']);
                if ($active !== null) {
                    if ($ownsTransaction) {
                        $pdo->rollBack();
                    }
                    return $active;
                }
            }
            $stmt = $pdo->prepare(
                'INSERT INTO incidents
                    (incident_type, reported_by, status, reporter_user_id, location, barangay_id, hospital_id,
                     hospital_name, latitude, longitude, patient_name, patient_age, patient_gender,
                     eta_minutes, responder_name, assigned_responder_user_id,
                     assigned_ambulance_id,
                     transport_name, transport_type, driver_name, notes)
                 VALUES
                    (:incident_type, :reported_by, :status, :reporter_user_id, :location, :barangay_id, :hospital_id,
                     :hospital_name, :latitude, :longitude, :patient_name, :patient_age, :patient_gender,
                     :eta_minutes, :responder_name, :assigned_responder_user_id,
                     :assigned_ambulance_id,
                     :transport_name, :transport_type, :driver_name, :notes)'
            );
            $stmt->execute([
                'incident_type' => $report['incident_type'] ?? 'SOS emergency alert',
                'reported_by' => $report['reported_by'] ?? 'resident_sos',
                'status' => $report['status'] ?? 'critical',
                'reporter_user_id' => $report['reporter_user_id'],
                'location' => $report['barangay']['name'] ?? sprintf(
                    'GPS %.6f, %.6f',
                    $report['latitude'],
                    $report['longitude']
                ),
                'barangay_id' => $report['barangay']['id'] ?? null,
                'hospital_id' => $report['hospital']['id'] ?? null,
                'hospital_name' => $report['hospital']['name'] ?? self::UNASSIGNED_HOSPITAL,
                'latitude' => $report['latitude'],
                'longitude' => $report['longitude'],
                'patient_name' => $report['patient_name'] ?? ($reporter['full_name'] ?? null),
                'patient_age' => $report['patient_age'] ?? ($reporter['age'] ?? null),
                'patient_gender' => in_array(
                    $report['patient_gender'] ?? $gender,
                    self::GENDERS,
                    true
                ) ? ($report['patient_gender'] ?? $gender) : 'unknown',
                'eta_minutes' => $report['eta_minutes'],
                'responder_name' => $report['responder_name'],
                'assigned_responder_user_id' => $report['responder']['id'] ?? null,
                'assigned_ambulance_id' => $transport['id'] ?? null,
                'transport_name' => $transport['call_sign'] ?? null,
                'transport_type' => $transport['vehicle_type'] ?? null,
                'driver_name' => $transport['driver_name'] ?? null,
                'notes' => $report['notes'] ?? 'Sent from the Reddiph mobile app.',
            ]);

            $incidentId = (int) $pdo->lastInsertId();
            if (isset($report['responder']['id'])) {
                $pdo->prepare(
                    "INSERT INTO responder_alerts (incident_id, responder_user_id)
                     VALUES (?, ?)"
                )->execute([$incidentId, (int) $report['responder']['id']]);
            }
            if (($report['request_another_vehicle'] ?? false)
                && isset($report['responder']['id'])) {
                $pdo->prepare(
                    'INSERT INTO incident_vehicle_requests (incident_id, responder_user_id) VALUES (?, ?)'
                )->execute([$incidentId, (int) $report['responder']['id']]);
            }
            if (isset($report['volunteer_user_id'])) {
                VolunteerModel::requestAssistance(
                    $pdo,
                    $incidentId,
                    (int) $report['volunteer_user_id'],
                    (int) $report['reporter_user_id']
                );
            }
            $patientRecords = $report['patient_records']
                ?? (isset($report['patient_record']) ? [$report['patient_record']] : []);
            if ($patientRecords !== []) {
                $insertPatient = $pdo->prepare(
                    'INSERT INTO incident_patients
                        (incident_id, patient_name, patient_age, patient_gender,
                         heart_rate, spo2, nurse_blood_pressure, notes, photo_filename)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insertCondition = $pdo->prepare(
                    'INSERT INTO incident_patient_conditions (patient_id, condition_code) VALUES (?, ?)'
                );
                foreach ($patientRecords as $patient) {
                    $insertPatient->execute([
                        $incidentId,
                        $patient['name'],
                        $patient['age'],
                        $patient['gender'],
                        $patient['heart_rate'],
                        $patient['spo2'],
                        $patient['blood_pressure'],
                        $patient['notes'],
                        $patient['photo_filename'],
                    ]);
                    $patientId = (int) $pdo->lastInsertId();
                    foreach ($patient['scene_conditions'] as $condition) {
                        $insertCondition->execute([$patientId, $condition]);
                    }
                }
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $incidentId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Live details of one incident for the reporter's emergency card. The distance is
     * measured from the assigned vehicle's last recorded position, or from the hospital
     * when the vehicle has not reported one, to the incident location.
     */
    public static function details(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.incident_id, i.incident_type, i.status, i.location, i.latitude, i.longitude,
                    i.reporter_user_id, i.patient_name, i.hospital_name, i.eta_minutes, i.responder_name,
                    i.assigned_responder_user_id, i.driver_name,
                    i.transport_name, i.transport_type, i.hospital_sent_at,
                    i.reported_at, i.responder_arrived_at,
                    i.patient_picked_up_at, i.hospital_arrived_at,
                    ra.created_at AS responder_assigned_at,
                    ra.acknowledged_at AS responder_acknowledged_at,
                    b.name AS barangay_name,
                    va.status AS volunteer_assistance_status, vu.full_name AS volunteer_name,
                    vr.rating AS volunteer_review_rating,
                    a.latitude AS vehicle_latitude, a.longitude AS vehicle_longitude,
                    COALESCE(h.name, i.hospital_name) AS assigned_hospital_name,
                    h.latitude AS hospital_latitude, h.longitude AS hospital_longitude,
                    rp.latitude AS responder_latitude, rp.longitude AS responder_longitude,
                    rb.name AS responder_barangay, ra.status AS responder_alert_status
             FROM incidents i
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN referrals latest_referral ON latest_referral.id = (
                SELECT r.id
                FROM referrals r
                WHERE r.incident_id = i.incident_id
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 1
             )
             LEFT JOIN hospitals h
                ON h.id = COALESCE(latest_referral.receiving_hospital_id, i.hospital_id)
             LEFT JOIN ambulances a
                ON a.id = i.assigned_ambulance_id
                OR (i.assigned_ambulance_id IS NULL
                    AND a.hospital_id = i.hospital_id
                    AND a.call_sign = i.transport_name)
             LEFT JOIN responder_presence rp
                ON rp.user_id = i.assigned_responder_user_id
               AND rp.last_seen_at >= NOW() - INTERVAL 90 SECOND
             LEFT JOIN user_type ru ON ru.id = i.assigned_responder_user_id
             LEFT JOIN barangays rb ON rb.id = ru.barangay_id
             LEFT JOIN responder_alerts ra
                ON ra.incident_id = i.incident_id
               AND ra.responder_user_id = i.assigned_responder_user_id
             LEFT JOIN volunteer_assistances va ON va.incident_id = i.incident_id
             LEFT JOIN user_type vu ON vu.id = va.volunteer_user_id
             LEFT JOIN volunteer_assistance_reviews vr
                ON vr.assistance_id = va.id
               AND vr.reviewer_user_id = i.reporter_user_id
             WHERE i.incident_id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $distanceKm = $distanceFrom = null;
        $etaMinutes = $row['hospital_sent_at'] !== null
            ? null
            : ($row['eta_minutes'] !== null ? (int) $row['eta_minutes'] : null);
        if ($row['hospital_sent_at'] !== null) {
            if ($row['hospital_latitude'] !== null
                && $row['hospital_longitude'] !== null
                && $row['responder_latitude'] !== null
                && $row['responder_longitude'] !== null) {
                $distanceKm = round(DispatchModel::distanceKm(
                    (float) $row['responder_latitude'],
                    (float) $row['responder_longitude'],
                    (float) $row['hospital_latitude'],
                    (float) $row['hospital_longitude']
                ), 1);
                $distanceFrom = 'responder';
                $etaMinutes = DispatchModel::etaMinutes(
                    (float) $row['responder_latitude'],
                    (float) $row['responder_longitude'],
                    (float) $row['hospital_latitude'],
                    (float) $row['hospital_longitude']
                );
            }
        } elseif ($row['latitude'] !== null && $row['longitude'] !== null) {
            foreach ([
                'responder' => 'responder',
                'vehicle' => 'vehicle',
                'hospital' => 'hospital',
            ] as $source => $prefix) {
                if ($row[$prefix . '_latitude'] !== null && $row[$prefix . '_longitude'] !== null) {
                    $fromLatitude = (float) $row[$prefix . '_latitude'];
                    $fromLongitude = (float) $row[$prefix . '_longitude'];
                    $distanceKm = round(DispatchModel::distanceKm(
                        $fromLatitude,
                        $fromLongitude,
                        (float) $row['latitude'], (float) $row['longitude']
                    ), 1);
                    $distanceFrom = $source;
                    if ($source === 'responder') {
                        $etaMinutes = DispatchModel::etaMinutes(
                            $fromLatitude,
                            $fromLongitude,
                            (float) $row['latitude'],
                            (float) $row['longitude']
                        );
                    }
                    break;
                }
            }
        }

        return [
            'incident_id' => (int) $row['incident_id'],
            'incident_type' => $row['incident_type'],
            'status' => $row['status'],
            'location' => $row['location'],
            'reporter_user_id' => $row['reporter_user_id'] !== null
                ? (int) $row['reporter_user_id']
                : null,
            'barangay' => $row['barangay_name'],
            'hospital' => $row['assigned_hospital_name'],
            'sent_to_hospital' => $row['hospital_sent_at'] !== null,
            'reported_at' => $row['reported_at'],
            'responder_assigned_at' => $row['responder_assigned_at'],
            'responder_acknowledged_at' => $row['responder_acknowledged_at'],
            'responder_arrived_at' => $row['responder_arrived_at'],
            'patient_picked_up_at' => $row['patient_picked_up_at'],
            'hospital_sent_at' => $row['hospital_sent_at'],
            'hospital_arrived_at' => $row['hospital_arrived_at'],
            'transport_name' => $row['transport_name'],
            'transport_type' => $row['transport_type'],
            'driver_name' => $row['driver_name'],
            'responder_name' => $row['responder_name'],
            'responder_user_id' => $row['assigned_responder_user_id'] !== null
                ? (int) $row['assigned_responder_user_id']
                : null,
            'responder_barangay' => $row['responder_barangay'],
            'responder_alert_status' => $row['responder_alert_status'],
            'responder_latitude' => $row['responder_latitude'] !== null
                ? (float) $row['responder_latitude']
                : null,
            'responder_longitude' => $row['responder_longitude'] !== null
                ? (float) $row['responder_longitude']
                : null,
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'hospital_latitude' => $row['hospital_latitude'] !== null
                ? (float) $row['hospital_latitude']
                : null,
            'hospital_longitude' => $row['hospital_longitude'] !== null
                ? (float) $row['hospital_longitude']
                : null,
            'eta_minutes' => $etaMinutes,
            'distance_km' => $distanceKm,
            'distance_from' => $distanceFrom,
            'patient_name' => $row['patient_name'],
            'volunteer_name' => $row['volunteer_name'],
            'volunteer_assistance_status' => $row['volunteer_assistance_status'],
            'volunteer_review_rating' => $row['volunteer_review_rating'] !== null
                ? (int) $row['volunteer_review_rating']
                : null,
        ];
    }

    /** Returns the newest open incident ID for this reporter. */
    public static function activeForReporter(int $reporterId): ?int
    {
        $stmt = Database::connection()->prepare(
            "SELECT incident_id FROM incidents
             WHERE reporter_user_id = ?
               AND status IN ('critical', 'urgent', 'stable', 'dispatched')
             ORDER BY reported_at DESC, incident_id DESC
             LIMIT 1"
        );
        $stmt->execute([$reporterId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Returns all cases reported by this resident, newest first. */
    public static function historyForReporter(int $reporterId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.incident_id, i.incident_type, i.reported_by, i.status,
                    i.location, i.reported_at, i.resolved_at, i.patient_name,
                    i.patient_age, i.patient_gender, i.eta_minutes,
                    i.responder_name, i.transport_name, i.transport_type,
                    i.driver_name, i.hospital_sent_at, i.responder_arrived_at,
                    i.patient_picked_up_at, i.hospital_arrived_at,
                    ra.created_at AS responder_assigned_at,
                    ra.acknowledged_at AS responder_acknowledged_at,
                    b.name AS barangay_name,
                    COALESCE(h.name, i.hospital_name) AS hospital_name,
                    fr.id AS false_report_id, fr.reason AS false_report_reason,
                    fr.notes AS false_report_notes
             FROM incidents i
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN referrals active_referral ON active_referral.id = (
                SELECT r.id
                FROM referrals r
                WHERE r.incident_id = i.incident_id
                  AND r.status IN (\'pending\', \'accepted\')
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 1
             )
             LEFT JOIN hospitals h
                ON h.id = COALESCE(active_referral.receiving_hospital_id, i.hospital_id)
             LEFT JOIN responder_alerts ra
                ON ra.incident_id = i.incident_id
               AND ra.responder_user_id = i.assigned_responder_user_id
             LEFT JOIN incident_false_reports fr ON fr.incident_id = i.incident_id
             WHERE i.reporter_user_id = ?
             ORDER BY i.reported_at DESC, i.incident_id DESC'
        );
        $stmt->execute([$reporterId]);

        return array_map(
            static function (array $row): array {
                return [
                    'incident_id' => (int) $row['incident_id'],
                    'incident_type' => (string) $row['incident_type'],
                    'reported_by' => (string) $row['reported_by'],
                    'status' => (string) $row['status'],
                    'location' => (string) $row['location'],
                    'reported_at' => $row['reported_at'],
                    'resolved_at' => $row['resolved_at'],
                    'patient_name' => $row['patient_name'],
                    'patient_age' => $row['patient_age'] !== null
                        ? (int) $row['patient_age']
                        : null,
                    'patient_gender' => $row['patient_gender'],
                    'eta_minutes' => $row['eta_minutes'] !== null
                        ? (int) $row['eta_minutes']
                        : null,
                    'responder_name' => $row['responder_name'],
                    'transport_name' => $row['transport_name'],
                    'transport_type' => $row['transport_type'],
                    'driver_name' => $row['driver_name'],
                    'hospital' => $row['hospital_name'],
                    'barangay' => $row['barangay_name'],
                    'responder_assigned_at' => $row['responder_assigned_at'],
                    'responder_acknowledged_at' => $row['responder_acknowledged_at'],
                    'responder_arrived_at' => $row['responder_arrived_at'],
                    'patient_picked_up_at' => $row['patient_picked_up_at'],
                    'hospital_sent_at' => $row['hospital_sent_at'],
                    'hospital_arrived_at' => $row['hospital_arrived_at'],
                    'false_report_id' => $row['false_report_id'] !== null
                        ? (int) $row['false_report_id']
                        : null,
                    'false_report_reason' => $row['false_report_reason'],
                    'false_report_notes' => $row['false_report_notes'],
                ];
            },
            $stmt->fetchAll()
        );
    }

    private static function hospitalRow(array|false $row): ?array
    {
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
        ];
    }
}
