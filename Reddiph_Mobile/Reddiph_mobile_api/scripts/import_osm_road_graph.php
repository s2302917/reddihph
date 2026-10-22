<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';

if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP cURL is required to import the local road graph.\n");
    exit(1);
}

$endpoints = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass.nchc.org.tw/api/interpreter',
];
$pdo = Database::connection();
$nodeInsert = $pdo->prepare(
    'INSERT IGNORE INTO road_route_nodes (node_id, latitude, longitude)
     VALUES (?, ?, ?)'
);
$edgeInsert = $pdo->prepare(
    'INSERT IGNORE INTO road_route_edges
        (from_node_id, to_node_id, distance_m, road_name, road_class)
     VALUES (?, ?, ?, ?, ?)'
);
$nodeCount = 0;
$edgeCount = 0;

$tileHeight = 0.35;
$tileWidth = 0.25;
// This padded rectangle covers Negros Occidental and nearby connecting roads.
$southBound = 8.80;
$northBound = 10.95;
$westBound = 122.20;
$eastBound = 123.35;
$tileTotal = (int) ceil(($northBound - $southBound) / $tileHeight)
    * (int) ceil(($eastBound - $westBound) / $tileWidth);
$tileNumber = 0;

try {
    for ($south = $southBound; $south < $northBound; $south += $tileHeight) {
        $north = min($south + $tileHeight, $northBound);
        for ($west = $westBound; $west < $eastBound; $west += $tileWidth) {
            $east = min($west + $tileWidth, $eastBound);
            $tileNumber++;
            $bounds = sprintf('(%.5f,%.5f,%.5f,%.5f)', $south, $west, $north, $east);
            $query = '[out:json][timeout:180];'
                . 'way["highway"~"^(motorway|trunk|primary|secondary|tertiary|unclassified|residential|living_street|service)$"]'
                . '["access"!="no"]["motor_vehicle"!="no"]' . $bounds . ';out geom;';

            $ways = downloadRoadWays($query, $endpoints);
            $pdo->beginTransaction();
            [$tileNodes, $tileEdges] = importRoadWays(
                $ways,
                $nodeInsert,
                $edgeInsert
            );
            $pdo->commit();
            $nodeCount += $tileNodes;
            $edgeCount += $tileEdges;
            fwrite(STDOUT, sprintf(
                "Imported tile %d/%d: %d nodes and %d directed road segments.\n",
                $tileNumber,
                $tileTotal,
                $tileNodes,
                $tileEdges
            ));
            sleep(1);
        }
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Road graph import stopped at tile ' . $tileNumber
        . '/' . $tileTotal . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Road graph import complete: %d nodes and %d directed road segments added. "
    . "Map data: © OpenStreetMap contributors (ODbL).\n",
    $nodeCount,
    $edgeCount
));

function downloadRoadWays(string $query, array $endpoints): array
{
    $downloadErrors = [];
    foreach ($endpoints as $endpoint) {
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_USERAGENT => 'Reddiph/1.0 (private road graph importer)',
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (is_string($body) && $status >= 200 && $status < 300) {
            try {
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                $reason = 'invalid JSON response';
            }
            if (isset($data['elements']) && is_array($data['elements'])) {
                return $data['elements'];
            }
            $reason ??= 'invalid road data response';
        } else {
            $reason = $curlError !== '' ? $curlError : 'HTTP ' . $status;
        }

        $downloadErrors[] = $endpoint . ': ' . $reason;
        fwrite(STDERR, 'OpenStreetMap download failed at ' . $endpoint
            . ' (' . $reason . '); trying the next server.' . PHP_EOL);
        unset($data, $reason);
    }

    throw new RuntimeException(
        "OpenStreetMap road data download failed on all servers:\n"
        . implode(PHP_EOL, $downloadErrors)
    );
}

function importRoadWays(
    array $ways,
    PDOStatement $nodeInsert,
    PDOStatement $edgeInsert
): array {
    $nodeCount = 0;
    $edgeCount = 0;
    foreach ($ways as $way) {
        if (!is_array($way)
            || !is_array($way['geometry'] ?? null)
            || !is_array($way['nodes'] ?? null)
            || count($way['geometry']) !== count($way['nodes'])
            || count($way['nodes']) < 2
            || !is_array($way['tags'] ?? null)) {
            continue;
        }

        $geometry = $way['geometry'];
        $nodeIds = $way['nodes'];
        foreach ($geometry as $index => $point) {
            if (!isset($nodeIds[$index], $point['lat'], $point['lon'])) {
                continue 2;
            }
            $nodeInsert->execute([
                (int) $nodeIds[$index],
                (float) $point['lat'],
                (float) $point['lon'],
            ]);
            $nodeCount += $nodeInsert->rowCount();
        }

        $tags = $way['tags'];
        $roadClass = (string) ($tags['highway'] ?? '');
        $roadName = substr(trim((string) ($tags['name'] ?? '')), 0, 255);
        $oneWay = strtolower((string) ($tags['oneway'] ?? ''));
        $reverseOnly = $oneWay === '-1';
        $forwardOnly = in_array($oneWay, ['yes', 'true', '1'], true)
            || ($tags['junction'] ?? '') === 'roundabout';

        for ($index = 0; $index < count($nodeIds) - 1; $index++) {
            $from = $geometry[$index];
            $to = $geometry[$index + 1];
            $distance = distanceMeters(
                (float) $from['lat'],
                (float) $from['lon'],
                (float) $to['lat'],
                (float) $to['lon']
            );
            if ($distance <= 0 || $distance > 5000) {
                continue;
            }

            $fromId = (int) $nodeIds[$index];
            $toId = (int) $nodeIds[$index + 1];
            $edgeInsert->execute([
                $reverseOnly ? $toId : $fromId,
                $reverseOnly ? $fromId : $toId,
                $distance,
                $roadName,
                $roadClass,
            ]);
            $edgeCount += $edgeInsert->rowCount();
            if (!$forwardOnly && !$reverseOnly) {
                $edgeInsert->execute([$toId, $fromId, $distance, $roadName, $roadClass]);
                $edgeCount += $edgeInsert->rowCount();
            }
        }
    }

    return [$nodeCount, $edgeCount];
}

function distanceMeters(
    float $latitude1,
    float $longitude1,
    float $latitude2,
    float $longitude2
): float {
    $latitudeDelta = deg2rad($latitude2 - $latitude1);
    $longitudeDelta = deg2rad($longitude2 - $longitude1);
    $a = sin($latitudeDelta / 2) ** 2
        + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2))
        * sin($longitudeDelta / 2) ** 2;

    return 6371000 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
}
