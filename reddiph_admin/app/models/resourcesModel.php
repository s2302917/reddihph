<?php

require_once __DIR__ . '/HospitalBedInventoryModel.php';

class ResourcesModel
{
    private ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
        } catch (Throwable $e) {
            error_log('[ResourcesModel] Database connection failed: ' . $e->getMessage());
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
                'fleet' => [],
                'alerts' => [],
            ];
        }

        try {
            $hospitalStatement = $this->db->prepare('SELECT available_beds, total_beds FROM hospitals WHERE id = :hospital_id LIMIT 1');
            $hospitalStatement->execute(['hospital_id' => $hospitalId]);
            $hospital = $hospitalStatement->fetch();
            if (!$hospital) {
                return ['summary' => [], 'cards' => [], 'fleet' => [], 'alerts' => []];
            }

            $this->syncFacilityStatus($hospitalId);
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
                'ambulance_bay' => $this->sumVehicleFleet($vehicles, 'Ambulance'),
                'ptv' => $this->sumVehicleFleet($vehicles, 'PTV'),
            ];
            $metricDefinitions = [
                'bed_capacity' => ['name' => 'Bed Capacity', 'type' => 'beds', 'label' => 'Available Beds', 'unit' => 'beds'],
                'ambulance_bay' => ['name' => 'Ambulance Bay', 'type' => 'ambulances', 'label' => 'Ambulance Bay', 'unit' => 'vehicles'],
                'ptv' => ['name' => 'PTV Units', 'type' => 'ptv', 'label' => 'PTV Units', 'unit' => 'vehicles'],
            ];
            $resourceCards = [];
            $summaryCards = [];

            foreach ($metricDefinitions as $metricKey => $definition) {
                $metric = $facilityMetrics[$metricKey] ?? null;
                $current = (int) ($metric['current_value'] ?? 0);
                $maximum = (int) ($metric['max_value'] ?? 0);
                $status = $this->resolveMetricStatus($current, $maximum);
                $ratio = $maximum > 0 ? ($current / $maximum) : 0;
                $badge = ucfirst($status === 'available' ? 'stable' : $status);
                $meta = $maximum > 0
                    ? round($ratio * 100) . '% of capacity available'
                    : 'Capacity not configured';

                if ($metricKey !== 'bed_capacity') {
                    $counts = $vehicleCounts[$metricKey];
                    $current = $counts['available'];
                    $maximum = $counts['total'];
                    $status = $this->resolveMetricStatus($current, $maximum);
                    $badge = ucfirst($status === 'available' ? 'stable' : $status);
                    $ratio = $maximum > 0 ? ($current / $maximum) : 0;
                    $meta = $maximum > 0
                        ? $counts['en_route'] . ' en route, ' . $counts['arrived'] . ' arrived, ' . $counts['offline'] . ' offline'
                        : 'No vehicles registered';
                }

                $resourceCards[] = [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'metricKey' => $metricKey,
                    'status' => $status,
                    'current' => $current,
                    'maximum' => $maximum,
                    'configured' => $metric !== null,
                    'primary' => $maximum > 0 || $metricKey === 'bed_capacity' ? $current . ' available' : 'No units registered',
                    'secondary' => $maximum > 0 ? $maximum . ' total ' . $definition['unit'] : ($metricKey === 'bed_capacity' ? '0 total beds' : 'Add a vehicle to register capacity'),
                    'meta' => $meta,
                    'badge' => $badge,
                ];
                $summaryCards[] = [
                    'label' => $definition['label'],
                    'value' => $maximum > 0 || $metricKey === 'bed_capacity' ? $current . ' / ' . $maximum : '0 / 0',
                    'meta' => $meta,
                    'status' => $status,
                ];
            }

            $summaryCards[] = [
                'label' => 'Fleet Status',
                'value' => $this->resolveFleetBadge($vehicles),
                'meta' => count($vehicles) . ' total vehicles assigned',
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
                $updatedAt = $facilityMetrics[$card['metricKey']]['updated_at'] ?? null;
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
                'fleet' => $fleet,
                'alerts' => $alerts ?: [
                    [
                        'name' => 'Resource status',
                        'status' => 'available',
                        'statusLabel' => 'Stable',
                        'message' => 'All monitored resources are within normal operating thresholds.',
                    ],
                ],
            ];
        } catch (Throwable $e) {
            error_log('[ResourcesModel] Resource summary query failed: ' . $e->getMessage());
            return [
                'summary' => [],
                'cards' => [],
                'fleet' => [],
                'alerts' => [],
            ];
        }
    }

    public function adjustAvailableBeds(int $hospitalId, int $change): void
    {
        if (!$this->db || $hospitalId <= 0) {
            throw new InvalidArgumentException('Invalid bed capacity change.');
        }

        HospitalBedInventoryModel::adjustAvailability($this->db, $hospitalId, $change);
    }

    public function updateBedMaximum(int $hospitalId, int $newMaximum): void
    {
        if (!$this->db || $hospitalId <= 0 || $newMaximum < 0 || $newMaximum > 10000) {
            throw new InvalidArgumentException('Enter a valid maximum bed capacity.');
        }

        HospitalBedInventoryModel::resize($this->db, $hospitalId, $newMaximum);
    }

    public function saveVehicle(int $hospitalId, ?int $vehicleId, string $vehicleType, string $callSign, string $plateNumber, string $driverName, string $status): void
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
        if (!in_array($status, ['available', 'en_route', 'arrived', 'offline'], true)) {
            throw new InvalidArgumentException('Choose a valid vehicle status.');
        }

        if ($vehicleId === null) {
            $statement = $this->db->prepare("INSERT INTO ambulances
                (call_sign, hospital_id, status, vehicle_type, driver_name, plate_number)
                VALUES (:call_sign, :hospital_id, :status, :vehicle_type, :driver_name, :plate_number)");
        } else {
            $statement = $this->db->prepare("UPDATE ambulances
                SET call_sign = :call_sign, status = :status, vehicle_type = :vehicle_type,
                    driver_name = :driver_name, plate_number = :plate_number
                WHERE id = :vehicle_id AND hospital_id = :hospital_id");
        }

        $values = [
            'call_sign' => $callSign,
            'status' => $status,
            'vehicle_type' => $vehicleType,
            'driver_name' => $driverName,
            'plate_number' => $plateNumber,
            'hospital_id' => $hospitalId,
        ];
        if ($vehicleId !== null) {
            $values['vehicle_id'] = $vehicleId;
        }
        $statement->execute($values);
        if ($vehicleId !== null && $statement->rowCount() === 0) {
            $check = $this->db->prepare('SELECT 1 FROM ambulances WHERE id = :vehicle_id AND hospital_id = :hospital_id');
            $check->execute(['vehicle_id' => $vehicleId, 'hospital_id' => $hospitalId]);
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('Vehicle record was not found.');
            }
        }
    }

    public function deleteVehicle(int $hospitalId, int $vehicleId): void
    {
        if (!$this->db || $hospitalId <= 0 || $vehicleId <= 0) {
            throw new InvalidArgumentException('Invalid vehicle record.');
        }

        $statement = $this->db->prepare('DELETE FROM ambulances WHERE id = :vehicle_id AND hospital_id = :hospital_id');
        $statement->execute(['vehicle_id' => $vehicleId, 'hospital_id' => $hospitalId]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Vehicle record was not found.');
        }
    }

    private function syncFacilityStatus(int $hospitalId): void
    {
        $statement = $this->db->prepare("INSERT INTO facility_status
            (hospital_id, metric_key, current_value, max_value, status_label)
            SELECT :bed_hospital_id, 'bed_capacity', h.available_beds, h.total_beds, NULL
                FROM hospitals h WHERE h.id = :bed_hospital_filter
            UNION ALL
            SELECT :ambulance_hospital_id, 'ambulance_bay',
                COALESCE(SUM(a.status = 'available'), 0), COUNT(a.id),
                IF(COUNT(a.id) = 0, 'No vehicles configured', NULL)
                FROM ambulances a
                WHERE a.hospital_id = :ambulance_hospital_filter AND a.vehicle_type = 'Ambulance'
            UNION ALL
            SELECT :ptv_hospital_id, 'ptv',
                COALESCE(SUM(a.status = 'available'), 0), COUNT(a.id),
                IF(COUNT(a.id) = 0, 'No vehicles configured', NULL)
                FROM ambulances a
                WHERE a.hospital_id = :ptv_hospital_filter AND a.vehicle_type = 'PTV'
            ON DUPLICATE KEY UPDATE
                current_value = VALUES(current_value),
                max_value = VALUES(max_value),
                status_label = VALUES(status_label)");
        $statement->execute([
            'bed_hospital_id' => $hospitalId,
            'bed_hospital_filter' => $hospitalId,
            'ambulance_hospital_id' => $hospitalId,
            'ambulance_hospital_filter' => $hospitalId,
            'ptv_hospital_id' => $hospitalId,
            'ptv_hospital_filter' => $hospitalId,
        ]);
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

    private function sumVehicleFleet(array $vehicles, string $vehicleType): array
    {
        $total = 0;
        $available = 0;
        $enRoute = 0;
        $arrived = 0;
        $offline = 0;

        foreach ($vehicles as $vehicle) {
            if (($vehicle['vehicle_type'] ?? '') !== $vehicleType) {
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
