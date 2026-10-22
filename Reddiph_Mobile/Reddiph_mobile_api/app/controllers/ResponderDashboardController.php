<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/DispatchModel.php';
require_once __DIR__ . '/../models/ResponderDashboardModel.php';
require_once __DIR__ . '/../services/TokenService.php';

/**
 * JSON controller for the responder home page.
 */
final class ResponderDashboardController extends ApiController
{
    private const QUEUE_PAGE_SIZE = 10;

    public static function show(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();

        $userId = self::userId($data);
        $page = self::page($data);
        $origin = self::origin($data);

        try {
            $profile = self::responder($userId);
            ResponderDashboardModel::updatePresence($userId, $origin);
            $hospitalId = $profile['hospital_id'];
            $origin ??= ResponderDashboardModel::hospitalPoint($hospitalId);
            $active = ResponderDashboardModel::activeCase(
                $userId,
                $profile['full_name'],
                $hospitalId
            );
            [$rows, $total] = ResponderDashboardModel::queue(
                $profile['barangay_id'],
                $hospitalId,
                $active['incident_id'] ?? null,
                $userId,
                $page
            );

            self::respond(200, [
                'success' => true,
                'responder' => ['full_name' => $profile['full_name'], 'vehicle' => $profile['vehicle']],
                'active_case' => $active === null ? null : self::incident($active, $origin),
                'queue' => [
                    'items' => array_map(static fn (array $row): array => self::incident($row, $origin), $rows),
                    'page' => $page,
                    'pages' => max(1, (int) ceil($total / ResponderDashboardModel::PAGE_SIZE)),
                    'total' => $total,
                ],
            ]);
        } catch (Throwable $e) {
            self::fail('responder dashboard', $e, 'Unable to load your dashboard.');
        }
    }

    /** The SOS queue tab: one page of the barangay's incidents, filtered by status, with the filter counts. */
    public static function queue(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();

        $userId = self::userId($data);
        $page = self::page($data);
        $origin = self::origin($data);
        $status = $data['status'] ?? 'all';
        if (!in_array($status, ['all', 'critical', 'urgent', 'stable'], true)) {
            self::respond(422, ['success' => false, 'message' => 'Unknown filter.']);
        }

        try {
            $profile = self::responder($userId);
            $barangayId = $profile['barangay_id'];
            $hospitalId = $profile['hospital_id'];
            $origin ??= ResponderDashboardModel::hospitalPoint($hospitalId);
            [$rows, $total] = ResponderDashboardModel::incidents(
                $barangayId,
                $hospitalId,
                $userId,
                $profile['full_name'],
                $status === 'all' ? null : $status,
                $page,
                self::QUEUE_PAGE_SIZE
            );

            self::respond(200, [
                'success' => true,
                'responder' => ['full_name' => $profile['full_name']],
                'counts' => ResponderDashboardModel::statusCounts(
                    $barangayId,
                    $hospitalId,
                    $userId,
                    $profile['full_name']
                ),
                'queue' => [
                    'items' => array_map(static fn (array $row): array => self::incident($row, $origin), $rows),
                    'page' => $page,
                    'pages' => max(1, (int) ceil($total / self::QUEUE_PAGE_SIZE)),
                    'total' => $total,
                ],
            ]);
        } catch (Throwable $e) {
            self::fail('responder queue', $e, 'Unable to load the SOS queue.');
        }
    }

    /** The Map tab: every open incident of the responder's barangay and dispatch list, with coordinates. */
    public static function map(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();

        $userId = self::userId($data);
        $origin = self::origin($data);

        try {
            $profile = self::responder($userId);
            $origin ??= ResponderDashboardModel::hospitalPoint($profile['hospital_id']);
            $rows = ResponderDashboardModel::mapIncidents(
                $profile['barangay_id'],
                $profile['hospital_id'],
                $userId,
                $profile['full_name']
            );

            self::respond(200, [
                'success' => true,
                'incidents' => array_map(static fn (array $row): array => self::incident($row, $origin), $rows),
            ]);
        } catch (Throwable $e) {
            self::fail('responder map', $e, 'Unable to load the map.');
        }
    }

    private static function userId(array $data): int
    {
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        return $userId;
    }

    private static function responder(int $userId): array
    {
        $profile = ResponderDashboardModel::profile($userId);
        if ($profile === null) {
            self::respond(403, ['success' => false, 'message' => 'This account is not a responder.']);
        }

        return $profile;
    }

    private static function page(array $data): int
    {
        return max(1, (int) filter_var($data['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
    }

    /** The responder's reported [latitude, longitude], or null when missing or out of range. */
    private static function origin(array $data): ?array
    {
        $latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        return $latitude !== false && $longitude !== false && abs($latitude) <= 90 && abs($longitude) <= 180
            ? [$latitude, $longitude]
            : null;
    }

    /** Shapes an incident row for the app, with its distance from [$origin] when both are known. */
    private static function incident(array $row, ?array $origin): array
    {
        $distanceKm = null;
        if ($origin !== null && $row['latitude'] !== null && $row['longitude'] !== null) {
            $distanceKm = round(DispatchModel::distanceKm($origin[0], $origin[1], (float) $row['latitude'], (float) $row['longitude']), 1);
        }

        return [
            'incident_id' => (int) $row['incident_id'],
            'incident_type' => $row['incident_type'],
            'status' => $row['status'],
            'sent_to_hospital' => (bool) $row['sent_to_hospital'],
            'reported_by' => $row['reported_by'],
            'responder_name' => $row['responder_name'],
            'has_reporter_account' => (bool) $row['has_reporter_account'],
            'location' => $row['location'],
            'barangay' => $row['barangay_name'],
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'patient_name' => $row['patient_name'],
            'patient_age' => $row['patient_age'] !== null ? (int) $row['patient_age'] : null,
            'patient_gender' => $row['patient_gender'],
            'eta_minutes' => $row['eta_minutes'] !== null ? (int) $row['eta_minutes'] : null,
            'transport_name' => $row['transport_name'],
            'transport_type' => $row['transport_type'],
            'minutes_ago' => max(0, (int) $row['minutes_ago']),
            'distance_km' => $distanceKm,
        ];
    }
}
