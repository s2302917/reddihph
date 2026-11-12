<?php

require_once __DIR__ . '/AuditLogModel.php';
require_once __DIR__ . '/../services/IncidentTriageCategory.php';

class HospitalnetworkModel
{
    private ?PDO $db = null;
    private static array $columnCache = [];

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Database connection failed: ' . $e->getMessage());
        }
    }

    public function getHospitals(): array
    {
        return $this->fetchAll(
            'SELECT id, name, address, specialties, created_at FROM hospitals'
            . $this->activeHospitalFilter()
            . ' ORDER BY name'
        );
    }

    public function getRecentHospitals(): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->query("SELECT id, name, address, created_at
                FROM hospitals
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                . $this->activeHospitalFilter(' AND ')
                . "
                ORDER BY created_at DESC, id DESC
                LIMIT 10");
            return $statement->fetchAll();
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Recent hospitals query failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getUserProfile(int $userId): ?array
    {
        if (!$this->db || $userId <= 0) {
            return null;
        }

        try {
            $statement = $this->db->prepare('SELECT full_name, role FROM user_type WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $userId]);
            return $statement->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] User profile query failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getReferralCandidates(?int $hospitalId): array
    {
        if (!$this->db || !$hospitalId) {
            return [];
        }

        try {
            $statement = $this->db->prepare("SELECT i.incident_id, i.incident_type, i.patient_name,
                    i.patient_gender, i.patient_age, i.location, i.hospital_name, i.responder_name,
                    i.reported_at, i.status, i.triage_category,
                    (
                        SELECT resident.full_name
                        FROM user_type resident
                        WHERE resident.hospital_id = i.hospital_id
                          AND resident.role = 'resident'
                          AND resident.full_name = i.responder_name
                        ORDER BY resident.id
                        LIMIT 1
                    ) AS resident_name
                FROM incidents i
                WHERE i.hospital_id = :hospital_id
                  AND i.status IN ('open', 'dispatched', 'referred')
                ORDER BY i.reported_at DESC, i.incident_id DESC");
            $statement->execute(['hospital_id' => $hospitalId]);
            return array_map(static function (array $incident): array {
                $incident['triage_category_label'] = IncidentTriageCategory::label(
                    $incident['triage_category'] !== null
                        ? (string) $incident['triage_category']
                        : null
                );
                return $incident;
            }, $statement->fetchAll());
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Referral candidates query failed: ' . $e->getMessage());
            return [];
        }
    }

    public function createReferral(
        int $incidentId,
        int $sourceHospitalId,
        int $receivingHospitalId,
        string $reason,
        int $userId
    ): ?int
    {
        $reason = trim($reason);
        if (!$this->db) {
            return null;
        }
        if (
            $incidentId <= 0
            || $sourceHospitalId <= 0
            || $receivingHospitalId <= 0
            || $sourceHospitalId === $receivingHospitalId
            || $userId <= 0
            || $reason === ''
            || strlen($reason) > 255
        ) {
            throw new DomainException('Enter a valid incident, receiving hospital, and referral reason.');
        }

        $this->db->beginTransaction();

        try {
            $administrator = $this->db->prepare(
                "SELECT id FROM user_type
                 WHERE id = :user_id AND hospital_id = :hospital_id
                   AND role = 'hospital_admin'
                 LIMIT 1"
            );
            $administrator->execute([
                'user_id' => $userId,
                'hospital_id' => $sourceHospitalId,
            ]);
            if ($administrator->fetchColumn() === false) {
                throw new DomainException('Your account is not authorized to refer this case.');
            }

            $destination = $this->db->prepare(
                'SELECT id FROM hospitals
                 WHERE id = :hospital_id AND is_active = 1 AND archived_at IS NULL
                 LIMIT 1'
            );
            $destination->execute(['hospital_id' => $receivingHospitalId]);
            if ($destination->fetchColumn() === false) {
                throw new DomainException('Choose an active receiving hospital.');
            }

            $incident = $this->db->prepare(
                'SELECT incident_id, hospital_id, status, assigned_ambulance_id, transport_name
                 FROM incidents
                 WHERE incident_id = :incident_id AND hospital_id = :hospital_id
                 LIMIT 1 FOR UPDATE'
            );
            $incident->execute([
                'incident_id' => $incidentId,
                'hospital_id' => $sourceHospitalId,
            ]);
            $currentIncident = $incident->fetch();
            if ($currentIncident === false) {
                throw new DomainException('The incident is not available to this hospital.');
            }
            if (!in_array($currentIncident['status'], ['open', 'dispatched', 'referred'], true)) {
                throw new DomainException('Only active incidents can be referred.');
            }

            $activeReferral = $this->db->prepare(
                "SELECT id, receiving_hospital_id
                 FROM referrals
                 WHERE incident_id = :incident_id
                   AND status IN ('pending', 'accepted')
                 ORDER BY id DESC
                 FOR UPDATE"
            );
            $activeReferral->execute(['incident_id' => $incidentId]);
            $activeReferrals = $activeReferral->fetchAll();
            if ($activeReferrals) {
                foreach ($activeReferrals as $existingReferral) {
                    if ((int) $existingReferral['receiving_hospital_id'] === $receivingHospitalId) {
                        $this->db->prepare(
                            "UPDATE incidents SET status = 'referred'
                             WHERE incident_id = :incident_id
                               AND status IN ('open', 'dispatched', 'referred')"
                        )->execute(['incident_id' => $incidentId]);
                        $this->db->commit();
                        return (int) $existingReferral['id'];
                    }
                }
                throw new DomainException(
                    'This incident already has an active referral. Resolve it before choosing another hospital.'
                );
            }

            $completedReferral = $this->db->prepare(
                "SELECT id FROM referrals
                 WHERE incident_id = :incident_id
                   AND receiving_hospital_id = :hospital_id
                   AND status = 'completed'
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $completedReferral->execute([
                'incident_id' => $incidentId,
                'hospital_id' => $receivingHospitalId,
            ]);
            $completedReferralId = $completedReferral->fetchColumn();
            if ($completedReferralId !== false) {
                $this->db->commit();
                return (int) $completedReferralId;
            }

            $statement = $this->db->prepare(
                'INSERT INTO referrals (incident_id, receiving_hospital_id, referral_reason, status, created_by_user_id)
                 VALUES (:incident_id, :hospital_id, :reason, :status, :user_id)'
            );
            if (!$statement->execute([
                'incident_id' => $incidentId,
                'hospital_id' => $receivingHospitalId,
                'reason' => $reason,
                'status' => 'pending',
                'user_id' => $userId,
            ])) {
                $this->db->rollBack();
                return null;
            }

            $this->db->prepare(
                "UPDATE incidents
                 SET status = 'referred'
                 WHERE incident_id = :incident_id
                   AND status IN ('open', 'dispatched', 'referred')"
            )->execute(['incident_id' => $incidentId]);

            $this->rerouteAssignedVehicle(
                (int) $currentIncident['assigned_ambulance_id'],
                (string) ($currentIncident['transport_name'] ?? ''),
                $sourceHospitalId,
                $receivingHospitalId
            );

            $referralId = (int) $this->db->lastInsertId();
            AuditLogModel::record($this->db, $userId, 'referral.created', 'referral', (string) $referralId, [
                'incident_id' => $incidentId,
                'source_hospital_id' => $sourceHospitalId,
                'receiving_hospital_id' => $receivingHospitalId,
                'operation' => 'hospital_referral',
            ]);
            $this->db->commit();
            return $referralId;
        } catch (DomainException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[HospitalnetworkModel] Referral insert failed: ' . $e->getMessage());
            return null;
        }
    }

    private function rerouteAssignedVehicle(?int $assignedAmbulanceId, string $transportName, int $originHospitalId, int $destinationHospitalId): void
    {
        if ($originHospitalId === $destinationHospitalId) {
            return;
        }

        $vehicleQuery = $this->db->prepare(
            'SELECT id, call_sign, hospital_id, assignment_note
             FROM ambulances
             WHERE (id = :vehicle_match_id)
                OR ( :transport_present <> \'\' AND call_sign = :transport_match AND hospital_id = :origin_hospital_id )
             ORDER BY CASE WHEN id = :vehicle_order_id THEN 0 ELSE 1 END, id DESC
             LIMIT 1 FOR UPDATE'
        );
        $vehicleQuery->execute([
            'vehicle_match_id' => $assignedAmbulanceId ?? 0,
            'vehicle_order_id' => $assignedAmbulanceId ?? 0,
            'transport_present' => $transportName,
            'transport_match' => $transportName,
            'origin_hospital_id' => $originHospitalId,
        ]);
        $vehicle = $vehicleQuery->fetch();
        if ($vehicle === false) {
            return;
        }

        $note = trim((string) ($vehicle['assignment_note'] ?? ''));
        $updatedNote = $note === ''
            ? 'Referred to receiving hospital'
            : $note . ' | Referred to receiving hospital';

        $this->db->prepare(
            'UPDATE ambulances
             SET hospital_id = :destination_hospital_id,
                 status = "en_route",
                 assignment_note = :assignment_note,
                 updated_at = NOW()
             WHERE id = :vehicle_id'
        )->execute([
            'destination_hospital_id' => $destinationHospitalId,
            'assignment_note' => $updatedNote,
            'vehicle_id' => (int) $vehicle['id'],
        ]);
    }

    public function getReferralStatus(int $referralId, int $hospitalId): ?array
    {
        if (!$this->db || $referralId <= 0 || $hospitalId <= 0) {
            return null;
        }

        try {
            $statement = $this->db->prepare('SELECT r.id, r.incident_id, r.status, r.referral_reason,
                    r.created_at, r.updated_at,
                    i.incident_type, i.status AS incident_status,
                    COALESCE(NULLIF(i.patient_name, \'\'), (
                        SELECT patient.patient_name FROM incident_patients patient
                        WHERE patient.incident_id = i.incident_id
                        ORDER BY patient.id LIMIT 1
                    )) AS patient_name,
                    i.patient_gender, i.patient_age, i.location,
                    COALESCE(source.name, i.hospital_name) AS sending_hospital,
                    destination.name AS receiving_hospital
                FROM referrals r
                INNER JOIN incidents i ON i.incident_id = r.incident_id
                LEFT JOIN hospitals source ON source.id = i.hospital_id
                INNER JOIN hospitals destination ON destination.id = r.receiving_hospital_id
                WHERE r.id = :id
                  AND (i.hospital_id = :source_hospital
                       OR r.receiving_hospital_id = :receiving_hospital)
                LIMIT 1');
            $statement->execute([
                'id' => $referralId,
                'source_hospital' => $hospitalId,
                'receiving_hospital' => $hospitalId,
            ]);
            return $statement->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Referral status query failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getReferralHistory(int $hospitalId): array
    {
        if (!$this->db || $hospitalId <= 0) {
            return [];
        }

        try {
            $statement = $this->db->prepare(
                'SELECT r.id, r.incident_id, r.status, r.referral_reason, r.created_at,
                    COALESCE(NULLIF(i.patient_name, \'\'), (
                        SELECT patient.patient_name FROM incident_patients patient
                        WHERE patient.incident_id = i.incident_id
                        ORDER BY patient.id LIMIT 1
                    )) AS patient_name,
                    i.incident_type, i.patient_age, i.patient_gender,
                    COALESCE(source.name, i.hospital_name) AS sending_hospital,
                    destination.name AS receiving_hospital
                 FROM referrals r
                 INNER JOIN incidents i ON i.incident_id = r.incident_id
                 LEFT JOIN hospitals source ON source.id = i.hospital_id
                 INNER JOIN hospitals destination ON destination.id = r.receiving_hospital_id
                 WHERE i.hospital_id = :source_hospital
                    OR r.receiving_hospital_id = :receiving_hospital
                 ORDER BY r.created_at DESC, r.id DESC
                 LIMIT 100'
            );
            $statement->execute([
                'source_hospital' => $hospitalId,
                'receiving_hospital' => $hospitalId,
            ]);
            return $statement->fetchAll();
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Referral history query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchAll(string $sql): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            return $this->db->query($sql)->fetchAll();
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function activeHospitalFilter(string $prefix = ' WHERE '): string
    {
        if (!$this->db) {
            return '';
        }

        $conditions = [];
        if ($this->hasColumn('hospitals', 'is_active')) {
            $conditions[] = 'is_active = 1';
        }
        if ($this->hasColumn('hospitals', 'archived_at')) {
            $conditions[] = 'archived_at IS NULL';
        }

        return $conditions ? $prefix . implode(' AND ', $conditions) : '';
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!$this->db) {
            return false;
        }

        $cacheKey = $table . '.' . $column;
        if (!array_key_exists($cacheKey, self::$columnCache)) {
            $statement = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
            );
            $statement->execute([$table, $column]);
            self::$columnCache[$cacheKey] = (int) $statement->fetchColumn() > 0;
        }

        return self::$columnCache[$cacheKey];
    }
}