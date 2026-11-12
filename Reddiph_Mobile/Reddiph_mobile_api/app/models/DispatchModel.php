<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/ResponderProfileModel.php';

/**
 * Dispatch lookups for a new SOS incident, read from the shared admin tables:
 * the barangay it falls in, the transport and responder to assign, and the first ETA.
 */
final class DispatchModel
{
    /** Great-circle distance in km from the :lat1/:lat2/:lng point to a row's latitude/longitude. */
    public const DISTANCE_SQL = '6371 * ACOS(LEAST(1,
        COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lng))
        + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))))';

    // Same road factor and speed the admin live map uses for its ETA.
    private const ROAD_FACTOR = 1.3;
    private const AVERAGE_SPEED_KMH = 35;
    private const EARTH_RADIUS_KM = 6371;
    private static array $columnCache = [];

    /** Returns the closest barangay of the hospital that has coordinates, as [id, name]. */
    public static function nearestBarangay(int $hospitalId, float $latitude, float $longitude): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, ' . self::DISTANCE_SQL . ' AS distance_km
             FROM barangays
             WHERE hospital_id = :hospital_id AND latitude IS NOT NULL AND longitude IS NOT NULL
             ' . self::activeBarangayFilter() . '
             ORDER BY distance_km
             LIMIT 1'
        );
        $stmt->execute(['lat1' => $latitude, 'lat2' => $latitude, 'lng' => $longitude, 'hospital_id' => $hospitalId]);
        $row = $stmt->fetch();

        return $row ? ['id' => (int) $row['id'], 'name' => (string) $row['name']] : null;
    }

    private static function activeBarangayFilter(): string
    {
        $pdo = Database::connection();
        $conditions = [];
        foreach (['is_active', 'archived_at'] as $column) {
            if (!self::hasColumn($pdo, 'barangays', $column)) {
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

    /** Returns the user's registered barangay, independent of hospital assignment. */
    public static function barangayById(int $barangayId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name FROM barangays WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$barangayId]);
        $row = $stmt->fetch();

        return $row ? ['id' => (int) $row['id'], 'name' => (string) $row['name']] : null;
    }

    /**
     * Returns an available vehicle of the hospital that is not on another open incident,
     * as [call_sign, vehicle_type, driver_name]. Vehicles with a driver come first.
     */
    public static function availableTransport(int $hospitalId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.id, a.call_sign, a.vehicle_type, a.driver_name
             FROM ambulances a
             WHERE a.hospital_id = :hospital_id AND a.status = 'available'
               AND NOT EXISTS (
                   SELECT 1 FROM incidents i
                   WHERE (i.assigned_ambulance_id = a.id
                          OR (i.assigned_ambulance_id IS NULL
                              AND i.hospital_id = a.hospital_id
                              AND i.transport_name = a.call_sign))
                     AND i.status IN ('open', 'dispatched', 'referred'))
             ORDER BY a.driver_name IS NULL, a.id
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['hospital_id' => $hospitalId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Returns the name of the most recently signed-in responder of the hospital who is not
     * the reporter and not already on an open incident.
     */
    public static function activeResponderName(int $hospitalId, ?int $reporterId): ?string
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.full_name
             FROM user_type u
             WHERE u.role = 'responder' AND u.hospital_id = :hospital_id
               AND u.last_login_at IS NOT NULL AND u.id <> :reporter_id
               AND NOT EXISTS (
                   SELECT 1 FROM incidents i
                   WHERE i.hospital_id = u.hospital_id AND i.responder_name = u.full_name
                     AND i.status IN ('open', 'dispatched', 'referred'))
             ORDER BY u.last_login_at DESC, u.id
             LIMIT 1"
        );
        $stmt->execute(['hospital_id' => $hospitalId, 'reporter_id' => $reporterId ?? 0]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    /** Returns the nearest eligible responder and, when available, a opted-in driver. */
    public static function availableResponderPair(
        ?int $barangayId,
        ?int $reporterId,
        float $latitude,
        float $longitude
    ): ?array {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.full_name, u.barangay_id, u.hospital_id,
                    b.name AS barangay_name,
                    p.latitude, p.longitude,
                COALESCE(rp.driver_responder_enabled, 0) AS driver_responder_enabled,
                rp.vehicle_type, rp.call_sign, rp.plate_number,
                    CASE WHEN p.latitude IS NULL OR p.longitude IS NULL THEN NULL
                         ELSE 6371 * ACOS(LEAST(1,
                             COS(RADIANS(:lat1)) * COS(RADIANS(p.latitude))
                             * COS(RADIANS(p.longitude) - RADIANS(:lng))
                             + SIN(RADIANS(:lat2)) * SIN(RADIANS(p.latitude))))
                    END AS distance_km
             FROM user_type u
             JOIN responder_presence p ON p.user_id = u.id
                         LEFT JOIN responder_profiles rp ON rp.user_id = u.id
             LEFT JOIN barangays b ON b.id = u.barangay_id
             WHERE u.role = \'responder\'
                             AND u.approval_status = \'approved\'
                             AND u.account_archived_at IS NULL
                             AND (u.banned_until IS NULL OR u.banned_until <= NOW())
                             AND (u.login_disabled_reason IS NULL OR u.login_disabled_reason = \'\')
               AND u.id <> :reporter_id
               AND p.last_seen_at >= NOW() - INTERVAL 90 SECOND
                             AND ' . ResponderProfileModel::availabilityCondition() . '
               AND NOT EXISTS (
                   SELECT 1 FROM incidents i
                   WHERE i.status IN (\'open\', \'dispatched\', \'referred\')
                     AND (i.assigned_responder_user_id = u.id
                          OR i.driver_name = u.full_name
                          OR (i.assigned_responder_user_id IS NULL
                              AND i.responder_name = u.full_name))
               )
             ORDER BY (u.barangay_id = :barangay_id) DESC,
                      distance_km IS NULL,
                      distance_km,
                      p.last_seen_at DESC,
                      u.id
             LIMIT 100
             FOR UPDATE'
        );
        $stmt->execute([
            'reporter_id' => $reporterId ?? 0,
            'barangay_id' => $barangayId ?? 0,
            'lat1' => $latitude,
            'lat2' => $latitude,
            'lng' => $longitude,
        ]);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return null;
        }

        $responder = null;
        $driver = null;
        foreach ($rows as $row) {
            $candidate = [
                'id' => (int) $row['id'],
                'full_name' => (string) $row['full_name'],
                'barangay_id' => $row['barangay_id'] !== null ? (int) $row['barangay_id'] : null,
                'hospital_id' => $row['hospital_id'] !== null ? (int) $row['hospital_id'] : null,
                'barangay_name' => $row['barangay_name'],
                'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
                'driver_responder_enabled' => (bool) $row['driver_responder_enabled'],
                'vehicle_type' => $row['vehicle_type'],
                'call_sign' => $row['call_sign'],
                'plate_number' => $row['plate_number'],
            ];
            $responder ??= $candidate;
            if (
                $candidate['id'] !== $responder['id']
                && $candidate['driver_responder_enabled']
                && $candidate['vehicle_type'] !== null
                && $candidate['call_sign'] !== null
                && $candidate['plate_number'] !== null
            ) {
                $driver = $candidate;
                break;
            }
        }

        return ['responder' => $responder, 'driver' => $driver];
    }

    /**
     * Returns the nearest responder who has recently checked in from the app.
     * Responders in the reporter's registered barangay take priority.
     */
    public static function availableResponder(
        ?int $barangayId,
        ?int $reporterId,
        float $latitude,
        float $longitude
    ): ?array {
        return self::availableResponderPair($barangayId, $reporterId, $latitude, $longitude)['responder'] ?? null;
    }

    public static function assignResponderPair(PDO $pdo, int $incidentId, array $primaryResponder, array $driverResponder, ?array $transport): void
    {
        $stmt = $pdo->prepare(
            'UPDATE incidents
             SET assigned_responder_user_id = ?, responder_name = ?, assigned_ambulance_id = ?,
                 transport_name = ?, transport_type = ?, driver_name = ?,
                 eta_minutes = COALESCE(?, eta_minutes), status = CASE WHEN status = \'open\' THEN \'dispatched\' ELSE status END
             WHERE incident_id = ?'
        );
        $stmt->execute([
            $primaryResponder['id'],
            $primaryResponder['full_name'],
            $transport['id'] ?? null,
            $transport['call_sign'] ?? null,
            $transport['vehicle_type'] ?? null,
            $driverResponder['full_name'],
            null,
            $incidentId,
        ]);

        foreach ([
            ['responder_user_id' => $primaryResponder['id'], 'assignment_role' => 'responder'],
            ['responder_user_id' => $driverResponder['id'], 'assignment_role' => 'driver'],
        ] as $assignment) {
            $insert = $pdo->prepare(
                'INSERT INTO responder_alerts
                    (incident_id, responder_user_id, assignment_role, status, created_at)
                 VALUES (?, ?, ?, \'pending\', NOW())
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    created_at = NOW(),
                    acknowledged_at = NULL'
            );
            $insert->execute([
                $incidentId,
                $assignment['responder_user_id'],
                $assignment['assignment_role'],
            ]);
        }
    }

    public static function notifyVolunteerOverflow(
        PDO $pdo,
        int $incidentId,
        int $requesterUserId,
        ?int $barangayId,
        ?int $hospitalId,
        float $latitude,
        float $longitude
    ): void {
        $insert = $pdo->prepare(
            'INSERT INTO volunteer_assistances (incident_id, volunteer_user_id, requested_by_user_id, status)
             SELECT ?, p.user_id, ?, \'requested\'
             FROM volunteer_profiles p
             JOIN user_type u ON u.id = p.user_id AND u.role = \'volunteer\'
             WHERE u.approval_status = \'approved\'
               AND p.is_available = 1
               AND NOT EXISTS (
                   SELECT 1 FROM volunteer_assistances va
                   WHERE va.incident_id = ? AND va.volunteer_user_id = p.user_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM volunteer_assistances va
                   WHERE va.volunteer_user_id = p.user_id
                     AND va.status IN (\'requested\', \'accepted\')
                     AND va.incident_id <> ?
               )'
        );
        $insert->execute([$incidentId, $requesterUserId, $incidentId, $incidentId]);
    }

    /** Straight-line (great-circle) distance in km between two coordinates. */
    public static function distanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    /** First ETA in minutes for the trip from the hospital to the incident (at least 1). */
    public static function etaMinutes(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): int
    {
        $distanceKm = self::distanceKm($fromLatitude, $fromLongitude, $toLatitude, $toLongitude);

        return (int) max(1, ceil(($distanceKm * self::ROAD_FACTOR / self::AVERAGE_SPEED_KMH) * 60));
    }
}
