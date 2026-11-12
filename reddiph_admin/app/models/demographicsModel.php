<?php

require_once __DIR__ . '/../services/IncidentTriageCategory.php';

class DemographicsModel
{
    private ?PDO $db = null;
    private ?string $triageColumn = 'triage_category';
    private bool $usesLegacyIncidentStatuses = false;
    private array $incidentColumns = [];
    private array $queryErrors = [];

    public function __construct()
    {
        try {
            $this->db = Database::connection();
            $this->incidentColumns = $this->db->query('SHOW COLUMNS FROM incidents')->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('triage_category', $this->incidentColumns, true)) {
                $this->triageColumn = 'triage_category';
            } elseif (in_array('triage_status', $this->incidentColumns, true)) {
                $this->triageColumn = 'triage_status';
            } else {
                $this->triageColumn = null;
                $this->usesLegacyIncidentStatuses = true;
            }
        } catch (Throwable $e) {
            $this->recordQueryFailure('Database connection or schema inspection', $e);
        }
    }

    public function getQueryErrors(): array
    {
        return array_values(array_unique($this->queryErrors));
    }

    private function recordQueryFailure(string $context, Throwable $error): void
    {
        error_log('[DemographicsModel] ' . $context . ' failed: ' . $error->getMessage());
        $this->queryErrors[] = $context;
    }

    public function getSummary(string $fromDate, string $toDate, int $hospitalId): array
    {
        $data = $this->fetchSummaryFromDb($fromDate, $toDate, $hospitalId);
        if ($data !== null) {
            return $data;
        }

        return [
            'activeAlerts' => 0,
            'resolvedToday' => 0,
            'patientsLogged' => 0,
            'highRiskCases' => 0,
        ];
    }

    public function getLogs(
        string $fromDate,
        string $toDate,
        int $hospitalId,
        string $searchQuery = ''
    ): array
    {
        if (!$this->db) {
            throw new RuntimeException('Emergency logs cannot be loaded because the database is unavailable.');
        }

        return $this->fetchLogsFromDb($fromDate, $toDate, $hospitalId, $searchQuery);
    }

    public function getAgeGroups(string $fromDate, string $toDate, int $hospitalId): array
    {
        $data = $this->fetchAgeGroupsFromDb($fromDate, $toDate, $hospitalId);
        if ($data !== []) {
            return $data;
        }

        return [
            ['label' => '0-17', 'value' => 0],
            ['label' => '18-35', 'value' => 0],
            ['label' => '36-59', 'value' => 0],
            ['label' => '60+', 'value' => 0],
        ];
    }

    public function getGenderBreakdown(string $fromDate, string $toDate, int $hospitalId): array
    {
        $data = $this->fetchGenderBreakdownFromDb($fromDate, $toDate, $hospitalId);
        if ($data !== []) {
            return $data;
        }

        return [
            ['label' => 'Female 0%', 'value' => 0, 'color' => '#b94b4d'],
            ['label' => 'Male 0%', 'value' => 0, 'color' => '#d2b0a8'],
            ['label' => 'Other 0%', 'value' => 0, 'color' => '#9bb7bb'],
        ];
    }

    public function getTopLocations(string $fromDate, string $toDate, int $hospitalId): array
    {
        $data = $this->fetchTopLocationsFromDb($fromDate, $toDate, $hospitalId);
        if ($data !== []) {
            return $data;
        }

        return [];
    }

    public function getAlerts(string $fromDate, string $toDate, int $hospitalId): array
    {
        $data = $this->fetchAlertsFromDb($fromDate, $toDate, $hospitalId);
        if ($data !== []) {
            return $data;
        }

        return [
            [
                'type' => 'critical',
                'title' => 'No active critical case',
                'message' => 'The incident queue is currently clear.',
            ],
            [
                'type' => 'resolved',
                'title' => 'No recent handoff',
                'message' => 'No resolved case has been closed yet.',
            ],
        ];
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
            $this->recordQueryFailure('User profile query', $e);
            return null;
        }
    }

    private function fetchSummaryFromDb(string $fromDate, string $toDate, int $hospitalId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $activeStatuses = $this->usesLegacyIncidentStatuses
                ? "'critical','dispatched','urgent','stable'"
                : "'open','dispatched','referred'";
            $highRiskCondition = $this->triageColumn !== null
                ? "i.{$this->triageColumn} IN ('emergent','urgent') OR i.patient_age >= 60 OR i.eta_minutes <= 10"
                : "i.status IN ('critical','urgent') OR i.patient_age >= 60 OR i.eta_minutes <= 10";
            $activeHospitalFilter = $this->incidentHospitalCondition('i', 'active_hospital');
            $resolvedHospitalFilter = $this->incidentHospitalCondition('i', 'resolved_hospital');
            $riskHospitalFilter = $this->incidentHospitalCondition('i', 'risk_hospital');
            $patientRows = $this->patientDemographicRowsSql();
            $statement = $this->db->prepare("SELECT
                (SELECT COUNT(*) FROM incidents i WHERE {$activeHospitalFilter} AND i.reported_at >= :active_from AND i.reported_at < DATE_ADD(:active_to, INTERVAL 1 DAY) AND i.status IN ({$activeStatuses})) AS active_alerts,
                (SELECT COUNT(*) FROM incidents i WHERE {$resolvedHospitalFilter} AND i.resolved_at >= :resolved_from AND i.resolved_at < DATE_ADD(:resolved_to, INTERVAL 1 DAY) AND i.status = 'resolved') AS resolved_in_range,
                (SELECT COUNT(*) FROM ({$patientRows}) patient_records) AS total_patients,
                (SELECT COUNT(*) FROM incidents i WHERE {$riskHospitalFilter} AND i.reported_at >= :risk_from AND i.reported_at < DATE_ADD(:risk_to, INTERVAL 1 DAY) AND i.status IN ({$activeStatuses}) AND ({$highRiskCondition})) AS high_risk_cases");
            $statement->execute([
                'active_hospital' => $hospitalId,
                'active_from' => $fromDate,
                'active_to' => $toDate,
                'resolved_hospital' => $hospitalId,
                'resolved_from' => $fromDate,
                'resolved_to' => $toDate,
                'risk_hospital' => $hospitalId,
                'risk_from' => $fromDate,
                'risk_to' => $toDate,
            ] + $this->patientDemographicParams($fromDate, $toDate, $hospitalId));
            $row = $statement->fetch();

            if (!$row) {
                return null;
            }

            return [
                'activeAlerts' => (int) ($row['active_alerts'] ?? 0),
                'resolvedToday' => (int) ($row['resolved_in_range'] ?? 0),
                'patientsLogged' => (int) ($row['total_patients'] ?? 0),
                'highRiskCases' => (int) ($row['high_risk_cases'] ?? 0),
            ];
        } catch (Throwable $e) {
            $this->recordQueryFailure('Summary query', $e);
            return null;
        }
    }

    private function fetchLogsFromDb(
        string $fromDate,
        string $toDate,
        int $hospitalId,
        string $searchQuery
    ): array
    {
        if (!$this->db) {
            throw new RuntimeException('Emergency logs cannot be loaded because the database is unavailable.');
        }

        if (!in_array('hospital_id', $this->incidentColumns, true)) {
            return $this->fetchLegacyLogsFromDb($fromDate, $toDate, $hospitalId, $searchQuery);
        }

        try {
            $triageSelect = $this->triageColumn !== null
                ? 'i.' . $this->triageColumn
                : "CASE i.status
                    WHEN 'critical' THEN 'emergent'
                    WHEN 'urgent' THEN 'urgent'
                    WHEN 'stable' THEN 'non_urgent'
                    ELSE NULL
                   END";
            $searchCondition = '';
            $params = [
                'hospital_id' => $hospitalId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ];
            if ($searchQuery !== '') {
                $searchCondition = "AND (
                    CAST(i.incident_id AS CHAR) LIKE :search_incident_id
                    OR COALESCE(i.patient_name, '') LIKE :search_patient_name
                    OR i.incident_type LIKE :search_incident_type
                    OR i.hospital_name LIKE :search_hospital_name
                    OR i.location LIKE :search_location
                    OR EXISTS (
                        SELECT 1
                        FROM incident_patients patient_search
                        WHERE patient_search.incident_id = i.incident_id
                          AND patient_search.patient_name LIKE :search_related_patient
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM referrals referral_search
                        INNER JOIN hospitals receiving_search
                            ON receiving_search.id = referral_search.receiving_hospital_id
                        WHERE referral_search.incident_id = i.incident_id
                          AND (referral_search.referral_reason LIKE :search_referral_reason
                               OR receiving_search.name LIKE :search_receiving_hospital)
                    )
                )";
                $searchPattern = '%' . $searchQuery . '%';
                $params += [
                    'search_incident_id' => $searchPattern,
                    'search_patient_name' => $searchPattern,
                    'search_incident_type' => $searchPattern,
                    'search_hospital_name' => $searchPattern,
                    'search_location' => $searchPattern,
                    'search_related_patient' => $searchPattern,
                    'search_referral_reason' => $searchPattern,
                    'search_receiving_hospital' => $searchPattern,
                ];
            }
            $statement = $this->db->prepare("SELECT i.incident_id, i.incident_type, i.status, {$triageSelect} AS triage_category, i.location,
                i.patient_gender, i.patient_age, i.responder_name, i.reported_at, i.updated_at, i.hospital_name,
                COALESCE(NULLIF((
                    SELECT GROUP_CONCAT(NULLIF(TRIM(patient.patient_name), '') ORDER BY patient.id SEPARATOR ', ')
                    FROM incident_patients patient
                    WHERE patient.incident_id = i.incident_id
                ), ''), NULLIF(TRIM(i.patient_name), ''), 'N/A') AS patient_name,
                i.transport_name, i.transport_type, i.driver_name,
                EXISTS (
                    SELECT 1
                    FROM user_type responder
                    WHERE responder.hospital_id = i.hospital_id
                        AND responder.full_name = i.responder_name
                        AND responder.role IN ('responder', 'volunteer')
                ) AS has_responder_user,
                (
                    SELECT assigned_vehicle.status
                    FROM ambulances assigned_vehicle
                    WHERE assigned_vehicle.hospital_id = i.hospital_id
                        AND (
                            assigned_vehicle.id = i.assigned_ambulance_id
                            OR assigned_vehicle.call_sign = i.transport_name
                            OR assigned_vehicle.call_sign = i.responder_name
                        )
                    ORDER BY (assigned_vehicle.id = i.assigned_ambulance_id) DESC, assigned_vehicle.id DESC
                    LIMIT 1
                ) AS transport_status,
                (
                    SELECT assigned_vehicle.arrived_at
                    FROM ambulances assigned_vehicle
                    WHERE assigned_vehicle.hospital_id = i.hospital_id
                        AND (
                            assigned_vehicle.id = i.assigned_ambulance_id
                            OR assigned_vehicle.call_sign = i.transport_name
                            OR assigned_vehicle.call_sign = i.responder_name
                        )
                    ORDER BY (assigned_vehicle.id = i.assigned_ambulance_id) DESC, assigned_vehicle.id DESC
                    LIMIT 1
                ) AS transport_arrived_at
                FROM incidents i
                WHERE i.hospital_id = :hospital_id
                    AND (
                        (
                            i.reported_at >= :from_date
                            AND i.reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
                        )
                        OR (
                            i.status <> 'resolved'
                            AND NULLIF(TRIM(i.transport_name), '') IS NULL
                            AND NULLIF(TRIM(i.transport_type), '') IS NULL
                            AND NULLIF(TRIM(i.driver_name), '') IS NULL
                            AND NULLIF(TRIM(i.responder_name), '') IS NULL
                            AND NOT EXISTS (
                                SELECT 1
                                FROM user_type pending_responder
                                WHERE pending_responder.hospital_id = i.hospital_id
                                    AND pending_responder.full_name = i.responder_name
                                    AND pending_responder.role IN ('responder', 'volunteer')
                            )
                        )
                    )
                    {$searchCondition}
                ORDER BY i.reported_at DESC, i.incident_id DESC");
            $statement->execute($params);
            $rows = $statement->fetchAll();
            if (!$rows) {
                return [];
            }

            return $this->formatLogRows($rows);
        } catch (Throwable $e) {
            $this->recordQueryFailure('Emergency logs query', $e);
            throw new RuntimeException('Emergency logs could not be retrieved from the database.', 0, $e);
        }
    }

    private function fetchLegacyLogsFromDb(
        string $fromDate,
        string $toDate,
        int $hospitalId,
        string $searchQuery
    ): array {
        try {
            $searchCondition = '';
            $params = [
                'hospital_id' => $hospitalId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ];
            if ($searchQuery !== '') {
                $searchCondition = "AND (
                    CAST(i.incident_id AS CHAR) LIKE :search_incident_id
                    OR i.incident_type LIKE :search_incident_type
                    OR i.hospital_name LIKE :search_hospital_name
                    OR i.location LIKE :search_location
                    OR EXISTS (
                        SELECT 1
                        FROM referrals referral_search
                        INNER JOIN hospitals receiving_search
                            ON receiving_search.id = referral_search.receiving_hospital_id
                        WHERE referral_search.incident_id = i.incident_id
                            AND (
                                referral_search.referral_reason LIKE :search_referral_reason
                                OR receiving_search.name LIKE :search_receiving_hospital
                            )
                    )
                )";
                $searchPattern = '%' . $searchQuery . '%';
                $params += [
                    'search_incident_id' => $searchPattern,
                    'search_incident_type' => $searchPattern,
                    'search_hospital_name' => $searchPattern,
                    'search_location' => $searchPattern,
                    'search_referral_reason' => $searchPattern,
                    'search_receiving_hospital' => $searchPattern,
                ];
            }
            $statement = $this->db->prepare("SELECT i.incident_id, i.incident_type, i.status,
                CASE i.status
                    WHEN 'critical' THEN 'emergent'
                    WHEN 'urgent' THEN 'urgent'
                    WHEN 'stable' THEN 'non_urgent'
                    ELSE NULL
                END AS triage_category,
                i.location, i.patient_gender, i.patient_age, i.responder_name,
                i.reported_at, i.updated_at, i.hospital_name,
                'N/A' AS patient_name,
                NULL AS transport_name, NULL AS transport_type, NULL AS driver_name,
                EXISTS (
                    SELECT 1
                    FROM user_type responder
                    WHERE responder.hospital_id = (
                        SELECT responder_hospital.id
                        FROM hospitals responder_hospital
                        WHERE responder_hospital.name = i.hospital_name
                        LIMIT 1
                    )
                    AND responder.full_name = i.responder_name
                    AND responder.role IN ('responder', 'volunteer')
                ) AS has_responder_user,
                NULL AS transport_status, NULL AS transport_arrived_at
                FROM incidents i
                WHERE i.hospital_name = (
                    SELECT hospital.name
                    FROM hospitals hospital
                    WHERE hospital.id = :hospital_id
                    LIMIT 1
                )
                AND i.reported_at >= :from_date
                AND i.reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
                {$searchCondition}
                ORDER BY i.reported_at DESC, i.incident_id DESC");
            $statement->execute($params);

            return $this->formatLogRows($statement->fetchAll());
        } catch (Throwable $e) {
            $this->recordQueryFailure('Emergency logs query', $e);
            throw new RuntimeException('Emergency logs could not be retrieved from the database.', 0, $e);
        }
    }

    private function formatLogRows(array $rows): array
    {
        $logs = [];
        foreach ($rows as $row) {
            [$statusText, $statusClass] = $this->classifyLogStatus($row);
            $patientName = trim((string) ($row['patient_name'] ?? ''));
            $reportedAt = strtotime((string) ($row['reported_at'] ?? ''));
            $updatedAt = strtotime((string) ($row['updated_at'] ?? ''));

            $logs[] = [
                'incidentId' => (int) $row['incident_id'],
                'time' => $reportedAt !== false ? date('M j, Y g:i A', $reportedAt) : 'N/A',
                'updatedAt' => $updatedAt !== false ? date('M j, Y g:i A', $updatedAt) : 'N/A',
                'patient' => $patientName !== '' ? $patientName : 'N/A',
                'caller' => trim((string) ($row['responder_name'] ?: 'Dispatch team')),
                'location' => trim((string) ($row['location'] ?: 'Unknown location')),
                'type' => trim((string) ($row['incident_type'] ?: 'Emergency')),
                'status' => $statusText,
                'assigned' => trim((string) ($row['hospital_name'] ?: 'Duty desk')),
                'statusClass' => $statusClass,
                'triageCategory' => $row['triage_category'] !== null
                    ? (string) $row['triage_category']
                    : null,
                'triageCategoryLabel' => IncidentTriageCategory::label(
                    $row['triage_category'] !== null
                        ? (string) $row['triage_category']
                        : null
                ),
            ];
        }

        return $logs;
    }

    private function patientDemographicRowsSql(): string
    {
        $detailHospitalFilter = $this->incidentHospitalCondition('i', 'detail_hospital_id');
        if (!in_array('hospital_id', $this->incidentColumns, true)) {
            return "SELECT i.patient_age, i.patient_gender
                FROM incidents i
                WHERE {$detailHospitalFilter}
                    AND i.reported_at >= :detail_from_date
                    AND i.reported_at < DATE_ADD(:detail_to_date, INTERVAL 1 DAY)";
        }

        $fallbackHospitalFilter = $this->incidentHospitalCondition('i', 'fallback_hospital_id');
        return "SELECT patient.patient_age, patient.patient_gender
                FROM incidents i
                INNER JOIN incident_patients patient ON patient.incident_id = i.incident_id
                WHERE {$detailHospitalFilter}
                    AND i.reported_at >= :detail_from_date
                    AND i.reported_at < DATE_ADD(:detail_to_date, INTERVAL 1 DAY)
                UNION ALL
                SELECT i.patient_age, i.patient_gender
                FROM incidents i
                WHERE {$fallbackHospitalFilter}
                    AND i.reported_at >= :fallback_from_date
                    AND i.reported_at < DATE_ADD(:fallback_to_date, INTERVAL 1 DAY)
                    AND NOT EXISTS (
                        SELECT 1
                        FROM incident_patients patient
                        WHERE patient.incident_id = i.incident_id
                    )";
    }

    private function patientDemographicParams(string $fromDate, string $toDate, int $hospitalId): array
    {
        $params = [
            'detail_hospital_id' => $hospitalId,
            'detail_from_date' => $fromDate,
            'detail_to_date' => $toDate,
        ];
        if (!in_array('hospital_id', $this->incidentColumns, true)) {
            return $params;
        }

        return $params + [
            'fallback_hospital_id' => $hospitalId,
            'fallback_from_date' => $fromDate,
            'fallback_to_date' => $toDate,
        ];
    }

    private function incidentHospitalCondition(string $alias, string $parameter): string
    {
        if (in_array('hospital_id', $this->incidentColumns, true)) {
            return "{$alias}.hospital_id = :{$parameter}";
        }

        return "{$alias}.hospital_name = (
            SELECT hospital.name
            FROM hospitals hospital
            WHERE hospital.id = :{$parameter}
            LIMIT 1
        )";
    }

    private function classifyLogStatus(array $row): array
    {
        $incidentStatus = strtolower((string) ($row['status'] ?? ''));
        $transportStatus = strtolower((string) ($row['transport_status'] ?? ''));
        $transportArrived = $transportStatus === 'arrived'
            || ($transportStatus !== 'en_route' && !empty($row['transport_arrived_at']));
        $hasResponderDetails = trim((string) ($row['responder_name'] ?? '')) !== ''
            || !empty($row['has_responder_user']);
        $hasTransportDetails = trim((string) ($row['transport_name'] ?? '')) !== ''
            || trim((string) ($row['transport_type'] ?? '')) !== ''
            || trim((string) ($row['driver_name'] ?? '')) !== ''
            || $transportStatus === 'en_route'
            || $transportStatus === 'arrived'
            || !empty($row['transport_arrived_at']);

        if ($incidentStatus === 'resolved') {
            return ['Resolved', 'resolved'];
        }

        if ($transportArrived) {
            return ['Arrived', 'arrived'];
        }

        if (!$hasTransportDetails && !$hasResponderDetails) {
            return ['Pending', 'pending'];
        }

        return ['Assigned', 'assigned'];
    }

    private function fetchAgeGroupsFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $patientRows = $this->patientDemographicRowsSql();
            $statement = $this->db->prepare("SELECT
                SUM(CASE WHEN patient_age BETWEEN 0 AND 17 THEN 1 ELSE 0 END) AS range_0_17,
                SUM(CASE WHEN patient_age BETWEEN 18 AND 35 THEN 1 ELSE 0 END) AS range_18_35,
                SUM(CASE WHEN patient_age BETWEEN 36 AND 59 THEN 1 ELSE 0 END) AS range_36_59,
                SUM(CASE WHEN patient_age >= 60 THEN 1 ELSE 0 END) AS range_60_plus
                FROM ({$patientRows}) patient_records
                WHERE patient_age IS NOT NULL");
            $statement->execute($this->patientDemographicParams($fromDate, $toDate, $hospitalId));
            $rows = $statement->fetch();
            if (!$rows) {
                return [];
            }

            $values = [
                ['label' => '0-17', 'value' => (int) ($rows['range_0_17'] ?? 0)],
                ['label' => '18-35', 'value' => (int) ($rows['range_18_35'] ?? 0)],
                ['label' => '36-59', 'value' => (int) ($rows['range_36_59'] ?? 0)],
                ['label' => '60+', 'value' => (int) ($rows['range_60_plus'] ?? 0)],
            ];
            $total = array_sum(array_column($values, 'value'));
            foreach ($values as &$group) {
                $group['percentage'] = $total > 0 ? (int) round(($group['value'] / $total) * 100) : 0;
            }
            unset($group);
            return $values;
        } catch (Throwable $e) {
            $this->recordQueryFailure('Age groups query', $e);
            return [];
        }
    }

    private function fetchGenderBreakdownFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $patientRows = $this->patientDemographicRowsSql();
            $statement = $this->db->prepare("SELECT patient_gender, COUNT(*) AS total
                FROM ({$patientRows}) patient_records
                WHERE patient_gender IS NOT NULL AND patient_gender <> 'unknown'
                GROUP BY patient_gender
                ORDER BY patient_gender");
            $statement->execute($this->patientDemographicParams($fromDate, $toDate, $hospitalId));
            $rows = $statement->fetchAll();
            if (!$rows) {
                return [];
            }

            $countTotal = 0;
            foreach ($rows as $row) {
                $countTotal += (int) ($row['total'] ?? 0);
            }

            if ($countTotal <= 0) {
                return [];
            }

            $data = [];
            $palette = ['#b94b4d', '#d2b0a8', '#9bb7bb'];
            $index = 0;
            foreach ($rows as $row) {
                $label = trim((string) ($row['patient_gender'] ?? 'Unknown'));
                $value = (int) ($row['total'] ?? 0);
                $percent = round(($value / $countTotal) * 100);
                $data[] = [
                    'label' => ucfirst($label) . ' ' . $percent . '%',
                    'value' => $percent,
                    'color' => $palette[$index % count($palette)],
                ];
                $index++;
            }

            return $data;
        } catch (Throwable $e) {
            $this->recordQueryFailure('Gender query', $e);
            return [];
        }
    }

    private function fetchTopLocationsFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $hospitalFilter = $this->incidentHospitalCondition('i', 'hospital_id');
            $statement = $this->db->prepare("SELECT i.location, COUNT(*) AS total
                FROM incidents i
                WHERE {$hospitalFilter}
                    AND i.location IS NOT NULL
                    AND i.location != ''
                    AND i.reported_at >= :from_date
                    AND i.reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
                GROUP BY i.location
                ORDER BY total DESC, i.location
                LIMIT 4");
            $statement->execute(['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
            $rows = $statement->fetchAll();
            if (!$rows) {
                return [];
            }

            $maximum = max(array_column($rows, 'total'));
            return array_map(static function (array $row) use ($maximum): array {
                $value = (int) ($row['total'] ?? 0);
                return [
                    'label' => trim((string) ($row['location'] ?? 'Unknown')),
                    'value' => $value,
                    'percentage' => $maximum > 0 ? (int) round(($value / $maximum) * 100) : 0,
                ];
            }, $rows);
        } catch (Throwable $e) {
            $this->recordQueryFailure('Locations query', $e);
            return [];
        }
    }

    private function fetchAlertsFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $criticalCondition = $this->triageColumn !== null
                ? "i.status IN ('open','dispatched','referred') AND i.{$this->triageColumn} IN ('emergent','urgent')"
                : "i.status IN ('critical','urgent')";
            $hospitalFilter = $this->incidentHospitalCondition('i', 'hospital_id');
            $criticalStatement = $this->db->prepare("SELECT i.incident_type, i.location
                FROM incidents i
                WHERE {$hospitalFilter} AND {$criticalCondition}
                    AND i.reported_at >= :from_date
                    AND i.reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
                ORDER BY i.reported_at DESC
                LIMIT 1");
            $criticalStatement->execute(['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
            $critical = $criticalStatement->fetch();
            $resolvedStatement = $this->db->prepare("SELECT i.incident_type, i.location
                FROM incidents i
                WHERE {$hospitalFilter}
                    AND i.status = 'resolved'
                    AND i.resolved_at >= :from_date
                    AND i.resolved_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
                ORDER BY i.resolved_at DESC
                LIMIT 1");
            $resolvedStatement->execute(['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
            $resolved = $resolvedStatement->fetch();

            $alerts = [];
            if ($critical) {
                $alerts[] = [
                    'type' => 'critical',
                    'title' => 'Next action: confirm receiving facility',
                    'message' => trim((string) ($critical['incident_type'] ?? 'Emergency')) . ' at ' . trim((string) ($critical['location'] ?? 'the local district')) . ' needs immediate coordination.',
                ];
            }
            if ($resolved) {
                $alerts[] = [
                    'type' => 'resolved',
                    'title' => 'Recent handoff completed',
                    'message' => trim((string) ($resolved['incident_type'] ?? 'Case')) . ' in ' . trim((string) ($resolved['location'] ?? 'the district')) . ' has been closed successfully.',
                ];
            }

            return $alerts;
        } catch (Throwable $e) {
            $this->recordQueryFailure('Alerts query', $e);
            return [];
        }
    }
}
