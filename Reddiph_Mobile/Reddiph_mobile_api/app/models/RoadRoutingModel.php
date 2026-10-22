<?php

declare(strict_types=1);

if (!class_exists('Database', false)) {
    require_once __DIR__ . '/../../config/config.php';
}

final class RoadRoutingModel
{
    private const MAX_VISITED_NODES = 100000;

    public static function route(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): array {
        return self::routeWithConnection(
            Database::connection(),
            $fromLatitude,
            $fromLongitude,
            $toLatitude,
            $toLongitude
        );
    }

    public static function routeWithConnection(
        PDO $pdo,
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
        bool $includeDetails = true
    ): array {
        $start = self::nearestNode($pdo, $fromLatitude, $fromLongitude);
        $target = self::nearestNode($pdo, $toLatitude, $toLongitude);
        if ($start === null || $target === null) {
            throw new RuntimeException('The local road network does not cover this location.');
        }

        $queue = new SplPriorityQueue();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $queue->insert($start['id'], -self::heuristic($start, $target));
        $costs = [$start['id'] => 0.0];
        $previous = [];
        $visited = [];
        $edges = $pdo->prepare(
            'SELECT e.to_node_id, e.distance_m, e.road_name, e.road_class,
                    nf.latitude AS from_latitude, nf.longitude AS from_longitude,
                    n.latitude AS to_latitude, n.longitude AS to_longitude
             FROM road_route_edges e
             INNER JOIN road_route_nodes nf ON nf.node_id = e.from_node_id
             INNER JOIN road_route_nodes n ON n.node_id = e.to_node_id
             WHERE e.from_node_id = ?'
        );
        $iterations = 0;

        while (!$queue->isEmpty() && $iterations < self::MAX_VISITED_NODES) {
            $currentId = (int) $queue->extract();
            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;
            $iterations++;
            if ($currentId === $target['id']) {
                break;
            }

            $edges->execute([$currentId]);
            foreach ($edges->fetchAll() as $edge) {
                $nextId = (int) $edge['to_node_id'];
                if (isset($visited[$nextId])) {
                    continue;
                }
                $next = [
                    'id' => $nextId,
                    'latitude' => (float) $edge['to_latitude'],
                    'longitude' => (float) $edge['to_longitude'],
                ];
                $nextCost = $costs[$currentId] + (float) $edge['distance_m'];
                if ($nextCost >= ($costs[$nextId] ?? INF)) {
                    continue;
                }

                $costs[$nextId] = $nextCost;
                $previous[$nextId] = [
                    'from' => $currentId,
                    'edge' => [
                        'from' => [
                            'id' => $currentId,
                            'latitude' => (float) $edge['from_latitude'],
                            'longitude' => (float) $edge['from_longitude'],
                        ],
                        'to' => $next,
                        'distance_m' => (float) $edge['distance_m'],
                        'road_name' => $edge['road_name'] !== null
                            ? (string) $edge['road_name']
                            : '',
                        'road_class' => (string) $edge['road_class'],
                    ],
                ];
                $priority = $nextCost + self::heuristic($next, $target);
                $queue->insert($nextId, -$priority);
            }
        }

        if (!isset($costs[$target['id']])) {
            if ($iterations >= self::MAX_VISITED_NODES) {
                throw new RuntimeException('The route search limit was reached. Try again from a nearby road.');
            }
            throw new RuntimeException('No connected road route was found to the hospital.');
        }

        $pathEdges = [];
        $cursor = $target['id'];
        while ($cursor !== $start['id']) {
            $step = $previous[$cursor] ?? null;
            if ($step === null) {
                throw new RuntimeException('The road route could not be reconstructed.');
            }
            $pathEdges[] = $step['edge'];
            $cursor = $step['from'];
        }
        $pathEdges = array_reverse($pathEdges);

        $geometry = [
            ['latitude' => $fromLatitude, 'longitude' => $fromLongitude],
        ];
        if ($pathEdges !== []) {
            $geometry[] = [
                'latitude' => $pathEdges[0]['from']['latitude'],
                'longitude' => $pathEdges[0]['from']['longitude'],
            ];
        }
        foreach ($pathEdges as $edge) {
            $geometry[] = [
                'latitude' => $edge['to']['latitude'],
                'longitude' => $edge['to']['longitude'],
            ];
        }
        $geometry[] = ['latitude' => $toLatitude, 'longitude' => $toLongitude];

        $accessDistanceM = self::distanceKm(
            $fromLatitude,
            $fromLongitude,
            $start['latitude'],
            $start['longitude']
        ) * 1000;
        $exitDistanceM = self::distanceKm(
            $target['latitude'],
            $target['longitude'],
            $toLatitude,
            $toLongitude
        ) * 1000;
        $distanceM = $accessDistanceM + $costs[$target['id']] + $exitDistanceM;

        return [
            'distance_m' => (int) round($distanceM),
            'eta_minutes' => self::estimateEtaMinutes(
                $pathEdges,
                $distanceM,
                $accessDistanceM + $exitDistanceM
            ),
            'geometry' => $geometry,
            'map_roads' => $includeDetails ? self::mapRoads($pdo, $pathEdges) : [],
            'instructions' => $includeDetails ? self::instructions($pathEdges, $distanceM) : [],
            'visited_nodes' => $iterations,
            'traffic_aware' => false,
            'traffic_delay_seconds' => null,
        ];
    }

