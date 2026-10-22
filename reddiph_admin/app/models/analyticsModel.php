<?php

class AnalyticsModel
{
    private ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (Throwable $e) {
            error_log('[AnalyticsModel] Database connection failed: ' . $e->getMessage());
        }
    }

    public function getUserProfile(int $userId): ?array
    {
        if (!$this->db || $userId <= 0) {
            return null;
        }

        try {
            $statement = $this->db->prepare('SELECT full_name, role, profile_image FROM user_type WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $userId]);
            return $statement->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('[AnalyticsModel] User profile query failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getMetrics(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $incidents = $this->fetchOne('SELECT COUNT(*) AS total, COALESCE(AVG(eta_minutes), 0) AS response_time FROM incidents WHERE hospital_id = :hospital_id AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY)', ['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
            $referrals = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(r.status IN ('accepted', 'completed')), 0) AS accepted FROM referrals r INNER JOIN incidents i ON i.incident_id = r.incident_id WHERE i.hospital_id = :hospital_id AND r.created_at >= :from_date AND r.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)", ['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
            $bedQuery = $this->db->prepare('SELECT COALESCE((total_beds - available_beds) / NULLIF(total_beds, 0) * 100, 0) FROM hospitals WHERE id = :hospital_id');
            $bedQuery->execute(['hospital_id' => $hospitalId]);
            $beds = $bedQuery->fetchColumn();

            return [
                'totalIncidents' => (int) ($incidents['total'] ?? 0),
                'responseTime' => (float) ($incidents['response_time'] ?? 0),
                'referralRate' => (int) (($referrals['total'] ?? 0) > 0 ? round(($referrals['accepted'] / $referrals['total']) * 100) : 0),
                'bedOccupancy' => (int) round((float) $beds),
            ];
        } catch (Throwable $e) {
            error_log('[AnalyticsModel] Metrics query failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getIncidentTypes(string $fromDate, string $toDate, int $hospitalId): array
    {
        return $this->fetchAll("SELECT CASE WHEN incident_type LIKE '%Cardiac%' THEN 'Cardiac' WHEN incident_type LIKE '%Accident%' OR incident_type LIKE '%Collision%' THEN 'Trauma / Accident' WHEN incident_type LIKE '%Respiratory%' OR incident_type LIKE '%Asthma%' THEN 'Respiratory' WHEN incident_type LIKE '%Fall%' THEN 'Falls' ELSE 'Other' END AS label, COUNT(*) AS total FROM incidents WHERE hospital_id = :hospital_id AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY) GROUP BY label ORDER BY total DESC", ['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    }

    public function getTopLocations(string $fromDate, string $toDate, int $hospitalId): array
    {
        return $this->fetchAll('SELECT location AS label, COUNT(*) AS total FROM incidents WHERE hospital_id = :hospital_id AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY) GROUP BY location ORDER BY total DESC, location LIMIT 4', ['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    }

    public function getFleetMetrics(int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->prepare("SELECT COALESCE(AVG(eta_minutes), 0) AS avg_dispatch_time, COALESCE(SUM(status = 'en_route'), 0) AS active_units, COUNT(*) AS total_units FROM ambulances WHERE hospital_id = :hospital_id");
            $statement->execute(['hospital_id' => $hospitalId]);
            $row = $statement->fetch();
            return ['avgDispatchTime' => (float) ($row['avg_dispatch_time'] ?? 0), 'activeUnits' => (int) ($row['active_units'] ?? 0), 'totalUnits' => (int) ($row['total_units'] ?? 0)];
        } catch (Throwable $e) {
            error_log('[AnalyticsModel] Fleet query failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getResponseTrend(string $fromDate, string $toDate, int $hospitalId): array
    {
        return $this->fetchAll("SELECT DATE_FORMAT(reported_at, '%m/%d') AS label, ROUND(AVG(eta_minutes), 1) AS value FROM incidents WHERE hospital_id = :hospital_id AND eta_minutes IS NOT NULL AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY) GROUP BY DATE(reported_at) ORDER BY DATE(reported_at)", ['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    }

    public function getIncidentReports(string $fromDate, string $toDate, int $hospitalId): array
    {
        return $this->fetchAll('SELECT * FROM incidents
            WHERE hospital_id = :hospital_id
              AND reported_at >= :from_date
              AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
            ORDER BY reported_at DESC, incident_id DESC', [
            'hospital_id' => $hospitalId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);
    }

    public function getFleetInventory(int $hospitalId): array
    {
        return $this->fetchAll('SELECT * FROM ambulances
            WHERE hospital_id = :hospital_id
            ORDER BY vehicle_type, call_sign, id', ['hospital_id' => $hospitalId]);
    }

    public function getStaffSchedules(string $fromDate, string $toDate, int $hospitalId): array
    {
        return $this->fetchAll("SELECT u.id AS user_id, u.role, u.full_name, u.work_email, u.license_number,
                u.duty_start, u.duty_end, u.duty_timezone,
                s.id AS staff_id, s.department, s.is_available,
                sh.id AS shift_id, sh.shift_date, sh.shift_start, sh.shift_end, sh.shift_status
            FROM user_type u
            LEFT JOIN staff s ON s.user_id = u.id AND s.hospital_id = :staff_hospital_id
            LEFT JOIN staff_shifts sh ON sh.staff_id = s.id
                AND sh.shift_date >= :shift_from_date AND sh.shift_date <= :shift_to_date
            WHERE u.hospital_id = :user_hospital_id
              AND u.role IN ('doctor', 'nurse')
            ORDER BY u.role, u.full_name, sh.shift_date, sh.shift_start", [
            'staff_hospital_id' => $hospitalId,
            'shift_from_date' => $fromDate,
            'shift_to_date' => $toDate,
            'user_hospital_id' => $hospitalId,
        ]);
    }

    public function getFacilityResources(int $hospitalId): array
    {
        return $this->fetchAll('SELECT metric_key, current_value, max_value, status_label, created_at, updated_at
            FROM facility_status
            WHERE hospital_id = :hospital_id
            ORDER BY metric_key', ['hospital_id' => $hospitalId]);
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->prepare($sql);
            $statement->execute($params);
            return $statement->fetchAll();
        } catch (Throwable $e) {
            error_log('[AnalyticsModel] Query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchOne(string $sql, array $params): array
    {
        if (!$this->db) {
            return [];
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: [];
    }
}