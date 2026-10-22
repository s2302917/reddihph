<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/DispatchModel.php';

/**
 * Reads for the responder home page: the responder's own profile and vehicle, the incident
 * they are assigned to, and the open incidents of their hospital's barangays.
 */
final class ResponderDashboardModel
{
    public const PAGE_SIZE = 5;
    private const MAP_LIMIT = 200;

    private const OPEN_STATUSES = "'critical', 'urgent', 'stable', 'dispatched'";
    private const INCIDENT_COLUMNS = 'i.incident_id, i.incident_type, i.status, i.location, i.latitude, i.longitude,
        (i.hospital_sent_at IS NOT NULL) AS sent_to_hospital,
        i.reported_by, i.reporter_user_id, reporter.role AS reporter_role,
        COALESCE(
            (SELECT assigned_responder.full_name
             FROM user_type assigned_responder
             WHERE assigned_responder.id = i.assigned_responder_user_id),
            i.responder_name
        ) AS responder_name,
        i.patient_name, i.patient_age, i.patient_gender,
        i.eta_minutes, i.transport_name, i.transport_type,
        b.name AS barangay_name, TIMESTAMPDIFF(MINUTE, i.reported_at, NOW()) AS minutes_ago';

    /**
     * Name, hospital and, when the responder is the driver of a vehicle, that vehicle.
     * Returns null when the id is not a responder.
     */
    public static function profile(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.full_name, u.hospital_id, u.barangay_id, a.call_sign, a.vehicle_type, a.plate_number
             FROM user_type u
             LEFT JOIN ambulances a ON a.hospital_id = u.hospital_id AND a.driver_name = u.full_name
             WHERE u.id = ? AND u.role = 'responder'
             ORDER BY a.id
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'full_name' => (string) $row['full_name'],
            'hospital_id' => $row['hospital_id'] !== null ? (int) $row['hospital_id'] : null,
            'barangay_id' => $row['barangay_id'] !== null ? (int) $row['barangay_id'] : null,
            'vehicle' => $row['call_sign'] === null ? null : [
                'call_sign' => $row['call_sign'],
                'vehicle_type' => $row['vehicle_type'],
                'plate_number' => $row['plate_number'],
            ],
        ];
    }

    /** Records a short-lived mobile presence heartbeat for dispatch selection. */
    public static function updatePresence(int $userId, ?array $origin): void
    {
        $latitude = $origin[0] ?? null;
        $longitude = $origin[1] ?? null;
        Database::connection()->prepare(
            'INSERT INTO responder_presence (user_id, latitude, longitude, last_seen_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                last_seen_at = VALUES(last_seen_at)'
        )->execute([$userId, $latitude, $longitude]);
    }

    /** The latest open incident assigned to the responder, or null. */
    public static function activeCase(
        int $responderUserId,
        string $responderName,
        ?int $hospitalId
    ): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ' . self::INCIDENT_COLUMNS . '
             FROM incidents i
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             WHERE (i.assigned_responder_user_id = :user_id
                    OR (i.assigned_responder_user_id IS NULL
                        AND i.responder_name = :name
                        AND i.hospital_id <=> :hospital_id))
               AND i.status IN (' . self::OPEN_STATUSES . ')
             ORDER BY i.reported_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $responderUserId,
            'name' => $responderName,
            'hospital_id' => $hospitalId,
        ]);

        $row = $stmt->fetch();

        return $row ? self::incidentRow($row) : null;
    }

    /**
     * One page of open incidents of the hospital, newest first, leaving out the responder's
     * own active case. Returns [rows, total].
     */
    public static function queue(
        ?int $barangayId,
        ?int $hospitalId,
        ?int $excludeIncidentId,
        int $responderUserId,
        int $page
    ): array
    {
        if ($barangayId === null && $hospitalId === null) {
            return [[], 0];
        }

        $pdo = Database::connection();
        $scope = $barangayId !== null
            ? '(i.barangay_id = :barangay_id OR
                (i.hospital_id = :hospital_id AND i.status = \'dispatched\'))'
            : 'i.hospital_id = :hospital_id';
        $where = $scope . ' AND i.status IN (' . self::OPEN_STATUSES . ')
                  AND i.incident_id <> :exclude_id
                  AND (i.assigned_responder_user_id IS NULL
                       OR i.assigned_responder_user_id = :responder_user_id)';
        $params = [
            'hospital_id' => $hospitalId ?? 0,
            'exclude_id' => $excludeIncidentId ?? 0,
            'responder_user_id' => $responderUserId,
        ];
        if ($barangayId !== null) {
            $params['barangay_id'] = $barangayId;
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM incidents i WHERE {$where}");
        $count->execute($params);

        // The page numbers are integers, so they are safe to place in LIMIT/OFFSET.
        $stmt = $pdo->prepare(
            'SELECT ' . self::INCIDENT_COLUMNS . "
             FROM incidents i
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             WHERE {$where}
             ORDER BY i.reported_at DESC, i.incident_id DESC
             LIMIT " . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE)
        );
        $stmt->execute($params);

        return [array_map([self::class, 'incidentRow'], $stmt->fetchAll()), (int) $count->fetchColumn()];
    }

    /**
     * One page of every incident (any status) in the responder's registered barangay, newest
     * first, optionally limited to one status. Responders without a barangay see their hospital's
     * incidents instead. Returns [rows, total].
     */
    public static function incidents(
        ?int $barangayId,
        ?int $hospitalId,
        int $responderUserId,
        string $responderName,
        ?string $status,
        int $page,
        int $size
    ): array
    {
        [$scope, $params] = self::scope($barangayId, $hospitalId);
        if ($scope === null) {
            return [[], 0];
        }
        $scope .= ' AND (i.assigned_responder_user_id = :responder_user_id
                         OR (i.assigned_responder_user_id IS NULL
                             AND (i.responder_name IS NULL
                                  OR i.responder_name = :responder_name)))';
        $params['responder_user_id'] = $responderUserId;
        $params['responder_name'] = $responderName;
        if ($status !== null) {
            $scope .= ' AND i.status = :status';
            $params['status'] = $status;
        }

        $pdo = Database::connection();
        $count = $pdo->prepare("SELECT COUNT(*) FROM incidents i WHERE {$scope}");
        $count->execute($params);

        // The page numbers are integers, so they are safe to place in LIMIT/OFFSET.
        $stmt = $pdo->prepare(
            'SELECT ' . self::INCIDENT_COLUMNS . "
             FROM incidents i
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             WHERE {$scope}
             ORDER BY i.reported_at DESC, i.incident_id DESC
             LIMIT {$size} OFFSET " . (($page - 1) * $size)
        );
        $stmt->execute($params);

        return [array_map([self::class, 'incidentRow'], $stmt->fetchAll()), (int) $count->fetchColumn()];
    }

    /**
     * Every open incident with a location that belongs on the responder's map: those of the
     * registered barangay plus the hospital's dispatched ones (the dispatch list).
     */
    public static function mapIncidents(
        ?int $barangayId,
        ?int $hospitalId,
        int $responderUserId,
        string $responderName
    ): array
    {
        $params = [
            'barangay_id' => $barangayId ?? 0,
            'hospital_id' => $hospitalId ?? 0,
            'responder_user_id' => $responderUserId,
            'responder_name' => $responderName,
        ];
        $stmt = Database::connection()->prepare(
            'SELECT ' . self::INCIDENT_COLUMNS . '
             FROM incidents i
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             WHERE i.status IN (' . self::OPEN_STATUSES . ')
               AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL
               AND (i.barangay_id = :barangay_id
                    OR (i.hospital_id = :hospital_id AND i.status = \'dispatched\'))
               AND (i.assigned_responder_user_id = :responder_user_id
                    OR (i.assigned_responder_user_id IS NULL
                        AND (i.responder_name IS NULL
                             OR i.responder_name = :responder_name)))
             ORDER BY i.reported_at DESC, i.incident_id DESC
             LIMIT ' . self::MAP_LIMIT
        );
        $stmt->execute($params);

        return array_map([self::class, 'incidentRow'], $stmt->fetchAll());
    }

    /** Incident counts per status for the same scope as incidents(), plus an 'all' total. */
    public static function statusCounts(
        ?int $barangayId,
        ?int $hospitalId,
        int $responderUserId,
        string $responderName
    ): array
    {
        $counts = ['all' => 0, 'critical' => 0, 'urgent' => 0, 'stable' => 0];
        [$scope, $params] = self::scope($barangayId, $hospitalId);
        if ($scope === null) {
            return $counts;
        }
        $scope .= ' AND (i.assigned_responder_user_id = :responder_user_id
                         OR (i.assigned_responder_user_id IS NULL
                             AND (i.responder_name IS NULL
                                  OR i.responder_name = :responder_name)))';
        $params['responder_user_id'] = $responderUserId;
        $params['responder_name'] = $responderName;

        $stmt = Database::connection()->prepare("SELECT i.status, COUNT(*) AS total FROM incidents i WHERE {$scope} GROUP BY i.status");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $counts['all'] += (int) $row['total'];
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /** WHERE clause and parameters for the responder's barangay, else hospital; null clause when neither is set. */
    private static function scope(?int $barangayId, ?int $hospitalId): array
    {
        if ($barangayId !== null) {
            return ['i.barangay_id = :barangay_id', ['barangay_id' => $barangayId]];
        }

        return $hospitalId !== null ? ['i.hospital_id = :hospital_id', ['hospital_id' => $hospitalId]] : [null, []];
    }

    private static function incidentRow(array $row): array
    {
        $row['has_reporter_account'] = $row['reporter_user_id'] !== null
            && $row['reporter_role'] === 'resident';
        unset($row['reporter_user_id'], $row['reporter_role']);

        return $row;
    }

    /** Hospital coordinates, the fallback start point when the responder's location is unknown. */
    public static function hospitalPoint(?int $hospitalId): ?array
    {
        if ($hospitalId === null) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT latitude, longitude FROM hospitals WHERE id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL'
        );
        $stmt->execute([$hospitalId]);
        $row = $stmt->fetch();

        return $row ? [(float) $row['latitude'], (float) $row['longitude']] : null;
    }
}