    public static function hospitalForIncident(int $incidentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT h.name, h.latitude, h.longitude, i.transport_name
             FROM incidents i
             LEFT JOIN referrals latest_referral ON latest_referral.id = (
                 SELECT r.id FROM referrals r
                 WHERE r.incident_id = i.incident_id
                 ORDER BY r.created_at DESC, r.id DESC
                 LIMIT 1
             ) AND latest_referral.status IN (\'pending\', \'accepted\')
             LEFT JOIN hospitals referred_hospital
                ON referred_hospital.id = latest_referral.receiving_hospital_id
             INNER JOIN hospitals h ON h.id = COALESCE(referred_hospital.id, i.hospital_id)
             WHERE i.incident_id = ? LIMIT 1'
        );
        $stmt->execute([$incidentId]);
        $hospital = $stmt->fetch();
        if ($hospital === false
            || $hospital['latitude'] === null
            || $hospital['longitude'] === null) {
            return null;
        }

        return [
            'name' => (string) $hospital['name'],
            'latitude' => (float) $hospital['latitude'],
            'longitude' => (float) $hospital['longitude'],
            'transport_assigned' => trim((string) ($hospital['transport_name'] ?? '')) !== '',
        ];
    }

    /** Returns server-verified responder and incident coordinates for the reporter's live map. */
    public static function responderRouteForReporter(int $incidentId, int $reporterId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT i.location, i.hospital_sent_at,
                    i.latitude AS incident_latitude,
                    i.longitude AS incident_longitude,
                    p.latitude AS responder_latitude,
                    p.longitude AS responder_longitude,
                    h.name AS hospital_name,
                    h.latitude AS hospital_latitude,
                    h.longitude AS hospital_longitude
             FROM incidents i
             LEFT JOIN referrals active_referral ON active_referral.id = (
                 SELECT r.id
                 FROM referrals r
                 WHERE r.incident_id = i.incident_id
                   AND r.status IN ('pending', 'accepted')
                 ORDER BY r.created_at DESC, r.id DESC
                 LIMIT 1
             )
             LEFT JOIN hospitals h
                ON h.id = COALESCE(active_referral.receiving_hospital_id, i.hospital_id)
             LEFT JOIN user_type u
                ON (u.id = i.assigned_responder_user_id
                    OR (i.assigned_responder_user_id IS NULL
                        AND u.role = 'responder'
                        AND u.hospital_id <=> i.hospital_id
                        AND u.full_name = i.responder_name))
             LEFT JOIN responder_presence p
                ON p.user_id = u.id
               AND p.last_seen_at >= NOW() - INTERVAL 90 SECOND
             WHERE i.incident_id = ? AND i.reporter_user_id = ?
               AND i.status IN ('critical', 'urgent', 'stable', 'dispatched')
             ORDER BY p.last_seen_at DESC
             LIMIT 1"
        );
        $stmt->execute([$incidentId, $reporterId]);
        $row = $stmt->fetch();
        if ($row === false || $row['incident_latitude'] === null
            || $row['incident_longitude'] === null) {
            return null;
        }

