<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

final class TomTomRoutingModel
{
    private const ENDPOINT =
        'https://api.tomtom.com/routing/1/calculateRoute/';

    public static function isConfigured(): bool
    {
        return Database::tomtomApiKey() !== null;
    }

    public static function route(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): array {
        $apiKey = Database::tomtomApiKey();
        if ($apiKey === null) {
            throw new RuntimeException('TomTom routing is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for traffic routing.');
        }

        $locations = implode(':', [
            self::formatCoordinate($fromLatitude, $fromLongitude),
            self::formatCoordinate($toLatitude, $toLongitude),
        ]);
        $url = self::ENDPOINT . $locations . '/json?'
            . http_build_query([
                'key' => $apiKey,
                'routeType' => 'fastest',
                'traffic' => 'true',
                'departAt' => 'now',
                'travelMode' => 'car',
                'instructionsType' => 'text',
                'language' => 'en-US',
            ]);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Reddiph/1.0 (traffic-aware hospital routing)',
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            $reason = $curlError !== '' ? $curlError : 'HTTP ' . $status;
            throw new RuntimeException('TomTom route request failed: ' . $reason);
        }

        try {
            $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('TomTom returned invalid route data.', 0, $error);
        }

        if (!is_array($response)) {
            throw new RuntimeException('TomTom returned invalid route data.');
        }

        return self::normalizeResponse(
            $response,
            $fromLatitude,
            $fromLongitude,
            $toLatitude,
            $toLongitude
        );
    }

    public static function normalizeResponse(
        array $response,
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): array {
        $providerRoute = $response['routes'][0] ?? null;
        $summary = is_array($providerRoute) ? ($providerRoute['summary'] ?? null) : null;
        if (!is_array($providerRoute)
            || !is_array($summary)
            || !is_numeric($summary['lengthInMeters'] ?? null)
            || !is_numeric($summary['travelTimeInSeconds'] ?? null)) {
            throw new RuntimeException('TomTom returned an incomplete route.');
        }

        $geometry = [
            ['latitude' => $fromLatitude, 'longitude' => $fromLongitude],
        ];
        foreach ($providerRoute['legs'] ?? [] as $leg) {
            if (!is_array($leg) || !is_array($leg['points'] ?? null)) {
                continue;
            }
            foreach ($leg['points'] as $point) {
                if (!is_array($point)
                    || !is_numeric($point['latitude'] ?? null)
                    || !is_numeric($point['longitude'] ?? null)) {
                    continue;
                }
                $geometry[] = [
                    'latitude' => (float) $point['latitude'],
                    'longitude' => (float) $point['longitude'],
                ];
            }
        }
        $geometry[] = ['latitude' => $toLatitude, 'longitude' => $toLongitude];
        if (count($geometry) < 3) {
            throw new RuntimeException('TomTom returned incomplete route geometry.');
        }

        $providerInstructions = [];
        $previousOffset = 0;
        foreach ($providerRoute['guidance']['instructions'] ?? [] as $instruction) {
            if (!is_array($instruction)
                || !is_string($instruction['message'] ?? null)
                || trim($instruction['message']) === '') {
                continue;
            }
            $offset = is_numeric($instruction['routeOffsetInMeters'] ?? null)
                ? min(
                    (int) $summary['lengthInMeters'],
                    max($previousOffset, (int) $instruction['routeOffsetInMeters'])
                )
                : $previousOffset;
            $providerInstructions[] = [
                'text' => trim($instruction['message']),
                'offset_m' => $offset,
            ];
            $previousOffset = $offset;
        }
        if ($providerInstructions === []) {
            throw new RuntimeException('TomTom returned no turn-by-turn directions.');
        }
        $instructions = [];
        foreach ($providerInstructions as $index => $instruction) {
            $nextOffset = $providerInstructions[$index + 1]['offset_m']
                ?? (int) $summary['lengthInMeters'];
            $instructions[] = [
                'text' => $instruction['text'],
                'distance_m' => max(0, $nextOffset - $instruction['offset_m']),
            ];
        }
        $instructions[] = ['text' => 'Arrive at the hospital', 'distance_m' => 0];

        $travelTimeSeconds = (int) $summary['travelTimeInSeconds'];
        return [
            'distance_m' => (int) $summary['lengthInMeters'],
            'eta_minutes' => max(1, (int) ceil($travelTimeSeconds / 60)),
            'geometry' => $geometry,
            'map_roads' => [],
            'instructions' => $instructions,
            'visited_nodes' => 0,
            'traffic_aware' => true,
            'traffic_delay_seconds' => is_numeric(
                $summary['trafficDelayInSeconds'] ?? null
            ) ? max(0, (int) $summary['trafficDelayInSeconds']) : null,
        ];
    }

    private static function formatCoordinate(float $latitude, float $longitude): string
    {
        return number_format($latitude, 6, '.', '')
            . ',' . number_format($longitude, 6, '.', '');
    }
}
