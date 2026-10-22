<?php

declare(strict_types=1);

putenv('DB_PASS=test-only-secret');
putenv('TOMTOM_API_KEY=test-only-tomtom-key');
require_once __DIR__ . '/../app/models/TomTomRoutingModel.php';

$response = [
    'routes' => [
        [
            'summary' => [
                'lengthInMeters' => 600,
                'travelTimeInSeconds' => 180,
                'trafficDelayInSeconds' => 30,
            ],
            'legs' => [
                [
                    'points' => [
                        ['latitude' => 10.4, 'longitude' => 122.9],
                        ['latitude' => 10.41, 'longitude' => 122.91],
                    ],
                ],
            ],
            'guidance' => [
                'instructions' => [
                    ['message' => 'Continue north', 'routeOffsetInMeters' => 0],
                    ['message' => 'Turn right', 'routeOffsetInMeters' => 350],
                ],
            ],
        ],
    ],
];

$route = TomTomRoutingModel::normalizeResponse(
    $response,
    10.399,
    122.899,
    10.42,
    122.92
);

$checks = [
    'reads the TomTom key from server configuration' =>
        Database::tomtomApiKey() === 'test-only-tomtom-key',
    'uses provider route distance' => $route['distance_m'] === 600,
    'uses traffic-aware ETA' => $route['eta_minutes'] === 3,
    'marks route as traffic-aware' => $route['traffic_aware'] === true,
    'retains route start and destination' => count($route['geometry']) === 4
        && $route['geometry'][0]['latitude'] === 10.399
        && $route['geometry'][3]['latitude'] === 10.42,
    'assigns distance to the preceding maneuver' =>
        $route['instructions'][0]['distance_m'] === 350
        && $route['instructions'][1]['distance_m'] === 250,
    'adds hospital arrival instruction' =>
        end($route['instructions'])['text'] === 'Arrive at the hospital',
];

$invalidResponseRejected = false;
try {
    TomTomRoutingModel::normalizeResponse([], 10.4, 122.9, 10.42, 122.92);
} catch (RuntimeException) {
    $invalidResponseRejected = true;
}
$checks['rejects incomplete provider data'] = $invalidResponseRejected;

$failures = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $passed ? 0 : 1;
}

exit($failures === 0 ? 0 : 1);