        if ($row['hospital_sent_at'] !== null) {
            if ($row['hospital_name'] === null
                || $row['hospital_latitude'] === null
                || $row['hospital_longitude'] === null
                || $row['responder_latitude'] === null
                || $row['responder_longitude'] === null) {
                return null;
            }

            return [
                'origin' => [
                    'latitude' => (float) $row['responder_latitude'],
                    'longitude' => (float) $row['responder_longitude'],
                ],
                'destination' => [
                    'name' => (string) $row['hospital_name'],
                    'latitude' => (float) $row['hospital_latitude'],
                    'longitude' => (float) $row['hospital_longitude'],
                ],
            ];
        }

        if ($row['responder_latitude'] === null || $row['responder_longitude'] === null) {
            return null;
        }

        return [
            'origin' => [
                'latitude' => (float) $row['responder_latitude'],
                'longitude' => (float) $row['responder_longitude'],
            ],
            'destination' => [
                'name' => (string) $row['location'],
                'latitude' => (float) $row['incident_latitude'],
                'longitude' => (float) $row['incident_longitude'],
            ],
        ];
    }

    public static function belongsToReporter(int $incidentId, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM incidents
             WHERE incident_id = ? AND reporter_user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$incidentId, $userId]);

        return $stmt->fetchColumn() !== false;
    }

    private static function nearestNode(PDO $pdo, float $latitude, float $longitude): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT node_id, latitude, longitude
             FROM road_route_nodes
             WHERE latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?
             ORDER BY POW(latitude - ?, 2)
                    + POW((longitude - ?) * COS(RADIANS(?)), 2)
             LIMIT 1'
        );
        foreach ([0.01, 0.03, 0.08] as $radius) {
            $stmt->execute([
                $latitude - $radius,
                $latitude + $radius,
                $longitude - $radius,
                $longitude + $radius,
                $latitude,
                $longitude,
                $latitude,
            ]);
            $node = $stmt->fetch();
            if ($node !== false) {
                return [
                    'id' => (int) $node['node_id'],
                    'latitude' => (float) $node['latitude'],
                    'longitude' => (float) $node['longitude'],
                ];
            }
        }

        return null;
    }

    private static function mapRoads(PDO $pdo, array $pathEdges): array
    {
        if ($pathEdges === []) {
            return [];
        }

        $sampleEvery = max(1, (int) ceil(count($pathEdges) / 40));
        $nodeIds = [];
        foreach ($pathEdges as $index => $edge) {
            if ($index % $sampleEvery === 0) {
                $nodeIds[$edge['from']['id']] = true;
                $nodeIds[$edge['to']['id']] = true;
            }
        }

        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT DISTINCT nf.latitude AS from_latitude, nf.longitude AS from_longitude,
                    nt.latitude AS to_latitude, nt.longitude AS to_longitude
             FROM road_route_edges e
             INNER JOIN road_route_nodes nf ON nf.node_id = e.from_node_id
             INNER JOIN road_route_nodes nt ON nt.node_id = e.to_node_id
             WHERE e.from_node_id IN (' . $placeholders . ')
                OR e.to_node_id IN (' . $placeholders . ')
             LIMIT 1200'
        );
        $ids = array_map('intval', array_keys($nodeIds));
        $stmt->execute(array_merge($ids, $ids));

        return array_map(
            static fn (array $road): array => [
                'from' => [
                    'latitude' => (float) $road['from_latitude'],
                    'longitude' => (float) $road['from_longitude'],
                ],
                'to' => [
                    'latitude' => (float) $road['to_latitude'],
                    'longitude' => (float) $road['to_longitude'],
                ],
            ],
            $stmt->fetchAll()
        );
    }

    private static function heuristic(array $from, array $to): float
    {
        return self::distanceKm(
            $from['latitude'],
            $from['longitude'],
            $to['latitude'],
            $to['longitude']
        ) * 1000;
    }

    private static function distanceKm(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function estimateEtaMinutes(
        array $edges,
        float $distanceM,
        float $connectorDistanceM
    ): int
    {
        $seconds = $connectorDistanceM / (20 * 1000 / 3600);
        $speeds = [
            'motorway' => 60,
            'trunk' => 50,
            'primary' => 45,
            'secondary' => 35,
            'tertiary' => 30,
            'unclassified' => 25,
            'residential' => 20,
            'living_street' => 15,
            'service' => 10,
        ];
        foreach ($edges as $edge) {
            $speedKmh = $speeds[$edge['road_class']] ?? 20;
            $seconds += $edge['distance_m'] / ($speedKmh * 1000 / 3600);
        }
        if ($edges === []) {
            $seconds = $distanceM / (20 * 1000 / 3600);
        }

        return max(1, (int) ceil($seconds / 60));
    }

    private static function instructions(array $edges, float $distanceM): array
    {
        if ($edges === []) {
            return [
                ['text' => 'Continue to the hospital', 'distance_m' => (int) round($distanceM)],
                ['text' => 'Arrive at the hospital', 'distance_m' => 0],
            ];
        }

        $steps = [];
        $group = $edges[0];
        $groupDistance = 0.0;
        $previousBearing = null;
        foreach ($edges as $index => $edge) {
            $bearing = self::bearing($edge['from']['latitude'], $edge['from']['longitude'],
                $edge['to']['latitude'], $edge['to']['longitude']);
            $turn = $previousBearing === null ? null : self::turn($previousBearing, $bearing);
            $sameRoad = $edge['road_name'] === $group['road_name'];
            if ($index > 0 && (!$sameRoad || $turn !== null)) {
                $steps[] = self::instruction($groupDistance, $turn, $edge['road_name']);
                $group = $edge;
                $groupDistance = 0.0;
            }
            $groupDistance += $edge['distance_m'];
            $previousBearing = $bearing;
        }
        $steps[] = self::instruction($groupDistance, null, $group['road_name']);
        $steps[] = ['text' => 'Arrive at the hospital', 'distance_m' => 0];

        return $steps;
    }

    private static function instruction(
        float $distanceM,
        ?string $turn,
        string $nextRoad
    ): array
    {
        $road = $nextRoad !== '' ? ' onto ' . $nextRoad : '';
        $text = ($turn ?? 'Continue') . $road;

        return ['text' => $text, 'distance_m' => (int) round($distanceM)];
    }

    private static function bearing(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): float {
        $fromLat = deg2rad($fromLatitude);
        $toLat = deg2rad($toLatitude);
        $deltaLongitude = deg2rad($toLongitude - $fromLongitude);
        $y = sin($deltaLongitude) * cos($toLat);
        $x = cos($fromLat) * sin($toLat)
            - sin($fromLat) * cos($toLat) * cos($deltaLongitude);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    private static function turn(float $from, float $to): ?string
    {
        $angle = fmod($to - $from + 540, 360) - 180;
        if (abs($angle) < 35) {
            return null;
        }
        if (abs($angle) > 150) {
            return 'Make a U-turn';
        }

        return $angle > 0 ? 'Turn right' : 'Turn left';
    }
}
