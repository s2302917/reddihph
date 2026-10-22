<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

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

    /** Returns the closest barangay of the hospital that has coordinates, as [id, name]. */
    public static function nearestBarangay(int $hospitalId, float $latitude, float $longitude): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, ' . self::DISTANCE_SQL . ' AS distance_km
             FROM barangays
             WHERE hospital_id = :hospital_id AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY distance_km
             LIMIT 1'
        );
        $stmt->execute(['lat1' => $latitude, 'lat2' => $latitude, 'lng' => $longitude, 'hospital_id' => $hospitalId]);
        $row = $stmt->fetch();

        return $row ? ['id' => (int) $row['id'], 'name' => (string) $row['name']] : null;
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
                     AND i.status IN ('critical', 'urgent', 'stable', 'dispatched'))
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
                     AND i.status IN ('critical', 'urgent', 'stable', 'dispatched'))
             ORDER BY u.last_login_at DESC, u.id
             LIMIT 1"
        );
        $stmt->execute(['hospital_id' => $hospitalId, 'reporter_id' => $reporterId ?? 0]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
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
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.full_name, u.barangay_id, u.hospital_id,
                    b.name AS barangay_name,
                    p.latitude, p.longitude,
                    CASE WHEN p.latitude IS NULL OR p.longitude IS NULL THEN NULL
                         ELSE 6371 * ACOS(LEAST(1,
                             COS(RADIANS(:lat1)) * COS(RADIANS(p.latitude))
                             * COS(RADIANS(p.longitude) - RADIANS(:lng))
                             + SIN(RADIANS(:lat2)) * SIN(RADIANS(p.latitude))))
                    END AS distance_km
             FROM user_type u
             JOIN responder_presence p ON p.user_id = u.id
             LEFT JOIN barangays b ON b.id = u.barangay_id
             WHERE u.role = \'responder\'
               AND u.id <> :reporter_id
               AND p.last_seen_at >= NOW() - INTERVAL 90 SECOND
               AND NOT EXISTS (
                   SELECT 1 FROM incidents i
                   WHERE i.status IN (\'critical\', \'urgent\', \'stable\', \'dispatched\')
                     AND (i.assigned_responder_user_id = u.id
                          OR (i.assigned_responder_user_id IS NULL
                              AND i.responder_name = u.full_name))
               )
             ORDER BY (u.barangay_id = :barangay_id) DESC,
                      distance_km IS NULL,
                      distance_km,
                      p.last_seen_at DESC,
                      u.id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            'reporter_id' => $reporterId ?? 0,
            'barangay_id' => $barangayId ?? 0,
            'lat1' => $latitude,
            'lat2' => $latitude,
            'lng' => $longitude,
        ]);
        $row = $stmt->fetch();

        return $row ? [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'barangay_id' => $row['barangay_id'] !== null ? (int) $row['barangay_id'] : null,
            'hospital_id' => $row['hospital_id'] !== null ? (int) $row['hospital_id'] : null,
            'barangay_name' => $row['barangay_name'],
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
        ] : null;
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
