<?php

class LivemappingModel
{
    private ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (Throwable $e) {
            error_log('[LivemappingModel] Database connection failed: ' . $e->getMessage());
        }
    }

    public function getMapData(
        ?string $barangaySlug = null,
        ?int $hospitalId = null,
        bool $includeStaffRecipients = true
    ): array
    {
        if (!$hospitalId) {
            return [
                'ambulances' => [],
                'incidents' => [],
                'fleet_ambulances' => [],
                'fleet_incidents' => [],
                'hospitals' => [],
                'recipients' => [],
            ];
        }

        $this->ensureFleetSeeds($hospitalId);
        $this->syncIncidentTransports($hospitalId);
        $incidents = $this->getIncidents(null, $hospitalId);
        $ambulances = $this->getAmbulances(null, $hospitalId);
        if ($barangaySlug !== null && $barangaySlug !== '') {
            $barangayName = $this->getBarangayName($barangaySlug, $hospitalId);
            if ($barangayName === null) {
                $incidents = [];
            } else {
                $incidents = array_values(array_filter(
                    $incidents,
                    static function (array $incident) use ($barangayName): bool {
                        $incidentBarangay = trim((string) ($incident['barangay_name'] ?? ''));
                        $incidentLocation = (string) ($incident['location'] ?? '');

                        return strcasecmp($incidentBarangay, $barangayName) === 0
                            || stripos($incidentLocation, $barangayName) !== false;
                    }
                ));
            }

            $incidentIds = [];
            foreach ($incidents as $incident) {
                $incidentIds[(string) $incident['id']] = true;
            }
            $ambulances = array_values(array_filter(
                $ambulances,
                static function (array $ambulance) use ($incidentIds): bool {
                    $status = strtolower((string) ($ambulance['status'] ?? ''));
                    return isset($incidentIds[(string) ($ambulance['incident_id'] ?? '')])
                        || in_array($status, ['available', 'offline'], true);
                }
            ));
        }

        return [
            'ambulances' => $ambulances,
            'incidents' => $incidents,
            'fleet_ambulances' => $this->getAllFleetVehicles(),
            'fleet_incidents' => $this->getAllFleetIncidents(),
            'hospitals' => $this->getHospitals(),
            'recipients' => $includeStaffRecipients ? $this->getStaffRecipients($hospitalId) : [],
        ];
    }

    private function getBarangayName(string $slug, int $hospitalId): ?string
    {
        if (!$this->db) {
            throw new RuntimeException('Database connection is unavailable.');
        }

        $statement = $this->db->prepare(
            'SELECT name FROM barangays WHERE slug = :slug AND hospital_id = :hospital_id LIMIT 1'
        );
        $statement->execute([
            'slug' => $slug,
            'hospital_id' => $hospitalId,
        ]);
        $name = $statement->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function getStaffAlertReceipts(int $hospitalId): array
    {
        if (!$this->db) {
            throw new RuntimeException('Database connection is unavailable.');
        }

        $statement = $this->db->prepare(
            "SELECT
                alert.id,
                alert.incident_id,
                alert.recipient_role,
                alert.status,
                alert.created_at,
                incident.incident_type,
                incident.patient_name,
                recipient.full_name AS recipient_name
            FROM staff_alerts alert
            INNER JOIN user_type recipient
                ON recipient.id = alert.recipient_user_id
                AND recipient.role = alert.recipient_role
                AND recipient.hospital_id = :hospital_id
            LEFT JOIN incidents incident ON incident.incident_id = alert.incident_id
            WHERE alert.channel = 'admin'
            ORDER BY alert.id DESC
            LIMIT 100"
        );
        $statement->execute(['hospital_id' => $hospitalId]);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'incidentId' => $row['incident_id'] === null ? null : (int) $row['incident_id'],
                'incident' => (string) ($row['incident_type'] ?? 'Emergency incident'),
                'patient' => (string) ($row['patient_name'] ?? ''),
                'recipient' => (string) ($row['recipient_name'] ?? 'Staff member'),
                'role' => (string) $row['recipient_role'],
                'status' => (string) $row['status'],
                'createdAt' => (string) $row['created_at'],
            ];
        }, $statement->fetchAll());
    }

    public function getAlertStaffOptions(int $incidentId, ?int $hospitalId): array
    {
        if (!$this->db || !$hospitalId || $incidentId <= 0) {
            return ['success' => false, 'message' => 'The selected incident could not be found. Please refresh the page and try again.'];
        }

        $incident = $this->getIncidentForAlert($incidentId, $hospitalId);
        if (!$incident) {
            return ['success' => false, 'message' => 'The selected incident could not be found. Please refresh the page and try again.'];
        }

        $recipients = $this->getEligibleStaffForAlert($hospitalId);
        $specializations = [];
        foreach ($recipients as $person) {
            $specialization = trim((string) ($person['specialization'] ?? ''));
            if ($specialization !== '' && !in_array($specialization, $specializations, true)) {
                $specializations[] = $specialization;
            }
        }
        sort($specializations, SORT_STRING);

        return [
            'success' => true,
            'incident' => $incident,
            'specializations' => $specializations,
            'recipients' => $recipients,
            'message' => $recipients ? '' : 'No doctors or nurses are currently on duty or logged in.',
        ];
    }

    public function validateAlertRecipients(array $recipientIds, ?int $hospitalId, ?string $specialization = null): array
    {
        if (!$this->db || !$hospitalId || !$recipientIds) {
            return [];
        }

        $normalizedIds = array_values(array_unique(array_map('intval', $recipientIds ?? [])));
        $requestedMap = array_fill_keys($normalizedIds, true);
        $eligibleRecipients = $this->getEligibleStaffForAlert($hospitalId, $specialization);

        return array_values(array_filter($eligibleRecipients, function (array $recipient) use ($requestedMap): bool {
            return isset($requestedMap[(int) ($recipient['user_id'] ?? 0)]);
        }));
    }

    private function isScheduledShiftActive(
        string $shiftDate,
        ?string $shiftStart,
        ?string $shiftEnd,
        DateTimeImmutable $now
    ): bool
    {
        if (!$shiftStart || !$shiftEnd) {
            return false;
        }

        $start = new DateTimeImmutable($shiftDate . ' ' . $shiftStart, $now->getTimezone());
        $end = new DateTimeImmutable($shiftDate . ' ' . $shiftEnd, $now->getTimezone());
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        return $now >= $start && $now < $end;
    }

    public function sendStaffAlert(array $data, ?int $hospitalId): array
    {
        if (!$this->db || !$hospitalId) {
            return ['success' => false, 'message' => 'The selected incident could not be found. Please refresh the page and try again.'];
        }

        $incidentId = (int) ($data['incident_id'] ?? 0);
        if ($incidentId <= 0) {
            return ['success' => false, 'message' => 'The selected incident could not be found. Please refresh the page and try again.'];
        }

        $incident = $this->getIncidentForAlert($incidentId, $hospitalId);
        if (!$incident) {
            return ['success' => false, 'message' => 'The selected incident could not be found. Please refresh the page and try again.'];
        }

        $specialization = trim((string) ($data['specialization'] ?? ''));
        if ($specialization === '' || strtolower($specialization) === 'all specializations' || strtolower($specialization) === 'all') {
            $specialization = null;
        }

        $rawRecipientIds = $data['recipient_ids'] ?? null;
        if (!is_array($rawRecipientIds) || !$rawRecipientIds) {
            return ['success' => false, 'message' => 'Select at least one eligible staff member before sending the alert.'];
        }
        $requestedIds = array_values(array_unique(array_map('intval', $rawRecipientIds)));
        $eligibleRecipients = $this->getEligibleStaffForAlert($hospitalId, $specialization);

        if (!$eligibleRecipients) {
            return ['success' => false, 'message' => 'No doctors or nurses are currently on duty or logged in.'];
        }

        $eligibleMap = [];
        foreach ($eligibleRecipients as $recipient) {
            $eligibleMap[(int) $recipient['id']] = $recipient;
        }

        $selectedRecipients = [];
        if ($requestedIds) {
            foreach ($requestedIds as $recipientId) {
                if (isset($eligibleMap[$recipientId])) {
                    $selectedRecipients[] = $eligibleMap[$recipientId];
                }
            }
        } else {
            $selectedRecipients = $eligibleRecipients;
        }

        if (!$selectedRecipients) {
            return ['success' => false, 'message' => 'The selected staff are no longer on duty or logged in. The alert was not sent.'];
        }

        $sentRecipients = [];
        $message = $this->buildStaffAlertMessage($incident, trim((string) ($data['message'] ?? '')));
        try {
            $this->db->beginTransaction();
            $insert = $this->db->prepare('INSERT INTO staff_alerts (incident_id, transport_name, recipient_user_id, recipient_role, specialization, message, channel, status)
                VALUES (:incident_id, :transport_name, :recipient_user_id, :recipient_role, :specialization, :message, :channel, :status)');
            foreach ($selectedRecipients as $recipient) {
                $recipientUserId = (int) ($recipient['user_id'] ?? 0);
                if ($recipientUserId <= 0) {
                    continue;
                }

                $insert->execute([
                    ':incident_id' => $incidentId,
                    ':transport_name' => trim((string) ($data['transport_name'] ?? '')) ?: null,
                    ':recipient_user_id' => $recipientUserId,
                    ':recipient_role' => strtolower((string) $recipient['role']),
                    ':specialization' => trim((string) ($recipient['specialization'] ?? '')) ?: null,
                    ':message' => $message,
                    ':channel' => 'admin',
                    ':status' => 'queued',
                ]);

                $sentRecipients[] = $recipient['full_name'] ?? 'Staff member';
            }
            if (!$sentRecipients) {
                throw new RuntimeException('No eligible staff recipients were available for this alert.');
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[LivemappingModel] Staff alert insert failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'The staff alert could not be saved. Please try again.'];
        }

        $response = [
            'success' => true,
            'message' => 'Alert Sent Successfully',
            'incident_id' => $incidentId,
            'incident_type' => $incident['incident_type'] ?? 'Emergency incident',
            'count' => count($sentRecipients),
            'sent_to' => $sentRecipients,
        ];

        return $response;
    }

    public function markTransportArrived(int $incidentId, string $transportName, ?int $hospitalId): bool
    {
        if (!$this->db || !$incidentId || !$hospitalId) {
            return false;
        }

        try {
            $this->db->beginTransaction();
            $arrivedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');

            $incidentSql = "UPDATE incidents
                SET hospital_arrived_at = COALESCE(hospital_arrived_at, :hospital_arrived_at),
                    resolved_at = :resolved_at
                WHERE incident_id = :incident_id
                AND hospital_id = :hospital_id
                AND status IN ('critical', 'urgent', 'dispatched', 'stable')";
            $incidentParams = [
                ':hospital_arrived_at' => $arrivedAt,
                ':resolved_at' => $arrivedAt,
                ':incident_id' => $incidentId,
                ':hospital_id' => $hospitalId,
            ];
            $transportName = trim($transportName);
            if ($transportName !== '') {
                $incidentSql .= ' AND transport_name = :incident_transport_name';
                $incidentParams[':incident_transport_name'] = $transportName;
            } else {
                $incidentSql .= " AND reported_by = 'on_scene'";
            }
            $incident = $this->db->prepare($incidentSql);
            $incident->execute($incidentParams);

            if ($incident->rowCount() === 0) {
                $this->db->rollBack();
                return false;
            }

            if ($transportName !== '') {
                $ambulance = $this->db->prepare("UPDATE ambulances
                    SET status = 'arrived', arrived_at = :arrived_at, eta_minutes = 0
                    WHERE call_sign = :transport_name AND hospital_id = :hospital_id");
                $ambulance->execute([
                    ':arrived_at' => $arrivedAt,
                    ':transport_name' => $transportName,
                    ':hospital_id' => $hospitalId,
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[LivemappingModel] Arrival update failed: ' . $e->getMessage());
            return false;
        }
    }

    public function updateTransportLocation(int $incidentId, string $transportName, float $latitude, float $longitude, ?int $hospitalId): array
    {
        if (
            !$this->db || !$incidentId || !$hospitalId || trim($transportName) === '' ||
            $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
        ) {
            return ['success' => false, 'message' => 'Invalid transport location update.'];
        }

        try {
            $this->db->beginTransaction();
            $incident = $this->db->prepare("SELECT i.incident_id, h.latitude AS hospital_latitude,
                    h.longitude AS hospital_longitude
                FROM incidents i
                INNER JOIN ambulances a
                    ON a.call_sign = :call_sign
                    AND a.hospital_id = :ambulance_hospital_id
                LEFT JOIN referrals active_referral ON active_referral.id = (
                    SELECT latest_referral.id
                    FROM referrals latest_referral
                    WHERE latest_referral.incident_id = i.incident_id
                    ORDER BY latest_referral.created_at DESC, latest_referral.id DESC
                    LIMIT 1
                ) AND active_referral.status IN ('pending', 'accepted')
                INNER JOIN hospitals h ON h.id = COALESCE(active_referral.receiving_hospital_id, i.hospital_id)
                WHERE i.incident_id = :incident_id
                AND i.hospital_id = :incident_hospital_id
                AND i.transport_name = :incident_transport_name
                AND i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                AND a.status <> 'arrived'
                LIMIT 1 FOR UPDATE");
            $incident->execute([
                ':call_sign' => $transportName,
                ':ambulance_hospital_id' => $hospitalId,
                ':incident_id' => $incidentId,
                ':incident_hospital_id' => $hospitalId,
                ':incident_transport_name' => $transportName,
            ]);
            $assignment = $incident->fetch();
            if (!$assignment) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'No active incident is assigned to this transport.'];
            }

            $distanceToHospital = null;
            if ($assignment['hospital_latitude'] !== null && $assignment['hospital_longitude'] !== null) {
                $distanceToHospital = $this->distanceKm(
                    $latitude,
                    $longitude,
                    (float) $assignment['hospital_latitude'],
                    (float) $assignment['hospital_longitude']
                );
            }

            $arrived = $this->hasReachedHospital($distanceToHospital);
            $timestamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
            $remainingEta = $arrived || $distanceToHospital === null
                ? null
                : $this->estimateEtaMinutes(
                    $latitude,
                    $longitude,
                    (float) $assignment['hospital_latitude'],
                    (float) $assignment['hospital_longitude']
                );

            if ($arrived) {
                $resolveIncident = $this->db->prepare("UPDATE incidents
                    SET resolved_at = :resolved_at
                    WHERE incident_id = :incident_id AND hospital_id = :hospital_id
                    AND transport_name = :incident_transport
                    AND status IN ('critical', 'urgent', 'dispatched', 'stable')");
                $resolveIncident->execute([
                    ':resolved_at' => $timestamp,
                    ':incident_id' => $incidentId,
                    ':hospital_id' => $hospitalId,
                    ':incident_transport' => $transportName,
                ]);

                if ($resolveIncident->rowCount() === 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'The assigned incident is no longer active.'];
                }

                $updateAmbulance = $this->db->prepare("UPDATE ambulances
                    SET latitude = :latitude, longitude = :longitude,
                        location_recorded_at = :recorded_at,
                    status = 'arrived', arrived_at = :arrived_at, eta_minutes = 0
                    WHERE call_sign = :call_sign AND hospital_id = :hospital_id AND status <> 'arrived'");
                $updateAmbulance->execute([
                    ':latitude' => $latitude,
                    ':longitude' => $longitude,
                    ':recorded_at' => $timestamp,
                    ':arrived_at' => $timestamp,
                    ':call_sign' => $transportName,
                    ':hospital_id' => $hospitalId,
                ]);
            } else {
                $updateAmbulance = $this->db->prepare("UPDATE ambulances
                    SET latitude = :latitude, longitude = :longitude,
                        location_recorded_at = :recorded_at,
                        eta_minutes = COALESCE(:eta_minutes, eta_minutes)
                    WHERE call_sign = :call_sign AND hospital_id = :hospital_id AND status <> 'arrived'");
                $updateAmbulance->execute([
                    ':latitude' => $latitude,
                    ':longitude' => $longitude,
                    ':recorded_at' => $timestamp,
                    ':eta_minutes' => $remainingEta,
                    ':call_sign' => $transportName,
                    ':hospital_id' => $hospitalId,
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'arrived' => $arrived,
                'status' => $arrived ? 'available' : 'en_route',
                'eta_minutes' => $arrived ? 0 : $remainingEta,
                'message' => $arrived ? 'Incident resolved and transport is available.' : 'Transport location updated.',
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[LivemappingModel] Location update failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to save the transport location.'];
        }
    }

    private function estimateEtaMinutes(float $startLatitude, float $startLongitude, float $endLatitude, float $endLongitude): int
    {
        $distanceKm = $this->distanceKm($startLatitude, $startLongitude, $endLatitude, $endLongitude);

        // Apply a road-distance factor to the straight-line estimate; GPS fixes refine this ETA en route.
        return (int) max(1, ceil(($distanceKm * 1.3 / 35) * 60));
    }

    private function distanceKm(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $latitude1Radians = deg2rad($latitude1);
        $latitude2Radians = deg2rad($latitude2);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($latitude1Radians) * cos($latitude2Radians) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    private function hasReachedHospital(?float $distanceKm): bool
    {
        return $distanceKm !== null && $distanceKm <= 0.15;
    }

    public function updateIncidentEta(int $incidentId, string $transportName, int $etaMinutes, ?int $hospitalId): bool
    {
        if (!$this->db || !$incidentId || !$hospitalId || trim($transportName) === '' || $etaMinutes < 0 || $etaMinutes > 1440) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $incident = $this->db->prepare("SELECT i.incident_id, i.eta_minutes AS incident_eta
                FROM incidents i
                INNER JOIN ambulances a
                    ON a.call_sign = :ambulance_call_sign
                    AND a.hospital_id = :ambulance_hospital_id
                WHERE i.incident_id = :incident_id
                AND i.hospital_id = :incident_hospital_id
                AND i.transport_name = :incident_transport_name
                AND i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                LIMIT 1 FOR UPDATE");
            $incident->execute([
                ':incident_id' => $incidentId,
                ':incident_hospital_id' => $hospitalId,
                ':incident_transport_name' => $transportName,
                ':ambulance_call_sign' => $transportName,
                ':ambulance_hospital_id' => $hospitalId,
            ]);

            $incidentRow = $incident->fetch();
            if (!$incidentRow) {
                $this->db->rollBack();
                return false;
            }

            $firstEta = $incidentRow['incident_eta'] !== null
                ? (int) $incidentRow['incident_eta']
                : $etaMinutes;
            $saveFirstIncidentEta = $this->db->prepare("UPDATE incidents
                SET eta_minutes = :eta_minutes
                WHERE incident_id = :incident_id AND hospital_id = :hospital_id
                AND eta_minutes IS NULL");
            $saveFirstIncidentEta->execute([
                ':eta_minutes' => $firstEta,
                ':incident_id' => $incidentId,
                ':hospital_id' => $hospitalId,
            ]);

            $updateAmbulance = $this->db->prepare("UPDATE ambulances
                SET eta_minutes = :eta_minutes
                WHERE call_sign = :call_sign AND hospital_id = :hospital_id");
            $updateAmbulance->execute([
                ':eta_minutes' => $etaMinutes,
                ':call_sign' => $transportName,
                ':hospital_id' => $hospitalId,
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[LivemappingModel] ETA update failed: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureFleetSeeds(?int $hospitalId): void
    {
        // Fleet records are managed in the database reset/administration flow.
        // Mapping must not create demo vehicles during a normal page request.
    }

    public function getStaffRecipients(?int $hospitalId): array
    {
        return $this->getEligibleStaffForAlert($hospitalId);
    }

    private function getEligibleStaffForAlert(?int $hospitalId, ?string $specialization = null): array
    {
        if (!$this->db || !$hospitalId) {
            return [];
        }

        $timezone = new DateTimeZone('Asia/Manila');
        $now = new DateTimeImmutable('now', $timezone);
        $sql = "SELECT u.id, u.id AS user_id, u.full_name, u.role,
            u.last_login_at,
            s.department AS specialization,
            s.is_available,
            scheduled_shift.shift_date,
            scheduled_shift.shift_start,
            scheduled_shift.shift_end,
            scheduled_shift.shift_status
            FROM user_type u
            LEFT JOIN staff s ON s.id = (
                SELECT candidate.id FROM staff candidate
                WHERE candidate.user_id = u.id
                AND (candidate.hospital_id = u.hospital_id OR candidate.hospital_id IS NULL)
                ORDER BY (candidate.hospital_id = u.hospital_id) DESC, candidate.id DESC
                LIMIT 1
            )
            LEFT JOIN staff_shifts scheduled_shift
                ON scheduled_shift.staff_id = s.id
                AND scheduled_shift.shift_date IN (:shift_today, :shift_yesterday)
            WHERE u.hospital_id = :hospital_id
            AND u.role IN ('doctor', 'nurse')
            AND (:specialization_null IS NULL OR :specialization_empty = '' OR s.department = :specialization_match)
            ORDER BY u.full_name ASC, (scheduled_shift.shift_status = 'on_duty') DESC, scheduled_shift.shift_date DESC";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':hospital_id' => $hospitalId,
            ':shift_today' => $now->format('Y-m-d'),
            ':shift_yesterday' => $now->modify('-1 day')->format('Y-m-d'),
            ':specialization_null' => $specialization,
            ':specialization_empty' => $specialization,
            ':specialization_match' => $specialization,
        ]);

        $eligibleRecipients = [];
        $eligibleIds = [];
        foreach ($statement->fetchAll() as $recipient) {
            $isOnDuty = $recipient['shift_status'] === 'on_duty'
                && $recipient['shift_date'] !== null
                && $this->isScheduledShiftActive(
                    (string) $recipient['shift_date'],
                    $recipient['shift_start'] === null ? null : (string) $recipient['shift_start'],
                    $recipient['shift_end'] === null ? null : (string) $recipient['shift_end'],
                    $now
                );
            $isOnline = !empty($recipient['last_login_at']);
            if (!$isOnDuty && !$isOnline) {
                continue;
            }

            $recipient['id'] = (int) $recipient['id'];
            $recipient['user_id'] = (int) $recipient['user_id'];
            $recipient['is_online'] = $isOnline;
            $recipient['duty_status'] = $isOnDuty && $isOnline
                ? 'On duty · logged in'
                : ($isOnDuty ? 'On duty' : 'Logged in');
            if (isset($eligibleIds[$recipient['user_id']])) {
                continue;
            }
            $eligibleIds[$recipient['user_id']] = true;
            $eligibleRecipients[] = $recipient;
        }

        return $eligibleRecipients;
    }

    private function getIncidentForAlert(int $incidentId, ?int $hospitalId = null): ?array
    {
        if (!$this->db) {
            return null;
        }

        $sql = "SELECT i.incident_id, i.incident_type, i.status, i.location, i.barangay_id, i.hospital_id,
            i.hospital_name, i.latitude, i.longitude, i.patient_name, i.patient_gender, i.patient_age,
            i.eta_minutes AS eta, i.transport_name, i.transport_type, i.reported_at, i.resolved_at,
            i.created_at, i.updated_at, i.notes,
            COALESCE(b.name, fallback_b.name) AS barangay_name,
            COALESCE(
                (
                    SELECT responder.full_name
                    FROM user_type responder
                    WHERE responder.hospital_id = i.hospital_id
                      AND responder.full_name = i.responder_name
                      AND responder.role IN ('responder', 'volunteer')
                    ORDER BY responder.id
                    LIMIT 1
                ),
                NULLIF(TRIM(vehicle.driver_name), ''),
                NULLIF(TRIM(i.driver_name), '')
            ) AS assigned_responder_name,
            COALESCE(NULLIF(TRIM(vehicle.driver_name), ''), NULLIF(TRIM(i.driver_name), '')) AS driver_name
            FROM incidents i
            LEFT JOIN barangays b ON b.id = i.barangay_id
            LEFT JOIN barangays fallback_b
                ON fallback_b.hospital_id = i.hospital_id
                AND i.location LIKE CONCAT('%', fallback_b.name, '%')
            LEFT JOIN ambulances vehicle
                ON vehicle.call_sign = i.transport_name AND vehicle.hospital_id = i.hospital_id
            WHERE i.incident_id = :incident_id
            AND (
                :hospital_id_is_null IS NULL
                OR i.hospital_id = :hospital_id_match
                OR EXISTS (
                    SELECT 1
                    FROM referrals active_referral
                    WHERE active_referral.incident_id = i.incident_id
                      AND active_referral.id = (
                          SELECT latest_referral.id
                          FROM referrals latest_referral
                          WHERE latest_referral.incident_id = i.incident_id
                          ORDER BY latest_referral.created_at DESC, latest_referral.id DESC
                          LIMIT 1
                      )
                      AND active_referral.receiving_hospital_id = :referral_hospital_id
                      AND active_referral.status IN ('pending', 'accepted')
                )
            )
            LIMIT 1";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':incident_id' => $incidentId,
            ':hospital_id_is_null' => $hospitalId,
            ':hospital_id_match' => $hospitalId,
            ':referral_hospital_id' => $hospitalId,
        ]);

        $incident = $statement->fetch();
        if (!$incident) {
            return null;
        }

        return [
            'incident_id' => (int) ($incident['incident_id'] ?? 0),
            'incident_type' => $incident['incident_type'] ?? 'Emergency incident',
            'status' => strtoupper((string) ($incident['status'] ?? 'critical')),
            'location' => $incident['location'] ?? 'Location unavailable',
            'barangay_name' => $incident['barangay_name'] ?? null,
            'hospital_name' => $incident['hospital_name'] ?? 'Hospital not available',
            'latitude' => $incident['latitude'] ?? null,
            'longitude' => $incident['longitude'] ?? null,
            'patient_name' => $incident['patient_name'] ?? 'Patient name unavailable',
            'patient_gender' => $incident['patient_gender'] ?? 'unknown',
            'patient_age' => $incident['patient_age'] !== null ? (int) $incident['patient_age'] : null,
            'eta' => $incident['eta'] !== null ? (int) $incident['eta'] : null,
            'responder_name' => $incident['assigned_responder_name'] ?? null,
            'transport_name' => $incident['transport_name'] ?? null,
            'transport_type' => $incident['transport_type'] ?? null,
            'driver_name' => $incident['driver_name'] ?? null,
            'reported_at' => $incident['reported_at'] ?? null,
            'resolved_at' => $incident['resolved_at'] ?? null,
            'notes' => $incident['notes'] ?? null,
        ];
    }

    private function buildStaffAlertMessage(array $incident, string $additionalMessage = ''): string
    {
        $gender = strtolower((string) ($incident['patient_gender'] ?? ''));
        $gender = $gender !== '' && $gender !== 'unknown' ? ucfirst($gender) : 'Unknown';
        $age = $incident['patient_age'] !== null ? (string) $incident['patient_age'] : 'Unknown';
        $coordinates = $incident['latitude'] !== null && $incident['longitude'] !== null
            ? $incident['latitude'] . ', ' . $incident['longitude']
            : 'Unavailable';

        $message = implode("\n", [
            'Emergency incident #' . (int) $incident['incident_id'] . ': ' . ($incident['incident_type'] ?? 'Emergency incident'),
            'Status: ' . ($incident['status'] ?? 'Unknown'),
            'Patient: ' . ($incident['patient_name'] ?? 'Patient name unavailable'),
            'Gender / Age: ' . $gender . ' / ' . $age,
            'Location: ' . ($incident['location'] ?? 'Location unavailable'),
            'Barangay: ' . ($incident['barangay_name'] ?? 'Unavailable'),
            'Assigned responder: ' . ($incident['responder_name'] ?? 'Unassigned'),
            'Transport: ' . ($incident['transport_name'] ?? 'Unassigned')
            . (!empty($incident['transport_type']) ? ' (' . $incident['transport_type'] . ')' : ''),
            'Driver: ' . ($incident['driver_name'] ?? 'Unassigned'),
            'Hospital: ' . ($incident['hospital_name'] ?? 'Unavailable'),
            'ETA: ' . ($incident['eta'] !== null ? $incident['eta'] . ' min' : 'Unavailable'),
            'Reported: ' . ($incident['reported_at'] ?? 'Unavailable'),
            'Resolved: ' . ($incident['resolved_at'] ?? 'Not resolved'),
            'Coordinates: ' . $coordinates,
            'Notes: ' . ($incident['notes'] ?? 'None'),
        ]);

        if ($additionalMessage !== '') {
            $message .= "\nAdditional message: " . $additionalMessage;
        }

        return $message;
    }

    private function syncIncidentTransports(?int $hospitalId): void
    {
        if (!$this->db || !$hospitalId) {
            return;
        }

        try {
            $incidents = $this->fetchAll("SELECT i.incident_id, i.transport_name, i.transport_type,
                i.incident_type, i.latitude, i.longitude, i.eta_minutes, i.reported_at
                FROM incidents i
                WHERE i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                AND i.hospital_id = " . (int) $hospitalId . "
                AND i.transport_name IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = i.incident_id
                )
                AND (COALESCE(i.reported_by, '') <> 'resident_sos' OR i.hospital_sent_at IS NOT NULL)");
            foreach ($incidents as $incident) {
                $insert = $this->db->prepare("INSERT INTO ambulances
                    (call_sign, hospital_id, status, assignment_note, vehicle_type)
                    VALUES (:call_sign, :hospital_id, 'en_route', :assignment_note, :vehicle_type)
                        ON DUPLICATE KEY UPDATE
                        status = CASE
                            WHEN arrived_at IS NOT NULL
                                AND arrived_at >= :arrival_after_reported_at
                            THEN 'arrived'
                            ELSE 'en_route'
                        END,
                        arrived_at = CASE
                            WHEN arrived_at IS NOT NULL
                                AND arrived_at < :clear_arrival_before_reported_at
                            THEN NULL
                            ELSE arrived_at
                        END,
                        assignment_note = VALUES(assignment_note),
                        vehicle_type = VALUES(vehicle_type)");
                $insert->execute([
                    ':call_sign' => $incident['transport_name'],
                    ':hospital_id' => $hospitalId,
                    ':assignment_note' => $incident['incident_type'],
                    ':vehicle_type' => $incident['transport_type'] ?: 'Ambulance',
                    ':arrival_after_reported_at' => $incident['reported_at'],
                    ':clear_arrival_before_reported_at' => $incident['reported_at'],
                ]);

                if ($incident['latitude'] !== null && $incident['longitude'] !== null) {
                    $location = $this->db->prepare("UPDATE ambulances
                        SET latitude = :latitude, longitude = :longitude, location_recorded_at = NOW()
                        WHERE call_sign = :call_sign AND hospital_id = :hospital_id
                        AND (latitude IS NULL OR longitude IS NULL)");
                    $location->execute([
                        ':latitude' => $incident['latitude'],
                        ':longitude' => $incident['longitude'],
                        ':call_sign' => $incident['transport_name'],
                        ':hospital_id' => $hospitalId,
                    ]);
                }

                $initialEta = $incident['eta_minutes'] !== null
                    ? (int) $incident['eta_minutes']
                    : null;
                if ($initialEta === null) {
                    $hospital = $this->db->prepare('SELECT latitude, longitude FROM hospitals WHERE id = :hospital_id LIMIT 1');
                    $hospital->execute([':hospital_id' => $hospitalId]);
                    $hospitalCoordinates = $hospital->fetch();
                    if (
                        $incident['latitude'] !== null && $incident['longitude'] !== null &&
                        $hospitalCoordinates && $hospitalCoordinates['latitude'] !== null && $hospitalCoordinates['longitude'] !== null
                    ) {
                        $initialEta = $this->estimateEtaMinutes(
                            (float) $incident['latitude'],
                            (float) $incident['longitude'],
                            (float) $hospitalCoordinates['latitude'],
                            (float) $hospitalCoordinates['longitude']
                        );
                        $saveInitialEta = $this->db->prepare('UPDATE incidents SET eta_minutes = :eta_minutes WHERE incident_id = :incident_id AND hospital_id = :hospital_id AND eta_minutes IS NULL');
                        $saveInitialEta->execute([
                            ':eta_minutes' => $initialEta,
                            ':incident_id' => $incident['incident_id'],
                            ':hospital_id' => $hospitalId,
                        ]);
                    }
                }

                if ($initialEta !== null) {
                    $saveFleetEta = $this->db->prepare('UPDATE ambulances SET eta_minutes = :eta_minutes WHERE call_sign = :call_sign AND hospital_id = :hospital_id AND eta_minutes IS NULL');
                    $saveFleetEta->execute([
                        ':eta_minutes' => $initialEta,
                        ':call_sign' => $incident['transport_name'],
                        ':hospital_id' => $hospitalId,
                    ]);
                }
            }

            $backfillEta = $this->db->prepare("UPDATE ambulances a
                INNER JOIN incidents i
                    ON i.transport_name = a.call_sign
                    AND i.hospital_id = a.hospital_id
                SET a.eta_minutes = CASE WHEN a.status = 'arrived' THEN 0 ELSE i.eta_minutes END
                WHERE a.hospital_id = :hospital_id
                AND a.eta_minutes IS NULL
                AND (i.eta_minutes IS NOT NULL OR a.status = 'arrived' OR a.arrived_at IS NOT NULL)
                AND (i.status IN ('critical', 'urgent', 'dispatched', 'stable') OR ((a.status = 'arrived' OR a.arrived_at IS NOT NULL) AND i.status = 'resolved'))
                AND EXISTS (
                    SELECT 1 FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = i.incident_id
                )
                AND (COALESCE(i.reported_by, '') <> 'resident_sos' OR i.hospital_sent_at IS NOT NULL)
                AND NOT EXISTS (
                    SELECT 1 FROM incidents newer
                    WHERE newer.transport_name = a.call_sign
                    AND newer.hospital_id = a.hospital_id
                    AND (newer.status IN ('critical', 'urgent', 'dispatched', 'stable') OR ((a.status = 'arrived' OR a.arrived_at IS NOT NULL) AND newer.status = 'resolved'))
                    AND newer.reported_at > i.reported_at
                    AND EXISTS (
                        SELECT 1 FROM incident_patients newer_submitted_patient
                        WHERE newer_submitted_patient.incident_id = newer.incident_id
                    )
                    AND (COALESCE(newer.reported_by, '') <> 'resident_sos' OR newer.hospital_sent_at IS NOT NULL)
                )");
            $backfillEta->execute([':hospital_id' => $hospitalId]);
        } catch (Throwable $e) {
            error_log('[LivemappingModel] Transport sync failed: ' . $e->getMessage());
        }
    }

    public function getAmbulances(?string $barangaySlug = null, ?int $hospitalId = null): array
    {
        $where = [];
        $params = [];
        if ($hospitalId) {
            $where[] = 'a.hospital_id = :hospital_id';
            $params['hospital_id'] = $hospitalId;
        }
        if ($barangaySlug) {
            $where[] = "((b.slug = :vehicle_barangay_slug AND b.hospital_id = :vehicle_barangay_hospital_id)
                OR a.status IN ('available', 'offline'))";
            $params['vehicle_barangay_slug'] = $barangaySlug;
            $params['vehicle_barangay_hospital_id'] = $hospitalId;
        }

        $incidentFilter = $barangaySlug
            ? " AND EXISTS (
                SELECT 1 FROM barangays incident_scope
                WHERE incident_scope.slug = :incident_barangay_slug
                  AND incident_scope.hospital_id = i.hospital_id
                  AND (
                    incident_scope.id = i.barangay_id
                    OR i.location LIKE CONCAT('%', incident_scope.name, '%')
                  )
            )"
            : '';
        $submittedIncidentFilter = " AND EXISTS (
                SELECT 1 FROM incident_patients submitted_patient
                WHERE submitted_patient.incident_id = i.incident_id
            )
            AND (COALESCE(i.reported_by, '') <> 'resident_sos' OR i.hospital_sent_at IS NOT NULL)";
        $newerSubmittedIncidentFilter = " AND EXISTS (
                SELECT 1 FROM incident_patients newer_submitted_patient
                WHERE newer_submitted_patient.incident_id = newer.incident_id
            )
            AND (COALESCE(newer.reported_by, '') <> 'resident_sos' OR newer.hospital_sent_at IS NOT NULL)";
        $newerIncidentFilter = $barangaySlug
            ? " AND EXISTS (
                SELECT 1 FROM barangays newer_incident_scope
                WHERE newer_incident_scope.slug = :newer_barangay_slug
                  AND newer_incident_scope.hospital_id = newer.hospital_id
                  AND (
                    newer_incident_scope.id = newer.barangay_id
                    OR newer.location LIKE CONCAT('%', newer_incident_scope.name, '%')
                  )
            )"
            : '';
        if ($barangaySlug) {
            $params['incident_barangay_slug'] = $barangaySlug;
            $params['newer_barangay_slug'] = $barangaySlug;
        }

        return $this->fetchAll("SELECT a.id, a.call_sign,
            CASE WHEN a.arrived_at IS NOT NULL OR a.status = 'arrived' THEN 'arrived'
                WHEN a.status = 'en_route' THEN 'en_route'
                ELSE a.status END AS status,
            a.arrived_at, a.assignment_note,
            a.driver_name,
            a.created_at, a.updated_at,
            CASE WHEN a.status = 'arrived' OR a.arrived_at IS NOT NULL THEN COALESCE(a.eta_minutes, i.eta_minutes, 0)
                ELSE COALESCE(a.eta_minutes, i.eta_minutes) END AS eta_minutes,
            COALESCE(a.vehicle_type, 'Ambulance') AS vehicle_type,
            COALESCE(a.latitude, i.latitude, b.latitude, h.latitude) AS latitude,
            COALESCE(a.longitude, i.longitude, b.longitude, h.longitude) AS longitude,
            a.location_recorded_at AS recorded_at,
            a.hospital_id, home_hospital.name AS hospital_name,
            i.incident_id, i.incident_type, i.location AS incident_location,
            COALESCE(i.latitude, b.latitude) AS incident_latitude,
            COALESCE(i.longitude, b.longitude) AS incident_longitude,
            i.reported_at, i.transport_name, i.transport_type,
            i.patient_name, i.patient_gender, i.patient_age,
            h.id AS destination_id, h.name AS destination_name,
            h.latitude AS destination_latitude, h.longitude AS destination_longitude
            FROM ambulances a
                LEFT JOIN incidents i ON i.transport_name = a.call_sign
                    AND i.hospital_id = a.hospital_id
                    AND (i.status IN ('critical', 'urgent', 'dispatched', 'stable') OR ((a.status = 'arrived' OR a.arrived_at IS NOT NULL) AND i.status = 'resolved')) {$incidentFilter}
                    {$submittedIncidentFilter}
                    AND NOT EXISTS (SELECT 1 FROM incidents newer
                    WHERE newer.transport_name = a.call_sign
                    AND newer.hospital_id = a.hospital_id
                    AND (newer.status IN ('critical', 'urgent', 'dispatched', 'stable') OR ((a.status = 'arrived' OR a.arrived_at IS NOT NULL) AND newer.status = 'resolved'))
                        AND newer.reported_at > i.reported_at {$newerIncidentFilter} {$newerSubmittedIncidentFilter})
            LEFT JOIN barangays b ON b.id = i.barangay_id OR (b.hospital_id = i.hospital_id AND i.location LIKE CONCAT('%', b.name, '%'))
            LEFT JOIN hospitals referral_h ON referral_h.id = (
                SELECT CASE
                    WHEN r.status IN ('pending', 'accepted') THEN r.receiving_hospital_id
                    ELSE NULL
                END
                FROM referrals r
                WHERE r.incident_id = i.incident_id
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 1
            )
            LEFT JOIN hospitals h ON h.id = COALESCE(referral_h.id, i.hospital_id, a.hospital_id)
            LEFT JOIN hospitals home_hospital ON home_hospital.id = a.hospital_id
            " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
            ORDER BY FIELD(
                CASE WHEN a.arrived_at IS NOT NULL OR a.status = 'arrived' THEN 'arrived' ELSE a.status END,
                'en_route', 'arrived', 'available', 'offline'
            ), a.call_sign", $params);
    }

    public function getIncidents(?string $barangaySlug = null, ?int $hospitalId = null): array
    {
        if (!$this->db || !$hospitalId) {
            return [];
        }

        $where = [
            "EXISTS (
                SELECT 1
                FROM incident_patients submitted_patient
                WHERE submitted_patient.incident_id = i.incident_id
            )",
            "(COALESCE(i.reported_by, '') <> 'resident_sos' OR i.hospital_sent_at IS NOT NULL)",
            "(
                i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                OR (
                    i.status = 'resolved'
                    AND (
                            i.hospital_arrived_at IS NOT NULL
                        OR (
                            EXISTS (
                                SELECT 1
                                FROM ambulances arrived_vehicle
                                WHERE arrived_vehicle.hospital_id = i.hospital_id
                                  AND (arrived_vehicle.status = 'arrived' OR arrived_vehicle.arrived_at IS NOT NULL)
                                  AND arrived_vehicle.call_sign = i.transport_name
                            )
                            AND NOT EXISTS (
                                SELECT 1
                                FROM incidents newer
                                WHERE newer.hospital_id = i.hospital_id
                                  AND newer.transport_name = i.transport_name
                                  AND newer.reported_at > i.reported_at
                                  AND newer.status IN ('critical', 'urgent', 'dispatched', 'stable', 'resolved')
                                  AND EXISTS (
                                      SELECT 1
                                      FROM incident_patients newer_submitted_patient
                                      WHERE newer_submitted_patient.incident_id = newer.incident_id
                                  )
                            )
                        )
                    )
                )
            )",
            '(i.hospital_id = :hospital_id OR referral_hospital.id = :referral_hospital_id)',
        ];
        $params = [
            'hospital_id' => $hospitalId,
            'referral_hospital_id' => $hospitalId,
        ];

        if ($barangaySlug !== null && $barangaySlug !== '') {
            $where[] = '(
                EXISTS (
                    SELECT 1
                    FROM barangays scoped_barangay
                    WHERE scoped_barangay.slug = :incident_barangay_slug
                      AND scoped_barangay.hospital_id = i.hospital_id
                      AND (
                        scoped_barangay.id = i.barangay_id
                        OR i.location LIKE CONCAT('%', scoped_barangay.name, '%')
                      )
                )
            )';
            $params['incident_barangay_slug'] = $barangaySlug;
        }

        $statement = $this->db->prepare(
            "SELECT i.incident_id AS id, i.incident_type, i.reported_by, i.status, i.location,
                COALESCE(assigned_vehicle.eta_minutes, i.eta_minutes) AS eta_minutes,
                COALESCE(
                    incident_barangay.name,
                    (
                        SELECT matching_barangay.name
                        FROM barangays matching_barangay
                        WHERE matching_barangay.hospital_id = i.hospital_id
                          AND i.location LIKE CONCAT('%', matching_barangay.name, '%')
                        ORDER BY CHAR_LENGTH(matching_barangay.name) DESC
                        LIMIT 1
                    )
                ) AS barangay_name,
                i.patient_name, i.patient_gender, i.patient_age, i.transport_name, i.transport_type,
                CASE WHEN i.hospital_arrived_at IS NOT NULL
                        OR assigned_vehicle.arrived_at IS NOT NULL
                        OR assigned_vehicle.status = 'arrived'
                    THEN 'arrived'
                    WHEN i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                        AND (
                            assigned_vehicle.status = 'en_route'
                            OR i.hospital_sent_at IS NOT NULL
                            OR i.reported_by = 'on_scene'
                            OR responder_position.user_id IS NOT NULL
                        )
                    THEN 'en_route'
                    ELSE NULL
                END AS transport_status,
                (i.hospital_sent_at IS NOT NULL) AS sent_to_hospital,
                assigned_vehicle.arrived_at AS transport_arrived_at,
                COALESCE(i.latitude, incident_barangay.latitude) AS latitude,
                COALESCE(i.longitude, incident_barangay.longitude) AS longitude,
                COALESCE(
                    responder_position.latitude,
                    CASE
                        WHEN assigned_vehicle.location_recorded_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                        THEN assigned_vehicle.latitude
                    END
                ) AS route_start_latitude,
                COALESCE(
                    responder_position.longitude,
                    CASE
                        WHEN assigned_vehicle.location_recorded_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                        THEN assigned_vehicle.longitude
                    END
                ) AS route_start_longitude,
                i.reported_at, i.hospital_id,
                destination.id AS destination_id,
                destination.name AS destination_name,
                destination.latitude AS destination_latitude,
                destination.longitude AS destination_longitude,
                CASE WHEN referral_hospital.id IS NULL THEN 0 ELSE 1 END AS is_referred
             FROM incidents i
             LEFT JOIN barangays incident_barangay ON incident_barangay.id = i.barangay_id
             LEFT JOIN responder_presence responder_position
                ON responder_position.user_id = i.assigned_responder_user_id
                AND responder_position.last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
             LEFT JOIN ambulances assigned_vehicle
                ON (
                    assigned_vehicle.id = i.assigned_ambulance_id
                    OR (
                        assigned_vehicle.call_sign = i.transport_name
                        AND assigned_vehicle.hospital_id = i.hospital_id
                    )
                )
             LEFT JOIN referrals active_referral ON active_referral.id = (
                SELECT r.id
                FROM referrals r
                WHERE r.incident_id = i.incident_id
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 1
             ) AND active_referral.status IN ('pending', 'accepted')
             LEFT JOIN hospitals referral_hospital
                ON referral_hospital.id = active_referral.receiving_hospital_id
             LEFT JOIN hospitals destination
                ON destination.id = COALESCE(referral_hospital.id, i.hospital_id)
             WHERE " . implode(' AND ', $where) . '
             ORDER BY i.reported_at DESC, i.incident_id DESC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function getIncidentRoadRoute(int $incidentId, int $hospitalId): ?array
    {
        if (!$this->db || $incidentId <= 0 || $hospitalId <= 0) {
            return null;
        }

        if (!class_exists('RoadRoutingModel', false)) {
            $routerPath = dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR . 'Reddiph_mobile_api'
                . DIRECTORY_SEPARATOR . 'app'
                . DIRECTORY_SEPARATOR . 'models'
                . DIRECTORY_SEPARATOR . 'RoadRoutingModel.php';
            if (!is_file($routerPath)) {
                throw new RuntimeException('The road routing model is not installed.');
            }
            require_once $routerPath;
        }

        $statement = $this->db->prepare(
            "SELECT
                COALESCE(
                    responder_position.latitude,
                    CASE
                        WHEN assigned_vehicle.location_recorded_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                        THEN assigned_vehicle.latitude
                    END
                ) AS origin_latitude,
                COALESCE(
                    responder_position.longitude,
                    CASE
                        WHEN assigned_vehicle.location_recorded_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                        THEN assigned_vehicle.longitude
                    END
                ) AS origin_longitude,
                destination.latitude AS destination_latitude,
                destination.longitude AS destination_longitude
             FROM incidents i
             LEFT JOIN barangays incident_barangay ON incident_barangay.id = i.barangay_id
             LEFT JOIN responder_presence responder_position
                ON responder_position.user_id = i.assigned_responder_user_id
                AND responder_position.last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
             LEFT JOIN ambulances assigned_vehicle
                ON (
                    assigned_vehicle.id = i.assigned_ambulance_id
                    OR (
                        assigned_vehicle.call_sign = i.transport_name
                        AND assigned_vehicle.hospital_id = i.hospital_id
                    )
                )
             LEFT JOIN referrals active_referral ON active_referral.id = (
                SELECT r.id
                FROM referrals r
                WHERE r.incident_id = i.incident_id
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 1
             ) AND active_referral.status IN ('pending', 'accepted')
             LEFT JOIN hospitals referral_hospital
                ON referral_hospital.id = active_referral.receiving_hospital_id
             LEFT JOIN hospitals destination
                ON destination.id = COALESCE(referral_hospital.id, i.hospital_id)
             WHERE i.incident_id = :incident_id
               AND i.status IN ('critical', 'urgent', 'dispatched', 'stable')
               AND (i.hospital_id = :hospital_id OR referral_hospital.id = :referral_hospital_id)
               AND (COALESCE(i.reported_by, '') <> 'resident_sos' OR i.hospital_sent_at IS NOT NULL)
               AND EXISTS (
                    SELECT 1
                    FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = i.incident_id
               )
             LIMIT 1"
        );
        $statement->execute([
            'incident_id' => $incidentId,
            'hospital_id' => $hospitalId,
            'referral_hospital_id' => $hospitalId,
        ]);
        $row = $statement->fetch();
        if ($row === false
            || $row['origin_latitude'] === null
            || $row['origin_longitude'] === null
            || $row['destination_latitude'] === null
            || $row['destination_longitude'] === null) {
            return null;
        }

        $originLatitude = (float) $row['origin_latitude'];
        $originLongitude = (float) $row['origin_longitude'];
        $destinationLatitude = (float) $row['destination_latitude'];
        $destinationLongitude = (float) $row['destination_longitude'];
        $route = RoadRoutingModel::routeWithConnection(
            $this->db,
            $originLatitude,
            $originLongitude,
            $destinationLatitude,
            $destinationLongitude,
            false
        );

        return [
            'origin' => ['latitude' => $originLatitude, 'longitude' => $originLongitude],
            'destination' => ['latitude' => $destinationLatitude, 'longitude' => $destinationLongitude],
            'geometry' => $route['geometry'],
            'distance_m' => $route['distance_m'],
            'eta_minutes' => $route['eta_minutes'],
        ];
    }

    public function getHospitals(): array
    {
        return $this->fetchAll(
            'SELECT id, name, latitude, longitude
             FROM hospitals
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY name, id'
        );
    }

    private function getAllFleetIncidents(): array
    {
        return $this->fetchAll(
            "SELECT i.incident_id AS id, i.incident_type, i.status, i.location,
                i.reported_by, i.reported_at, i.created_at, i.eta_minutes, i.transport_name,
                i.transport_type, i.hospital_id,
                (i.hospital_sent_at IS NOT NULL) AS sent_to_hospital,
                owner_hospital.name AS owner_hospital_name,
                COALESCE(incident_barangay.name, (
                    SELECT matching_barangay.name
                    FROM barangays matching_barangay
                    WHERE matching_barangay.hospital_id = i.hospital_id
                      AND i.location LIKE CONCAT('%', matching_barangay.name, '%')
                    ORDER BY CHAR_LENGTH(matching_barangay.name) DESC
                    LIMIT 1
                )) AS barangay_name,
                destination_hospital.name AS destination_name,
                CASE
                    WHEN i.hospital_arrived_at IS NOT NULL
                        OR assigned_vehicle.arrived_at IS NOT NULL
                        OR assigned_vehicle.status = 'arrived'
                    THEN 'arrived'
                    WHEN i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                        AND (
                            assigned_vehicle.status = 'en_route'
                            OR i.hospital_sent_at IS NOT NULL
                            OR i.reported_by = 'on_scene'
                        )
                    THEN 'en_route'
                    ELSE i.status
                END AS movement_status
             FROM incidents i
             LEFT JOIN hospitals owner_hospital ON owner_hospital.id = i.hospital_id
             LEFT JOIN barangays incident_barangay ON incident_barangay.id = i.barangay_id
             LEFT JOIN ambulances assigned_vehicle
                ON assigned_vehicle.call_sign = i.transport_name
                AND assigned_vehicle.hospital_id = i.hospital_id
             LEFT JOIN referrals active_referral ON active_referral.id = (
                SELECT r.id
                FROM referrals r
                WHERE r.incident_id = i.incident_id
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 1
             ) AND active_referral.status IN ('pending', 'accepted')
             LEFT JOIN hospitals destination_hospital
                ON destination_hospital.id = COALESCE(
                    active_referral.receiving_hospital_id,
                    i.hospital_id
                )
             WHERE EXISTS (
                SELECT 1
                FROM incident_patients submitted_patient
                WHERE submitted_patient.incident_id = i.incident_id
             )
             AND (COALESCE(i.reported_by, '') <> 'resident_sos' OR i.hospital_sent_at IS NOT NULL)
             ORDER BY i.reported_at DESC, i.incident_id DESC"
        );
    }

    private function getAllFleetVehicles(): array
    {
        return $this->fetchAll(
            "SELECT a.id, a.call_sign, a.hospital_id, home_hospital.name AS hospital_name,
                a.status, a.arrived_at, a.assignment_note, a.driver_name,
                a.created_at, a.updated_at, a.location_recorded_at,
                a.eta_minutes, COALESCE(a.vehicle_type, 'Ambulance') AS vehicle_type,
                COALESCE(a.latitude, active_incident.latitude, home_hospital.latitude) AS latitude,
                COALESCE(a.longitude, active_incident.longitude, home_hospital.longitude) AS longitude,
                active_incident.incident_id
             FROM ambulances a
             INNER JOIN hospitals home_hospital ON home_hospital.id = a.hospital_id
             LEFT JOIN incidents active_incident ON active_incident.incident_id = (
                SELECT latest_incident.incident_id
                FROM incidents latest_incident
                WHERE latest_incident.transport_name = a.call_sign
                  AND latest_incident.hospital_id = a.hospital_id
                  AND (
                    latest_incident.status IN ('critical', 'urgent', 'dispatched', 'stable')
                    OR (
                        (a.status = 'arrived' OR a.arrived_at IS NOT NULL)
                        AND latest_incident.status = 'resolved'
                    )
                  )
                  AND EXISTS (
                    SELECT 1
                    FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = latest_incident.incident_id
                  )
                  AND (
                    COALESCE(latest_incident.reported_by, '') <> 'resident_sos'
                    OR latest_incident.hospital_sent_at IS NOT NULL
                  )
                ORDER BY latest_incident.reported_at DESC, latest_incident.incident_id DESC
                LIMIT 1
             )
             ORDER BY FIELD(
                CASE WHEN a.arrived_at IS NOT NULL OR a.status = 'arrived'
                    THEN 'arrived' ELSE a.status END,
                'en_route', 'arrived', 'available', 'offline'
             ), home_hospital.name, a.call_sign"
        );
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            if ($params) {
                $statement = $this->db->prepare($sql);
                $statement->execute($params);
                return $statement->fetchAll();
            }

            return $this->db->query($sql)->fetchAll();
        } catch (Throwable $e) {
            error_log('[LivemappingModel] Query failed: ' . $e->getMessage());
            return [];
        }
    }
}