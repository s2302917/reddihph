<?php

class HospitalnetworkModel
{
    private ?PDO $db = null;

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
        return $this->fetchAll('SELECT id, name, address, specialties, created_at FROM hospitals ORDER BY name');
    }

    public function getRecentHospitals(): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->query("SELECT id, name, address, created_at
                FROM hospitals
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
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
                    i.reported_at,
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
                  AND i.status IN ('critical', 'urgent', 'dispatched', 'stable')
                ORDER BY i.reported_at DESC, i.incident_id DESC");
            $statement->execute(['hospital_id' => $hospitalId]);
            return $statement->fetchAll();
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Referral candidates query failed: ' . $e->getMessage());
            return [];
        }
    }

    public function createReferral(int $incidentId, int $hospitalId, string $reason, ?int $userId): ?int
    {
        if (!$this->db) {
            return null;
        }

        try {
            $statement = $this->db->prepare('INSERT INTO referrals (incident_id, receiving_hospital_id, referral_reason, created_by_user_id) VALUES (:incident_id, :hospital_id, :reason, :user_id)');
            if (!$statement->execute(['incident_id' => $incidentId, 'hospital_id' => $hospitalId, 'reason' => $reason, 'user_id' => $userId])) {
                return null;
            }

            return (int) $this->db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Referral insert failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getReferralStatus(int $referralId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $statement = $this->db->prepare('SELECT r.id, r.incident_id, r.status, r.referral_reason, r.created_at,
                    i.incident_type, i.patient_name, i.patient_gender, i.patient_age, i.location,
                    i.hospital_name AS sending_hospital, h.name AS receiving_hospital
                FROM referrals r
                INNER JOIN incidents i ON i.incident_id = r.incident_id
                INNER JOIN hospitals h ON h.id = r.receiving_hospital_id
                WHERE r.id = :id LIMIT 1');
            $statement->execute(['id' => $referralId]);
            return $statement->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('[HospitalnetworkModel] Referral status query failed: ' . $e->getMessage());
            return null;
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
}