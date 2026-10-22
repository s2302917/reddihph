<?php

class DemographicsModel
{
    private ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (Throwable $e) {
            error_log('[DemographicsModel] Database connection failed: ' . $e->getMessage());
        }
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

    public function getLogs(string $fromDate, string $toDate, int $hospitalId): array
    {
        $logs = $this->fetchLogsFromDb($fromDate, $toDate, $hospitalId);
        if ($logs !== []) {
            return $logs;
        }

        return [];
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
            error_log('[DemographicsModel] User profile query failed: ' . $e->getMessage());
            return null;
        }
    }

    private function fetchSummaryFromDb(string $fromDate, string $toDate, int $hospitalId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $statement = $this->db->prepare("SELECT
                (SELECT COUNT(*) FROM incidents WHERE hospital_id = :active_hospital AND reported_at >= :active_from AND reported_at < DATE_ADD(:active_to, INTERVAL 1 DAY) AND status IN ('critical','urgent','dispatched')) AS active_alerts,
                (SELECT COUNT(*) FROM incidents WHERE hospital_id = :resolved_hospital AND resolved_at >= :resolved_from AND resolved_at < DATE_ADD(:resolved_to, INTERVAL 1 DAY) AND status = 'resolved') AS resolved_in_range,
                (SELECT COUNT(*) FROM incidents WHERE hospital_id = :patients_hospital AND reported_at >= :patients_from AND reported_at < DATE_ADD(:patients_to, INTERVAL 1 DAY)) AS total_incidents,
                (SELECT COUNT(*) FROM incidents WHERE hospital_id = :risk_hospital AND reported_at >= :risk_from AND reported_at < DATE_ADD(:risk_to, INTERVAL 1 DAY) AND status IN ('critical','urgent','dispatched') AND (patient_age >= 60 OR eta_minutes <= 10)) AS high_risk_cases");
            $statement->execute([
                'active_hospital' => $hospitalId,
                'active_from' => $fromDate,
                'active_to' => $toDate,
                'resolved_hospital' => $hospitalId,
                'resolved_from' => $fromDate,
                'resolved_to' => $toDate,
                'patients_hospital' => $hospitalId,
                'patients_from' => $fromDate,
                'patients_to' => $toDate,
                'risk_hospital' => $hospitalId,
                'risk_from' => $fromDate,
                'risk_to' => $toDate,
            ]);
            $row = $statement->fetch();

            if (!$row) {
                return null;
            }

            return [
                'activeAlerts' => (int) ($row['active_alerts'] ?? 0),
                'resolvedToday' => (int) ($row['resolved_in_range'] ?? 0),
                'patientsLogged' => (int) ($row['total_incidents'] ?? 0),
                'highRiskCases' => (int) ($row['high_risk_cases'] ?? 0),
            ];
        } catch (Throwable $e) {
            error_log('[DemographicsModel] Summary query failed: ' . $e->getMessage());
            return null;
        }
    }

    private function fetchLogsFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->prepare("SELECT i.incident_id, i.incident_type, i.status, i.location,
                i.patient_gender, i.patient_age, i.responder_name, i.reported_at, i.hospital_name,
                i.transport_name, i.transport_type, i.driver_name,
                EXISTS (
                    SELECT 1
                    FROM user_type responder
                    WHERE responder.hospital_id = i.hospital_id
                        AND responder.full_name = i.responder_name
                        AND responder.role IN ('responder', 'volunteer')
                ) AS has_responder_user,
                vehicle.status AS transport_status, vehicle.arrived_at AS transport_arrived_at
                FROM incidents i
                LEFT JOIN ambulances vehicle
                    ON vehicle.hospital_id = i.hospital_id
                    AND (vehicle.call_sign = i.transport_name OR vehicle.call_sign = i.responder_name)
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
                ORDER BY i.reported_at DESC, i.incident_id DESC");
            $statement->execute(['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
            $rows = $statement->fetchAll();
            if (!$rows) {
                return [];
            }

            $logs = [];
            foreach ($rows as $row) {
                [$statusText, $statusClass] = $this->classifyLogStatus($row);
                $patientLabel = $row['patient_age'] !== null ? (($row['patient_gender'] && $row['patient_gender'] !== 'unknown') ? ucfirst($row['patient_gender']) . ' • ' . (int) $row['patient_age'] : 'Age ' . (int) $row['patient_age']) : 'Unknown patient';

                $logs[] = [
                    'time' => date('h:i A', strtotime((string) ($row['reported_at']))),
                    'patient' => $patientLabel,
                    'caller' => trim((string) ($row['responder_name'] ?: 'Dispatch team')),
                    'location' => trim((string) ($row['location'] ?: 'Unknown location')),
                    'type' => trim((string) ($row['incident_type'] ?: 'Emergency')),
                    'status' => $statusText,
                    'assigned' => trim((string) ($row['hospital_name'] ?: 'Duty desk')),
                    'statusClass' => $statusClass,
                ];
            }

            return $logs;
        } catch (Throwable $e) {
            error_log('[DemographicsModel] Logs query failed: ' . $e->getMessage());
            return [];
        }
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

        if (in_array($incidentStatus, ['critical', 'urgent'], true)) {
            return ['Critical', 'critical'];
        }

        return ['Assigned', 'assigned'];
    }

    private function fetchAgeGroupsFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->prepare("SELECT SUM(CASE WHEN patient_age BETWEEN 0 AND 17 THEN 1 ELSE 0 END) AS range_0_17, SUM(CASE WHEN patient_age BETWEEN 18 AND 35 THEN 1 ELSE 0 END) AS range_18_35, SUM(CASE WHEN patient_age BETWEEN 36 AND 59 THEN 1 ELSE 0 END) AS range_36_59, SUM(CASE WHEN patient_age >= 60 THEN 1 ELSE 0 END) AS range_60_plus FROM incidents WHERE hospital_id = :hospital_id AND patient_age IS NOT NULL AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY)");
            $statement->execute(['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
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
            error_log('[DemographicsModel] Age groups query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchGenderBreakdownFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->prepare("SELECT patient_gender, COUNT(*) AS total FROM incidents WHERE hospital_id = :hospital_id AND patient_gender IS NOT NULL AND patient_gender != 'unknown' AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY) GROUP BY patient_gender ORDER BY patient_gender");
            $statement->execute(['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
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
            error_log('[DemographicsModel] Gender query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchTopLocationsFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $statement = $this->db->prepare("SELECT location, COUNT(*) AS total FROM incidents WHERE hospital_id = :hospital_id AND location IS NOT NULL AND location != '' AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY) GROUP BY location ORDER BY total DESC, location LIMIT 4");
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
            error_log('[DemographicsModel] Locations query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchAlertsFromDb(string $fromDate, string $toDate, int $hospitalId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $criticalStatement = $this->db->prepare("SELECT incident_type, location FROM incidents WHERE hospital_id = :hospital_id AND status IN ('critical', 'urgent') AND reported_at >= :from_date AND reported_at < DATE_ADD(:to_date, INTERVAL 1 DAY) ORDER BY reported_at DESC LIMIT 1");
            $criticalStatement->execute(['hospital_id' => $hospitalId, 'from_date' => $fromDate, 'to_date' => $toDate]);
            $critical = $criticalStatement->fetch();
            $resolvedStatement = $this->db->prepare("SELECT incident_type, location FROM incidents WHERE hospital_id = :hospital_id AND status = 'resolved' AND resolved_at >= :from_date AND resolved_at < DATE_ADD(:to_date, INTERVAL 1 DAY) ORDER BY resolved_at DESC LIMIT 1");
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
            error_log('[DemographicsModel] Alerts query failed: ' . $e->getMessage());
            return [];
        }
    }
}
