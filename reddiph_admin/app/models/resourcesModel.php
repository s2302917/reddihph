<?php

require_once __DIR__ . '/HospitalBedInventoryModel.php';
require_once __DIR__ . '/AuditLogModel.php';

class ResourcesModel
{
    private ?PDO $db = null;
    private bool $bedAssignmentsAvailable = false;
    private bool $multipleBedStaffAvailable = false;
    private ?string $triageColumn = null;
    private bool $usesLegacyIncidentStatuses = true;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
            $bedColumns = $this->db->query('SHOW COLUMNS FROM hospital_beds')->fetchAll(PDO::FETCH_COLUMN);
            $incidentColumns = $this->db->query('SHOW COLUMNS FROM incidents')->fetchAll(PDO::FETCH_COLUMN);
            $this->bedAssignmentsAvailable = in_array('room_number', $bedColumns, true)
                && in_array('assigned_doctor_user_id', $bedColumns, true)
                && in_array('assigned_nurse_user_id', $bedColumns, true);
            $this->multipleBedStaffAvailable = $this->bedAssignmentsAvailable && (bool) $this->db
                ->query("SHOW TABLES LIKE 'hospital_bed_staff_assignments'")
                ->fetchColumn();
            $this->triageColumn = in_array('triage_category', $incidentColumns, true)
                ? 'triage_category'
                : (in_array('triage_status', $incidentColumns, true) ? 'triage_status' : null);
            $this->usesLegacyIncidentStatuses = $this->triageColumn === null;
        } catch (Throwable $e) {
            error_log('[ResourcesModel] Database connection or schema inspection failed: ' . $e->getMessage());
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
            error_log('[ResourcesModel] User profile query failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getHospitalResources(?int $hospitalId): array
    {
        if (!$this->db || !$hospitalId) {
            return [
                'summary' => [],
                'cards' => [],
                'bedAreas' => [],
                'staffOptions' => ['doctors' => [], 'nurses' => []],
                'vehicleCounts' => [],
                'fleet' => [],
                'alerts' => [],
                'bedAssignmentsAvailable' => $this->bedAssignmentsAvailable,
                'multipleBedStaffAvailable' => $this->multipleBedStaffAvailable,
                'dataError' => !$this->db
                    ? 'Resource records could not be loaded because the database is unavailable.'
                    : 'Resource records could not be loaded because no hospital is associated with this account.',
            ];
        }

        try {
            $hospitalStatement = $this->db->prepare('SELECT available_beds, total_beds FROM hospitals WHERE id = :hospital_id LIMIT 1');
            $hospitalStatement->execute(['hospital_id' => $hospitalId]);
            $hospital = $hospitalStatement->fetch();
            if (!$hospital) {
                return [
                    'summary' => [],
                    'cards' => [],
                    'bedAreas' => [],
                    'staffOptions' => ['doctors' => [], 'nurses' => []],
                    'vehicleCounts' => [],
                    'fleet' => [],
                    'alerts' => [],
                    'bedAssignmentsAvailable' => $this->bedAssignmentsAvailable,
                    'multipleBedStaffAvailable' => $this->multipleBedStaffAvailable,
                    'dataError' => 'Resource records could not be loaded because the hospital record was not found.',
                ];
            }

            $bedInventory = $this->getHospitalBedInventory($hospitalId);
            $bedAreas = $bedInventory['areas'];
            $bedTotal = $bedInventory['total'];
            $availableBeds = $bedInventory['available'];
            $facilityStatement = $this->db->prepare("SELECT metric_key, current_value, max_value, updated_at
                FROM facility_status
                WHERE hospital_id = :hospital_id
                AND metric_key IN ('bed_capacity', 'ambulance_bay', 'ptv')");
            $facilityStatement->execute(['hospital_id' => $hospitalId]);
            $facilityMetrics = [];
            foreach ($facilityStatement->fetchAll() as $metric) {
                $facilityMetrics[$metric['metric_key']] = $metric;
            }

            $vehicleStatement = $this->db->prepare("SELECT id, call_sign, plate_number, vehicle_type, status, assignment_note, eta_minutes, driver_name
                FROM ambulances
                WHERE hospital_id = :hospital_id
                ORDER BY FIELD(status, 'available', 'en_route', 'arrived', 'offline'), call_sign");
            $vehicleStatement->execute(['hospital_id' => $hospitalId]);
            $vehicles = $vehicleStatement->fetchAll();

            $vehicleCounts = [
                'all' => $this->sumVehicleFleet($vehicles, null),
                'ambulance' => $this->sumVehicleFleet($vehicles, 'Ambulance'),
                'ptv' => $this->sumVehicleFleet($vehicles, 'PTV'),
            ];
            $vehicleTotal = $vehicleCounts['all']['total'];
            $availableVehicles = $vehicleCounts['all']['available'];
            $otherVehicleCount = $vehicleTotal
                - $vehicleCounts['ambulance']['total']
                - $vehicleCounts['ptv']['total'];
            $vehicleStatus = $this->resolveMetricStatus($availableVehicles, $vehicleTotal);
            $vehicleBadge = ucfirst($vehicleStatus === 'available' ? 'stable' : $vehicleStatus);
            $vehicleMeta = $vehicleTotal > 0
                ? $vehicleCounts['all']['en_route'] . ' en route, '
                    . $vehicleCounts['all']['arrived'] . ' arrived, '
                    . $vehicleCounts['all']['offline'] . ' offline'
                : 'No vehicles registered';
            $vehicleBreakdown = 'Ambulance: ' . $vehicleCounts['ambulance']['total']
                . ' · PTV: ' . $vehicleCounts['ptv']['total']
                . ($otherVehicleCount > 0 ? ' · Other: ' . $otherVehicleCount : '');

            $maximumBeds = $bedTotal;
            $bedStatus = $this->resolveMetricStatus($availableBeds, $maximumBeds);
            $bedBadge = ucfirst($bedStatus === 'available' ? 'stable' : $bedStatus);
            $bedMeta = $maximumBeds > 0
                ? round(($availableBeds / $maximumBeds) * 100) . '% of capacity available'
                : 'Capacity not configured';

            $resourceCards = [
                [
                    'name' => 'Bed Capacity',
                    'type' => 'beds',
                    'metricKey' => 'bed_capacity',
                    'status' => $bedStatus,
                    'current' => $availableBeds,
                    'maximum' => $maximumBeds,
                    'configured' => $maximumBeds > 0,
                    'primary' => $availableBeds . ' available',
                    'secondary' => $maximumBeds . ' total beds',
                    'meta' => $bedMeta,
                    'badge' => $bedBadge,
                ],
                [
                    'name' => 'Vehicle Resources',
                    'type' => 'vehicles',
                    'metricKey' => 'vehicles',
                    'status' => $vehicleStatus,
                    'current' => $availableVehicles,
                    'maximum' => $vehicleTotal,
                    'configured' => $vehicleTotal > 0,
                    'primary' => $vehicleTotal . ' total vehicles',
                    'secondary' => $vehicleBreakdown,
                    'meta' => $vehicleMeta,
                    'badge' => $vehicleBadge,
                ],
            ];
            $summaryCards = [
                [
                    'label' => 'Available Beds',
                    'value' => $availableBeds . ' / ' . $maximumBeds,
                    'meta' => $bedMeta,
                    'status' => $bedStatus,
                ],
                [
                    'label' => 'Vehicle Resources',
                    'value' => (string) $vehicleTotal,
                    'meta' => $vehicleBreakdown,
                    'status' => $vehicleStatus,
                ],
            ];

            $summaryCards[] = [
                'label' => 'Fleet Status',
                'value' => $this->resolveFleetBadge($vehicles),
                'meta' => $vehicleMeta,
                'status' => count($vehicles) === 0 ? 'offline' : 'available',
            ];

            $fleet = array_map(function (array $vehicle): array {
                $status = $this->normalizeVehicleStatus((string) ($vehicle['status'] ?? 'available'));
                return [
                    'id' => (int) $vehicle['id'],
                    'name' => $vehicle['call_sign'] ?? 'Vehicle',
                    'type' => $vehicle['vehicle_type'] ?? 'Ambulance',
                    'plateNumber' => $vehicle['plate_number'] ?? '',
                    'driverName' => $vehicle['driver_name'] ?? '',
                    'status' => $status,
                    'filterStatus' => $status === 'available' ? 'available' : ($status === 'offline' ? 'limited' : 'critical'),
                    'statusLabel' => $this->vehicleStatusLabel($status),
                    'eta' => $vehicle['eta_minutes'] !== null && $vehicle['eta_minutes'] !== '' ? (int) $vehicle['eta_minutes'] . ' min' : ($status === 'available' ? 'Ready now' : 'No ETA'),
                    'note' => $vehicle['assignment_note'] ?: ($vehicle['driver_name'] ? 'Driver: ' . $vehicle['driver_name'] : 'Operational status reported.'),
                ];
            }, $vehicles);

            $alerts = [];
            foreach ($resourceCards as $card) {
                $updatedAt = $card['metricKey'] === 'vehicles'
                    ? max(
                        (string) ($facilityMetrics['ambulance_bay']['updated_at'] ?? ''),
                        (string) ($facilityMetrics['ptv']['updated_at'] ?? '')
                    )
                    : ($facilityMetrics[$card['metricKey']]['updated_at'] ?? null);
                $updatedAt = $updatedAt !== '' ? $updatedAt : null;
                $updatedTimestamp = $updatedAt ? strtotime((string) $updatedAt) : false;
                $alerts[] = [
                    'name' => $card['name'],
                    'status' => $card['status'],
                    'statusLabel' => $card['badge'],
                    'message' => $card['meta'],
                    'updatedAt' => $updatedTimestamp !== false ? date('M j · g:i A', $updatedTimestamp) : null,
                ];
            }

            return [
                'summary' => $summaryCards,
                'cards' => $resourceCards,
                'bedAreas' => $bedAreas,
                'staffOptions' => ['doctors' => [], 'nurses' => []],
                'vehicleCounts' => $vehicleCounts,
                'fleet' => $fleet,
                'alerts' => $alerts ?: [
                    [
                        'name' => 'Resource status',
                        'status' => 'available',
                        'statusLabel' => 'Stable',
                        'message' => 'All monitored resources are within normal operating thresholds.',
                    ],
                ],
                'bedAssignmentsAvailable' => $this->bedAssignmentsAvailable,
                'multipleBedStaffAvailable' => $this->multipleBedStaffAvailable,
                'dataError' => null,
            ];
        } catch (Throwable $e) {
            error_log('[ResourcesModel] Resource summary query failed: ' . $e->getMessage());
            return [
                'summary' => [],
                'cards' => [],
                'bedAreas' => [],
                'staffOptions' => ['doctors' => [], 'nurses' => []],
                'vehicleCounts' => [],
                'fleet' => [],
                'alerts' => [],
                'bedAssignmentsAvailable' => $this->bedAssignmentsAvailable,
                'multipleBedStaffAvailable' => $this->multipleBedStaffAvailable,
                'dataError' => 'Resource records could not be loaded. Check the database connection and schema.',
            ];
        }
    }

    private function getHospitalBedInventory(int $hospitalId, ?int $onlyBedId = null): array
    {
        $roomNumberColumn = $this->bedAssignmentsAvailable ? 'b.room_number' : 'NULL';
        $doctorIdColumn = $this->bedAssignmentsAvailable ? 'b.assigned_doctor_user_id' : 'NULL';
        $nurseIdColumn = $this->bedAssignmentsAvailable ? 'b.assigned_nurse_user_id' : 'NULL';
        $doctorNameColumn = $this->bedAssignmentsAvailable ? 'doctor.full_name' : 'NULL';
        $nurseNameColumn = $this->bedAssignmentsAvailable ? 'nurse.full_name' : 'NULL';
        $doctorJoin = $this->bedAssignmentsAvailable
            ? "LEFT JOIN user_type doctor
                    ON doctor.id = b.assigned_doctor_user_id
                   AND doctor.hospital_id = b.hospital_id
                   AND doctor.role = 'doctor'"
            : '';
        $nurseJoin = $this->bedAssignmentsAvailable
            ? "LEFT JOIN user_type nurse
                    ON nurse.id = b.assigned_nurse_user_id
                   AND nurse.hospital_id = b.hospital_id
                   AND nurse.role = 'nurse'"
            : '';
        $triageColumn = $this->triageColumn !== null ? 'i.' . $this->triageColumn : 'NULL';
        $activeStatuses = $this->usesLegacyIncidentStatuses
            ? "'critical','dispatched','urgent','stable'"
            : "'open','dispatched','referred'";
        $roomOrder = $this->bedAssignmentsAvailable ? "COALESCE(b.room_number, '')" : "''";

        $statement = $this->db->prepare(
            "SELECT
                b.id,
                b.bed_type,
                b.bed_number,
                {$roomNumberColumn} AS room_number,
                b.status,
                {$doctorIdColumn} AS assigned_doctor_user_id,
                {$doctorNameColumn} AS assigned_doctor_name,
                {$nurseIdColumn} AS assigned_nurse_user_id,
                {$nurseNameColumn} AS assigned_nurse_name,
                i.incident_id,
                i.incident_type,
                i.status AS incident_status,
                {$triageColumn} AS triage_category,
                COALESCE(NULLIF(TRIM(i.patient_name), ''), (
                    SELECT patient.patient_name
                    FROM incident_patients patient
                    WHERE patient.incident_id = i.incident_id
                    ORDER BY patient.id
                    LIMIT 1
                )) AS patient_name,
                COALESCE(i.patient_age, (
                    SELECT patient.patient_age
                    FROM incident_patients patient
                    WHERE patient.incident_id = i.incident_id
                    ORDER BY patient.id
                    LIMIT 1
                )) AS patient_age,
                COALESCE(NULLIF(i.patient_gender, 'unknown'), (
                    SELECT patient.patient_gender
                    FROM incident_patients patient
                    WHERE patient.incident_id = i.incident_id
                    ORDER BY patient.id
                    LIMIT 1
                )) AS patient_gender,
                i.reported_at
                ,i.notes AS incident_notes
                ,i.nurse_heart_rate
                ,i.nurse_blood_pressure
                ,i.nurse_spo2
                ,i.nurse_gcs
                ,i.nurse_notes
                ,i.updated_at AS incident_updated_at
                ,(SELECT patient.heart_rate FROM incident_patients patient
                  WHERE patient.incident_id = i.incident_id
                  ORDER BY patient.id DESC LIMIT 1) AS patient_heart_rate
                ,(SELECT patient.spo2 FROM incident_patients patient
                  WHERE patient.incident_id = i.incident_id
                  ORDER BY patient.id DESC LIMIT 1) AS patient_spo2
                ,(SELECT patient.nurse_blood_pressure FROM incident_patients patient
                  WHERE patient.incident_id = i.incident_id
                  ORDER BY patient.id DESC LIMIT 1) AS patient_blood_pressure
                ,(SELECT patient.notes FROM incident_patients patient
                  WHERE patient.incident_id = i.incident_id
                  ORDER BY patient.id DESC LIMIT 1) AS patient_notes
                ,(SELECT patient.created_at FROM incident_patients patient
                  WHERE patient.incident_id = i.incident_id
                  ORDER BY patient.id DESC LIMIT 1) AS patient_recorded_at
             FROM hospital_beds b
             LEFT JOIN hospitals h ON h.id = b.hospital_id
             {$doctorJoin}
             {$nurseJoin}
             LEFT JOIN (
                SELECT nurse_bed_id, MAX(incident_id) AS incident_id
                FROM incidents
                WHERE nurse_bed_id IS NOT NULL
                  AND status IN ({$activeStatuses})
                GROUP BY nurse_bed_id
             ) active_case ON active_case.nurse_bed_id = b.id
             LEFT JOIN incidents i
                ON i.incident_id = active_case.incident_id
               AND (i.hospital_id = b.hospital_id
                    OR (i.hospital_id IS NULL AND i.hospital_name = h.name))
             WHERE b.hospital_id = :hospital_id
               " . ($onlyBedId !== null ? 'AND b.id = :bed_id' : '') . "
             ORDER BY FIELD(b.bed_type, 'er', 'pediatric'),
                {$roomOrder}, b.bed_number"
        );
        $parameters = ['hospital_id' => $hospitalId];
        if ($onlyBedId !== null) {
            $parameters['bed_id'] = $onlyBedId;
        }
        $statement->execute($parameters);
        $rows = $statement->fetchAll();
        $staffAssignments = [];
        if ($this->multipleBedStaffAvailable && $rows) {
            $bedIds = array_values(array_unique(array_map(
                static fn(array $row): int => (int) $row['id'],
                $rows
            )));
            $placeholders = implode(',', array_fill(0, count($bedIds), '?'));
            $assignmentStatement = $this->db->prepare(
                "SELECT assignment.bed_id, assignment.user_id, assignment.role, staff.full_name
                 FROM hospital_bed_staff_assignments assignment
                 INNER JOIN hospital_beds assigned_bed
                    ON assigned_bed.id = assignment.bed_id
                   AND assigned_bed.hospital_id = ?
                 INNER JOIN user_type staff
                    ON staff.id = assignment.user_id
                   AND staff.hospital_id = assigned_bed.hospital_id
                   AND staff.role = assignment.role
                 WHERE assignment.bed_id IN ({$placeholders})
                 ORDER BY staff.full_name, staff.id"
            );
            $assignmentStatement->execute(array_merge([$hospitalId], $bedIds));
            foreach ($assignmentStatement->fetchAll() as $assignment) {
                $staffAssignments[(int) $assignment['bed_id']][(string) $assignment['role']][] = [
                    'id' => (int) $assignment['user_id'],
                    'name' => (string) $assignment['full_name'],
                ];
            }
        }

        $areas = [
            'er' => [
                'key' => 'er',
                'label' => 'Emergency Room (ER)',
                'beds' => [],
                'total' => 0,
                'available' => 0,
                'occupied' => 0,
                'unavailable' => 0,
            ],
            'pediatric' => [
                'key' => 'pediatric',
                'label' => 'Pediatric Bay',
                'beds' => [],
                'total' => 0,
                'available' => 0,
                'occupied' => 0,
                'unavailable' => 0,
            ],
        ];

        foreach ($rows as $row) {
            $type = (string) $row['bed_type'];
            if (!isset($areas[$type])) {
                continue;
            }

            $status = (string) $row['status'];
            $countKey = $status === 'ready'
                ? 'available'
                : ($status === 'occupied' ? 'occupied' : 'unavailable');
            $patientName = trim((string) ($row['patient_name'] ?? ''));

            $doctorAssignments = $staffAssignments[(int) $row['id']]['doctor'] ?? [];
            $nurseAssignments = $staffAssignments[(int) $row['id']]['nurse'] ?? [];
            if (!$doctorAssignments && $row['assigned_doctor_user_id'] !== null) {
                $doctorAssignments[] = [
                    'id' => (int) $row['assigned_doctor_user_id'],
                    'name' => trim((string) ($row['assigned_doctor_name'] ?? '')),
                ];
            }
            if (!$nurseAssignments && $row['assigned_nurse_user_id'] !== null) {
                $nurseAssignments[] = [
                    'id' => (int) $row['assigned_nurse_user_id'],
                    'name' => trim((string) ($row['assigned_nurse_name'] ?? '')),
                ];
            }
            $bed = [
                'id' => (int) $row['id'],
                'type' => $type,
                'number' => (int) $row['bed_number'],
                'room' => trim((string) ($row['room_number'] ?? '')),
                'status' => $status,
                'statusLabel' => $this->bedStatusLabel($status),
                'doctorIds' => array_column($doctorAssignments, 'id'),
                'doctorNames' => array_column($doctorAssignments, 'name'),
                'doctorId' => isset($doctorAssignments[0]) ? (string) $doctorAssignments[0]['id'] : '',
                'doctorName' => implode(', ', array_column($doctorAssignments, 'name')),
                'nurseIds' => array_column($nurseAssignments, 'id'),
                'nurseNames' => array_column($nurseAssignments, 'name'),
                'nurseId' => isset($nurseAssignments[0]) ? (string) $nurseAssignments[0]['id'] : '',
                'nurseName' => implode(', ', array_column($nurseAssignments, 'name')),
                'incidentId' => $row['incident_id'] === null
                    ? null
                    : (int) $row['incident_id'],
                'incidentType' => trim((string) ($row['incident_type'] ?? '')),
                'incidentStatus' => trim((string) ($row['incident_status'] ?? '')),
                'triageCategory' => trim((string) ($row['triage_category'] ?? '')),
                'patientName' => $patientName,
                'patientAge' => $row['patient_age'] === null
                    ? null
                    : (int) $row['patient_age'],
                'patientGender' => trim((string) ($row['patient_gender'] ?? '')),
                'reportedAt' => trim((string) ($row['reported_at'] ?? '')),
                'vitals' => [
                    'heartRate' => $row['nurse_heart_rate'] ?? $row['patient_heart_rate'],
                    'bloodPressure' => $row['nurse_blood_pressure'] ?? $row['patient_blood_pressure'],
                    'spo2' => $row['nurse_spo2'] ?? $row['patient_spo2'],
                    'gcs' => $row['nurse_gcs'],
                    'recordedAt' => $row['patient_recorded_at'] ?: $row['incident_updated_at'],
                ],
                'nurseNotes' => trim((string) ($row['nurse_notes'] ?? '')),
                'incidentNotes' => trim((string) ($row['incident_notes'] ?? '')),
                'patientNotes' => trim((string) ($row['patient_notes'] ?? '')),
            ];
            $areas[$type]['beds'][] = $bed;
            $areas[$type]['total']++;
            $areas[$type][$countKey]++;
        }

        return [
            'areas' => array_values($areas),
            'total' => array_sum(array_column($areas, 'total')),
            'available' => array_sum(array_column($areas, 'available')),
        ];
    }

    public function getHospitalBed(int $hospitalId, int $bedId): ?array
    {
        if (!$this->db) {
            throw new RuntimeException('Bed details are unavailable because the database connection failed.');
        }
        if ($hospitalId <= 0 || $bedId <= 0) {
            return null;
        }

        $inventory = $this->getHospitalBedInventory($hospitalId, $bedId);
        foreach ($inventory['areas'] as $area) {
            if (!empty($area['beds'][0])) {
                $bed = $area['beds'][0];
                $bed['clinicalNotes'] = [];
                if ($bed['incidentId'] !== null) {
                    $notes = $this->db->prepare(
                        'SELECT update_note.note, update_note.created_at, author.full_name, author.role
                         FROM incident_note_updates update_note
                         INNER JOIN user_type author ON author.id = update_note.author_user_id
                         WHERE update_note.incident_id = :incident_id
                         ORDER BY update_note.created_at DESC, update_note.id DESC
                         LIMIT 10'
                    );
                    $notes->execute(['incident_id' => $bed['incidentId']]);
                    $bed['clinicalNotes'] = array_map(static function (array $note): array {
                        return [
                            'note' => (string) $note['note'],
                            'createdAt' => (string) $note['created_at'],
                            'author' => (string) $note['full_name'],
                            'role' => (string) $note['role'],
                        ];
                    }, $notes->fetchAll());
                }
                return $bed;
            }
        }
        return null;
    }

    public function searchHospitalIncidents(
        int $hospitalId,
        string $query,
        int $page = 1,
        int $perPage = 5,
        int $bedId = 0
    ): array
    {
        if (!$this->db || $hospitalId <= 0) {
            throw new RuntimeException('Incident search is unavailable for this hospital.');
        }
        if ($page < 1) {
            throw new InvalidArgumentException('Choose a valid incident results page.');
        }
        if ($bedId <= 0) {
            throw new InvalidArgumentException('Save the bed before searching for incidents.');
        }
        $bedCheck = $this->db->prepare(
            'SELECT 1 FROM hospital_beds
             WHERE id = :bed_id AND hospital_id = :hospital_id
             LIMIT 1'
        );
        $bedCheck->execute(['bed_id' => $bedId, 'hospital_id' => $hospitalId]);
        if ($bedCheck->fetchColumn() === false) {
            throw new InvalidArgumentException('Choose a bed belonging to this hospital.');
        }
        $query = trim($query);
        if ($query !== '' && mb_strlen($query) < 2) {
            throw new InvalidArgumentException('Enter at least two characters to search incidents.');
        }
        $idQuery = preg_match('/^#?\d+$/', $query) === 1 ? ltrim($query, '#') : $query;
        $idLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $idQuery) . '%';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $activeStatuses = $this->usesLegacyIncidentStatuses
            ? "'critical','dispatched','urgent','stable'"
            : "'open','dispatched','referred'";
        $fromWhere = " FROM incidents i
             LEFT JOIN hospitals h ON h.id = i.hospital_id
             WHERE (i.hospital_id = :hospital_id
                    OR (i.hospital_id IS NULL AND i.hospital_name = (
                        SELECT hospital.name FROM hospitals hospital WHERE hospital.id = :hospital_name_id
                    )))
               AND i.status IN ({$activeStatuses})
               AND (i.nurse_bed_id IS NULL OR i.nurse_bed_id = :bed_id)
               AND (
                    CAST(i.incident_id AS CHAR) LIKE :incident_id
                    OR i.incident_type LIKE :incident_type
                    OR i.patient_name LIKE :incident_patient
                    OR EXISTS (
                        SELECT 1 FROM incident_patients searched_patient
                        WHERE searched_patient.incident_id = i.incident_id
                          AND searched_patient.patient_name LIKE :patient_name
                    )
               )";
        $parameters = [
            'hospital_id' => $hospitalId,
            'hospital_name_id' => $hospitalId,
            'bed_id' => $bedId,
            'incident_id' => $idLike,
            'incident_type' => $like,
            'incident_patient' => $like,
            'patient_name' => $like,
        ];
        $count = $this->db->prepare('SELECT COUNT(*)' . $fromWhere);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $statement = $this->db->prepare(
            "SELECT
                i.incident_id,
                i.incident_type,
                i.status,
                DATE_FORMAT(i.reported_at, '%b %e, %Y %h:%i %p') AS reported_at,
                COALESCE(NULLIF(TRIM(i.patient_name), ''), (
                    SELECT patient.patient_name
                    FROM incident_patients patient
                    WHERE patient.incident_id = i.incident_id
                    ORDER BY patient.id
                    LIMIT 1
                )) AS patient_name,
                COALESCE(h.name, i.hospital_name) AS hospital_name,
                i.nurse_bed_id
             {$fromWhere}
             ORDER BY i.reported_at DESC, i.incident_id DESC
             LIMIT :page_size OFFSET :page_offset"
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':page_size', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':page_offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map(static function (array $incident): array {
            return [
                'id' => (int) $incident['incident_id'],
                'type' => (string) $incident['incident_type'],
                'status' => (string) $incident['status'],
                'reportedAt' => (string) $incident['reported_at'],
                'patient' => (string) ($incident['patient_name'] ?? ''),
                'hospital' => (string) $incident['hospital_name'],
                'assignedBedId' => $incident['nurse_bed_id'] === null
                    ? null
                    : (int) $incident['nurse_bed_id'],
            ];
        }, $statement->fetchAll());
        return [
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'perPage' => $perPage,
        ];
    }

    public function assignIncidentToBed(
        int $hospitalId,
        int $bedId,
        ?int $incidentId,
        int $actorId
    ): void {
        if (!$this->db || $hospitalId <= 0 || $bedId <= 0 || ($incidentId !== null && $incidentId <= 0)) {
            throw new InvalidArgumentException('Choose a valid bed and incident.');
        }

        $activeStatuses = $this->usesLegacyIncidentStatuses
            ? "'critical','dispatched','urgent','stable'"
            : "'open','dispatched','referred'";
        $this->db->beginTransaction();
        try {
            $bedQuery = $this->db->prepare(
                'SELECT b.status, h.name AS hospital_name
                 FROM hospital_beds b
                 INNER JOIN hospitals h ON h.id = b.hospital_id
                 WHERE b.id = :bed_id AND b.hospital_id = :hospital_id
                 LIMIT 1 FOR UPDATE'
            );
            $bedQuery->execute(['bed_id' => $bedId, 'hospital_id' => $hospitalId]);
            $bed = $bedQuery->fetch();
            if (!$bed) {
                throw new InvalidArgumentException('Bed record was not found in this hospital.');
            }

            $currentIncidentQuery = $this->db->prepare(
                "SELECT incident_id
                 FROM incidents
                 WHERE nurse_bed_id = :bed_id AND status IN ({$activeStatuses})
                 ORDER BY incident_id DESC
                 FOR UPDATE"
            );
            $currentIncidentQuery->execute(['bed_id' => $bedId]);
            $currentIncidentIds = array_map('intval', $currentIncidentQuery->fetchAll(PDO::FETCH_COLUMN));
            if (count($currentIncidentIds) > 1) {
                throw new RuntimeException('This bed has multiple active incident assignments. Resolve them before changing the assignment.');
            }
            $currentIncidentId = $currentIncidentIds[0] ?? false;

            if ($incidentId === null) {
                if ($currentIncidentId !== false) {
                    $detach = $this->db->prepare(
                        'UPDATE incidents SET nurse_bed_id = NULL
                         WHERE incident_id = :incident_id AND nurse_bed_id = :bed_id'
                    );
                    $detach->execute(['incident_id' => $currentIncidentId, 'bed_id' => $bedId]);
                    if ($bed['status'] === 'preparing') {
                        $release = $this->db->prepare(
                            "UPDATE hospital_beds SET status = 'ready'
                             WHERE id = :bed_id AND hospital_id = :hospital_id AND status = 'preparing'"
                        );
                        $release->execute(['bed_id' => $bedId, 'hospital_id' => $hospitalId]);
                    }
                }
            } else {
                $incidentQuery = $this->db->prepare(
                    "SELECT incident_id, status, nurse_bed_id
                     FROM incidents
                     WHERE incident_id = :incident_id
                       AND (hospital_id = :hospital_id
                            OR (hospital_id IS NULL AND hospital_name = :hospital_name))
                       AND status IN ({$activeStatuses})
                     LIMIT 1 FOR UPDATE"
                );
                $incidentQuery->execute([
                    'incident_id' => $incidentId,
                    'hospital_id' => $hospitalId,
                    'hospital_name' => $bed['hospital_name'],
                ]);
                $incident = $incidentQuery->fetch();
                if (!$incident) {
                    throw new InvalidArgumentException('Choose an active incident belonging to this hospital.');
                }
                if ($incident['nurse_bed_id'] !== null && (int) $incident['nurse_bed_id'] !== $bedId) {
                    throw new InvalidArgumentException('This incident is already assigned to another bed.');
                }
                if ($currentIncidentId !== false && (int) $currentIncidentId !== $incidentId) {
                    throw new InvalidArgumentException('Remove the current incident assignment before choosing another.');
                }
                if ($bed['status'] !== 'ready' && $bed['status'] !== 'preparing'
                    && $currentIncidentId === false) {
                    throw new InvalidArgumentException('Only a ready bed can be assigned to an incident.');
                }
                if ($incident['nurse_bed_id'] === null) {
                    $attach = $this->db->prepare(
                        'UPDATE incidents SET nurse_bed_id = :bed_id
                         WHERE incident_id = :incident_id AND nurse_bed_id IS NULL'
                    );
                    $attach->execute(['bed_id' => $bedId, 'incident_id' => $incidentId]);
                    if ($attach->rowCount() !== 1) {
                        throw new InvalidArgumentException('The incident assignment changed. Refresh and try again.');
                    }
                }
                if ($bed['status'] === 'ready') {
                    $prepare = $this->db->prepare(
                        "UPDATE hospital_beds SET status = 'preparing'
                         WHERE id = :bed_id AND hospital_id = :hospital_id AND status = 'ready'"
                    );
                    $prepare->execute(['bed_id' => $bedId, 'hospital_id' => $hospitalId]);
                }
            }

            HospitalBedInventoryModel::synchronizeAggregates($this->db, $hospitalId);
            AuditLogModel::record($this->db, $actorId, 'resource.updated', 'hospital_bed', (string) $bedId, [
                'fields' => ['incident_assignment'],
                'incident_id' => $incidentId,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function searchHospitalStaff(
        int $hospitalId,
        string $role,
        string $query,
        int $page,
        int $perPage = 5
    ): array
    {
        if (!$this->db) {
            throw new RuntimeException('Staff search is unavailable because the database connection failed.');
        }
        if ($hospitalId <= 0 || !in_array($role, ['doctor', 'nurse'], true)) {
            throw new InvalidArgumentException('Choose a valid staff category.');
        }
        if ($page < 1) {
            throw new InvalidArgumentException('Choose a valid staff results page.');
        }

        $query = trim($query);
        $parameters = ['hospital_id' => $hospitalId, 'role' => $role];
        $searchClause = '';
        if ($query !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $searchClause = ' AND (u.full_name LIKE :name_search OR staff.department LIKE :specialty_search)';
            $parameters['name_search'] = $like;
            $parameters['specialty_search'] = $like;
        }
        $staffJoin = "LEFT JOIN staff ON staff.id = (
            SELECT candidate.id
            FROM staff candidate
            WHERE candidate.user_id = u.id
              AND (candidate.hospital_id = u.hospital_id OR candidate.hospital_id IS NULL)
            ORDER BY (candidate.hospital_id = u.hospital_id) DESC, candidate.id DESC
            LIMIT 1
        )";
        $fromWhere = " FROM user_type u
            {$staffJoin}
            WHERE u.hospital_id = :hospital_id
              AND u.role = :role
              AND u.account_archived_at IS NULL
              AND (u.banned_until IS NULL OR u.banned_until <= UTC_TIMESTAMP())
              AND u.approval_status = 'approved'
              {$searchClause}";

        $count = $this->db->prepare('SELECT COUNT(*)' . $fromWhere);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $statement = $this->db->prepare(
            "SELECT u.id, u.full_name,
                    COALESCE(NULLIF(TRIM(staff.department), ''), '') AS specialization,
                    COALESCE(staff.profile_image, u.profile_image) AS profile_image
             {$fromWhere}
             ORDER BY u.full_name, u.id
             LIMIT :page_size OFFSET :page_offset"
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':page_size', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':page_offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = array_map(static function (array $member) use ($role): array {
            return [
                'id' => (int) $member['id'],
                'name' => (string) $member['full_name'],
                'role' => ucfirst($role),
                'specialization' => (string) $member['specialization'],
                'imageUrl' => userProfileImageUrl($member['profile_image'] ?? null),
            ];
        }, $statement->fetchAll());

        return [
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'perPage' => $perPage,
        ];
    }

    public function saveBed(
        int $hospitalId,
        ?int $bedId,
        string $bedType,
        int $bedNumber,
        string $roomNumber,
        array $doctorIds,
        array $nurseIds,
        int $actorId,
        string $status = 'ready'
    ): int {
        $roomNumber = trim($roomNumber);
        if (
            !$this->db
            || $hospitalId <= 0
            || !in_array($bedType, ['er', 'pediatric'], true)
            || $bedNumber < 1
            || $bedNumber > 65535
            || ($this->bedAssignmentsAvailable && strlen($roomNumber) > 30)
            || ($bedId !== null && $bedId <= 0)
        ) {
            throw new InvalidArgumentException('Enter a valid bed area and number.');
        }
        if (!in_array($status, ['ready', 'preparing', 'occupied', 'cleaning'], true)) {
            throw new InvalidArgumentException('Choose a valid bed status.');
        }
        $doctorIds = array_values(array_unique(array_filter(array_map('intval', $doctorIds))));
        $nurseIds = array_values(array_unique(array_filter(array_map('intval', $nurseIds))));
        if (!$this->bedAssignmentsAvailable && ($doctorIds || $nurseIds)) {
            throw new InvalidArgumentException('Room and staff assignments are unavailable until the bed assignment migration is applied.');
        }
        if (!$this->multipleBedStaffAvailable && (count($doctorIds) > 1 || count($nurseIds) > 1)) {
            throw new InvalidArgumentException('Apply the multiple bed staff assignments migration to assign more than one staff member.');
        }

        $this->db->beginTransaction();
        try {
            $hospitalLock = $this->db->prepare(
                'SELECT id FROM hospitals WHERE id = :hospital_id LIMIT 1 FOR UPDATE'
            );
            $hospitalLock->execute(['hospital_id' => $hospitalId]);
            if ($hospitalLock->fetchColumn() === false) {
                throw new InvalidArgumentException('Hospital record was not found.');
            }
            if ($this->bedAssignmentsAvailable) {
                foreach ($doctorIds as $doctorId) {
                    $this->validateAssignedStaff($hospitalId, $doctorId, 'doctor');
                }
                foreach ($nurseIds as $nurseId) {
                    $this->validateAssignedStaff($hospitalId, $nurseId, 'nurse');
                }
            }
            $doctorId = $doctorIds[0] ?? null;
            $nurseId = $nurseIds[0] ?? null;

            $values = [
                'bed_type' => $bedType,
                'bed_number' => $bedNumber,
                'status' => $status,
                'hospital_id' => $hospitalId,
            ];

            if ($bedId === null) {
                $assignmentColumns = $this->bedAssignmentsAvailable
                    ? ', room_number, assigned_doctor_user_id, assigned_nurse_user_id'
                    : '';
                $assignmentValues = $this->bedAssignmentsAvailable
                    ? ', :room_number, :doctor_id, :nurse_id'
                    : '';
                if ($this->bedAssignmentsAvailable) {
                    $values += [
                        'room_number' => $roomNumber !== '' ? $roomNumber : null,
                        'doctor_id' => $doctorId,
                        'nurse_id' => $nurseId,
                    ];
                }
                $statement = $this->db->prepare(
                    "INSERT INTO hospital_beds
                        (hospital_id, bed_type, bed_number{$assignmentColumns}, status)
                     VALUES
                        (:hospital_id, :bed_type, :bed_number{$assignmentValues}, :status)"
                );
                $statement->execute($values);
                $bedId = (int) $this->db->lastInsertId();
                $action = 'bed.created';
            } else {
                $current = $this->db->prepare(
                    'SELECT bed_type, bed_number, status
                     FROM hospital_beds
                     WHERE id = :bed_id AND hospital_id = :hospital_id
                     LIMIT 1 FOR UPDATE'
                );
                $current->execute(['bed_id' => $bedId, 'hospital_id' => $hospitalId]);
                $currentBed = $current->fetch();
                if (!$currentBed) {
                    throw new InvalidArgumentException('Bed record was not found.');
                }

                if (
                    ($currentBed['bed_type'] !== $bedType
                        || (int) $currentBed['bed_number'] !== $bedNumber)
                    && $currentBed['status'] === 'occupied'
                ) {
                    throw new InvalidArgumentException(
                        'An occupied bed cannot be moved to another area or bed number.'
                    );
                }

                $activeStatuses = $this->usesLegacyIncidentStatuses
                    ? "'critical','dispatched','urgent','stable'"
                    : "'open','dispatched','referred'";
                $activeCase = $this->db->prepare(
                    "SELECT 1 FROM incidents
                     WHERE nurse_bed_id = :bed_id
                       AND status IN ({$activeStatuses})
                     LIMIT 1 FOR UPDATE"
                );
                $activeCase->execute(['bed_id' => $bedId]);
                $hasActiveCase = $activeCase->fetchColumn() !== false;
                if (
                    $hasActiveCase
                    && ($currentBed['bed_type'] !== $bedType
                        || (int) $currentBed['bed_number'] !== $bedNumber)
                ) {
                    throw new InvalidArgumentException(
                        'A bed assigned to an active case cannot be moved.'
                    );
                }
                if ($hasActiveCase && !in_array($status, ['preparing', 'occupied'], true)) {
                    throw new InvalidArgumentException(
                        'A bed linked to an active incident must remain preparing or occupied.'
                    );
                }
                if (
                    $hasActiveCase
                    && $currentBed['status'] === 'occupied'
                    && $status !== 'occupied'
                ) {
                    throw new InvalidArgumentException(
                        'A bed occupied by an active incident cannot be changed to another status.'
                    );
                }

                $values['bed_id'] = $bedId;
                $assignmentUpdate = '';
                if ($this->bedAssignmentsAvailable) {
                    $assignmentUpdate = ',
                         room_number = :room_number,
                         assigned_doctor_user_id = :doctor_id,
                         assigned_nurse_user_id = :nurse_id';
                    $values['room_number'] = $roomNumber !== '' ? $roomNumber : null;
                    $values['doctor_id'] = $doctorId;
                    $values['nurse_id'] = $nurseId;
                }
                $statement = $this->db->prepare(
                    "UPDATE hospital_beds
                     SET bed_type = :bed_type, bed_number = :bed_number, status = :status{$assignmentUpdate}
                     WHERE id = :bed_id AND hospital_id = :hospital_id"
                );
                $statement->execute($values);
                $action = 'bed.updated';
            }

            if ($this->multipleBedStaffAvailable) {
                $deleteAssignments = $this->db->prepare(
                    'DELETE FROM hospital_bed_staff_assignments WHERE bed_id = :bed_id'
                );
                $deleteAssignments->execute(['bed_id' => $bedId]);
                $insertAssignment = $this->db->prepare(
                    'INSERT INTO hospital_bed_staff_assignments (bed_id, user_id, role)
                     VALUES (:bed_id, :user_id, :role)'
                );
                foreach ([
                    'doctor' => $doctorIds,
                    'nurse' => $nurseIds,
                ] as $role => $staffIds) {
                    foreach ($staffIds as $staffId) {
                        $insertAssignment->execute([
                            'bed_id' => $bedId,
                            'user_id' => $staffId,
                            'role' => $role,
                        ]);
                    }
                }
            }

            HospitalBedInventoryModel::synchronizeAggregates($this->db, $hospitalId);
            AuditLogModel::record($this->db, $actorId, $action, 'hospital_bed', (string) $bedId, [
                'bed_type' => $bedType,
                'bed_number' => $bedNumber,
                'status' => $status,
                'room_number' => $roomNumber,
                'assigned_doctor_user_ids' => $doctorIds,
                'assigned_nurse_user_ids' => $nurseIds,
            ]);
            $this->db->commit();
            return $bedId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function validateAssignedStaff(int $hospitalId, ?int $staffId, string $role): void
    {
        if ($staffId === null) {
            return;
        }
        if ($staffId <= 0) {
            throw new InvalidArgumentException('Choose a valid assigned staff member.');
        }

        $statement = $this->db->prepare(
            'SELECT 1 FROM user_type
             WHERE id = :staff_id AND hospital_id = :hospital_id AND role = :role
             LIMIT 1 FOR UPDATE'
        );
        $statement->execute([
            'staff_id' => $staffId,
            'hospital_id' => $hospitalId,
            'role' => $role,
        ]);
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException(
                'Assigned ' . $role . ' must belong to this hospital.'
            );
        }
    }

    private function bedStatusLabel(string $status): string
    {
        switch ($status) {
            case 'ready':
                return 'Available';
            case 'occupied':
                return 'Occupied';
            case 'preparing':
                return 'Preparing';
            case 'cleaning':
                return 'Cleaning';
            default:
                return 'Unavailable';
        }
    }

    public function adjustAvailableBeds(int $hospitalId, int $change, int $actorId): void
    {
        if (!$this->db || $hospitalId <= 0) {
            throw new InvalidArgumentException('Invalid bed capacity change.');
        }

        HospitalBedInventoryModel::adjustAvailability($this->db, $hospitalId, $change, $actorId);
    }

    public function updateBedMaximum(int $hospitalId, int $newMaximum, int $actorId): void
    {
        if (!$this->db || $hospitalId <= 0 || $newMaximum < 0 || $newMaximum > 10000) {
            throw new InvalidArgumentException('Enter a valid maximum bed capacity.');
        }

        HospitalBedInventoryModel::resize($this->db, $hospitalId, $newMaximum, $actorId);
    }

    public function saveVehicle(int $hospitalId, ?int $vehicleId, string $vehicleType, string $callSign, string $plateNumber, string $driverName, ?string $status, int $actorId): void
    {
        $callSign = trim($callSign);
        $plateNumber = strtoupper(trim($plateNumber));
        $driverName = trim($driverName);
        if (!$this->db || $hospitalId <= 0 || !in_array($vehicleType, ['Ambulance', 'PTV'], true)) {
            throw new InvalidArgumentException('Choose Ambulance or PTV.');
        }
        if ($callSign === '' || $plateNumber === '' || $driverName === '') {
            throw new InvalidArgumentException('Call sign, plate number, and driver name are required.');
        }
        if (strlen($callSign) > 30 || strlen($plateNumber) > 30 || strlen($driverName) > 160) {
            throw new InvalidArgumentException('One or more vehicle fields exceed the database field limit.');
        }
        if ($vehicleId !== null && !in_array($status, ['available', 'en_route', 'arrived', 'offline'], true)) {
            throw new InvalidArgumentException('Choose a valid vehicle status.');
        }

        $isNewVehicle = $vehicleId === null;
        if ($isNewVehicle) {
            $statement = $this->db->prepare("INSERT INTO ambulances
                (call_sign, hospital_id, vehicle_type, status, driver_name, plate_number)
                VALUES (:call_sign, :hospital_id, :vehicle_type, 'available', :driver_name, :plate_number)");
        } else {
            $existing = $this->db->prepare(
                'SELECT vehicle_type FROM ambulances WHERE id = :vehicle_id AND hospital_id = :hospital_id LIMIT 1'
            );
            $existing->execute(['vehicle_id' => $vehicleId, 'hospital_id' => $hospitalId]);
            $existingType = $existing->fetchColumn();
            if ($existingType === false) {
                throw new InvalidArgumentException('Vehicle record was not found.');
            }
            if ($existingType !== $vehicleType) {
                throw new InvalidArgumentException('Open this vehicle from its matching Resources section.');
            }
            $statement = $this->db->prepare("UPDATE ambulances
                SET call_sign = :call_sign, status = :status, vehicle_type = :vehicle_type,
                    driver_name = :driver_name, plate_number = :plate_number
                WHERE id = :vehicle_id AND hospital_id = :hospital_id");
        }

        $values = [
            'call_sign' => $callSign,
            'vehicle_type' => $vehicleType,
            'driver_name' => $driverName,
            'plate_number' => $plateNumber,
            'hospital_id' => $hospitalId,
        ];
        if ($vehicleId !== null) {
            $values['status'] = $status;
            $values['vehicle_id'] = $vehicleId;
        }
        $statement->execute($values);
        if ($isNewVehicle) {
            $vehicleId = (int) $this->db->lastInsertId();
            AuditLogModel::record($this->db, $actorId, 'vehicle.created', 'ambulance', (string) $vehicleId, [
                'fields' => ['vehicle_type', 'call_sign', 'plate_number', 'driver_name', 'status'],
            ]);
        }
        if (!$isNewVehicle && $statement->rowCount() === 0) {
            $check = $this->db->prepare('SELECT 1 FROM ambulances WHERE id = :vehicle_id AND hospital_id = :hospital_id');
            $check->execute(['vehicle_id' => $vehicleId, 'hospital_id' => $hospitalId]);
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('Vehicle record was not found.');
            }
        } elseif (!$isNewVehicle && $statement->rowCount() > 0) {
            AuditLogModel::record($this->db, $actorId, 'vehicle.updated', 'ambulance', (string) $vehicleId, [
                'fields' => ['vehicle_type', 'call_sign', 'plate_number', 'driver_name', 'status'],
            ]);
        }
    }

    public function deleteVehicle(int $hospitalId, int $vehicleId, int $actorId): void
    {
        if (!$this->db || $hospitalId <= 0 || $vehicleId <= 0) {
            throw new InvalidArgumentException('Invalid vehicle record.');
        }

        $statement = $this->db->prepare('DELETE FROM ambulances WHERE id = :vehicle_id AND hospital_id = :hospital_id');
        $statement->execute(['vehicle_id' => $vehicleId, 'hospital_id' => $hospitalId]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Vehicle record was not found.');
        }
        AuditLogModel::record($this->db, $actorId, 'vehicle.deleted', 'ambulance', (string) $vehicleId);
    }

    private function resolveMetricStatus(int $current, int $maximum): string
    {
        if ($maximum <= 0) {
            return 'unavailable';
        }
        $availability = $current / $maximum;
        if ($availability <= 0.3) {
            return 'critical';
        }
        return $availability <= 0.5 ? 'limited' : 'available';
    }

    private function sumVehicleFleet(array $vehicles, ?string $vehicleType): array
    {
        $total = 0;
        $available = 0;
        $enRoute = 0;
        $arrived = 0;
        $offline = 0;

        foreach ($vehicles as $vehicle) {
            if ($vehicleType !== null
                && strcasecmp(trim((string) ($vehicle['vehicle_type'] ?? '')), $vehicleType) !== 0
            ) {
                continue;
            }
            $total++;
            $status = $this->normalizeVehicleStatus((string) ($vehicle['status'] ?? 'available'));
            if ($status === 'available') {
                $available++;
            }
            if ($status === 'en_route') {
                $enRoute++;
            }
            if ($status === 'arrived') {
                $arrived++;
            }
            if ($status === 'offline') {
                $offline++;
            }
        }

        return [
            'total' => $total,
            'available' => $available,
            'en_route' => $enRoute,
            'arrived' => $arrived,
            'offline' => $offline,
        ];
    }

    private function resolveFleetBadge(array $vehicles): string
    {
        if (!$vehicles) {
            return 'No units';
        }

        $statuses = array_map(function (array $vehicle): string {
            return $this->normalizeVehicleStatus((string) ($vehicle['status'] ?? 'available'));
        }, $vehicles);

        $available = count(array_filter($statuses, fn(string $status): bool => $status === 'available'));
        $inTransit = count(array_filter($statuses, fn(string $status): bool => $status === 'en_route'));

        if ($available > 0) {
            return $available . ' ready';
        }
        if ($inTransit > 0) {
            return $inTransit . ' in transit';
        }

        return 'Monitoring';
    }

    private function normalizeVehicleStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (in_array($normalized, ['en_route', 'in_transit'], true)) {
            return 'en_route';
        }
        if ($normalized === 'arrived') {
            return 'arrived';
        }
        if ($normalized === 'offline') {
            return 'offline';
        }
        return 'available';
    }

    private function vehicleStatusLabel(string $status): string
    {
        switch ($status) {
            case 'en_route':
                return 'En route';
            case 'arrived':
                return 'Arrived';
            case 'offline':
                return 'Offline';
            default:
                return 'Available';
        }
    }
}
