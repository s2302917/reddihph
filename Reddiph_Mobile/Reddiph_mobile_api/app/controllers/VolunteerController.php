<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/VolunteerModel.php';
require_once __DIR__ . '/../models/RoadRoutingModel.php';
require_once __DIR__ . '/../services/TokenService.php';

/** Authenticated volunteer roster and profile actions. */
final class VolunteerController extends ApiController
{
    public static function roster(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        $reporter = UserModel::findReporter($userId);
        if ($reporter === null
            || !in_array($reporter['role'], ['resident', 'responder'], true)) {
            self::respond(403, ['success' => false, 'message' => 'This account cannot view the volunteer roster.']);
        }

        [$latitude, $longitude] = self::coordinates($data);
        try {
            self::respond(200, [
                'success' => true,
                'volunteers' => VolunteerModel::roster($latitude, $longitude),
            ]);
        } catch (Throwable $e) {
            self::fail('volunteer roster', $e, 'Unable to load registered volunteers.');
        }
    }

    public static function updateLocation(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null
            || $profile['role'] !== 'volunteer'
            || $profile['approval_status'] !== 'approved') {
            self::respond(403, ['success' => false, 'message' => 'This account is not a volunteer.']);
        }

        [$latitude, $longitude] = self::coordinates($data);
        try {
            VolunteerModel::updateLocation($userId, $latitude, $longitude);
            self::respond(200, ['success' => true]);
        } catch (InvalidArgumentException $e) {
            self::respond(409, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            self::fail('volunteer location', $e, 'Unable to update volunteer location.');
        }
    }

    public static function updateAvailability(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null
            || $profile['role'] !== 'volunteer'
            || $profile['approval_status'] !== 'approved') {
            self::respond(403, ['success' => false, 'message' => 'This account is not a volunteer.']);
        }
        if (!is_bool($data['available'] ?? null)) {
            self::respond(422, ['success' => false, 'message' => 'Choose a valid availability setting.']);
        }

        try {
            VolunteerModel::setAvailability($userId, $data['available']);
            self::respond(200, ['success' => true]);
        } catch (InvalidArgumentException $e) {
            self::respond(409, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            self::fail('volunteer availability', $e, 'Unable to update availability.');
        }
    }

    public static function updateVehicle(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null
            || $profile['role'] !== 'volunteer'
            || $profile['approval_status'] !== 'approved') {
            self::respond(403, ['success' => false, 'message' => 'This account is not a volunteer.']);
        }
        if (isset($data['vehicle_type']) && !is_string($data['vehicle_type'])) {
            self::respond(422, ['success' => false, 'message' => 'Enter valid vehicle information.']);
        }
        $vehicleType = trim($data['vehicle_type'] ?? '');
        if (strlen($vehicleType) > 80) {
            self::respond(422, ['success' => false, 'message' => 'Vehicle information must be 80 characters or fewer.']);
        }

        try {
            VolunteerModel::updateVehicle(
                $userId,
                $vehicleType !== '' ? $vehicleType : null
            );
            self::respond(200, [
                'success' => true,
                'vehicle_type' => $vehicleType !== '' ? $vehicleType : null,
            ]);
        } catch (Throwable $e) {
            self::fail('volunteer vehicle', $e, 'Unable to update vehicle information.');
        }
    }

    public static function requests(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = self::authenticatedVolunteer($data);

        try {
            self::respond(200, [
                'success' => true,
                'requests' => VolunteerModel::requests($userId),
            ]);
        } catch (Throwable $e) {
            self::fail('volunteer requests', $e, 'Unable to load volunteer requests.');
        }
    }

    public static function dashboard(): void
    {
        self::requireMethod('POST');
        $userId = self::authenticatedVolunteer(self::readJsonBody());
        try {
            self::respond(200, [
                'success' => true,
                'profile' => VolunteerModel::profileSummary($userId),
            ]);
        } catch (Throwable $e) {
            self::fail('volunteer dashboard', $e, 'Unable to load volunteer profile.');
        }
    }

    public static function history(): void
    {
        self::requireMethod('POST');
        $userId = self::authenticatedVolunteer(self::readJsonBody());
        try {
            self::respond(200, [
                'success' => true,
                'items' => VolunteerModel::history($userId),
            ]);
        } catch (Throwable $e) {
            self::fail('volunteer history', $e, 'Unable to load assistance history.');
        }
    }

    public static function community(): void
    {
        self::requireMethod('POST');
        $userId = self::authenticatedVolunteer(self::readJsonBody());
        try {
            self::respond(200, [
                'success' => true,
                'profile' => VolunteerModel::profileSummary($userId),
            ] + VolunteerModel::community());
        } catch (Throwable $e) {
            self::fail('volunteer community', $e, 'Unable to load community feedback.');
        }
    }

    public static function details(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        self::authenticatedVolunteer($data);
        $volunteerId = filter_var($data['volunteer_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($volunteerId === false) {
            self::respond(422, ['success' => false, 'message' => 'Choose a valid volunteer.']);
        }
        try {
            $profile = VolunteerModel::volunteerDetails($volunteerId);
            if ($profile === null) {
                self::respond(404, ['success' => false, 'message' => 'Volunteer not found.']);
            }
            self::respond(200, ['success' => true, 'profile' => $profile]);
        } catch (Throwable $e) {
            self::fail('volunteer details', $e, 'Unable to load volunteer details.');
        }
    }

    public static function assistanceRoute(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = self::authenticatedVolunteer($data);
        $assistanceId = filter_var($data['assistance_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($assistanceId === false) {
            self::respond(422, ['success' => false, 'message' => 'Choose a valid assistance request.']);
        }
        [$latitude, $longitude] = self::coordinates($data);
        try {
            $route = VolunteerModel::assistanceRouteData(
                $userId,
                $assistanceId,
                $latitude,
                $longitude
            );
            if ($route === null) {
                self::respond(404, [
                    'success' => false,
                    'message' => 'This accepted incident is no longer available.',
                ]);
            }
            self::respond(200, $route);
        } catch (PDOException $e) {
            if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('Volunteer route graph schema is missing: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'The local road network is not installed.',
                ]);
            }
            self::fail(
                'volunteer assistance route',
                $e,
                'Unable to calculate a route to the incident.'
            );
        } catch (RuntimeException $e) {
            self::respond(422, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            self::fail(
                'volunteer assistance route',
                $e,
                'Unable to calculate a route to the incident.'
            );
        }
    }

    public static function respondToRequest(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null
            || $profile['role'] !== 'volunteer'
            || $profile['approval_status'] !== 'approved') {
            self::respond(403, ['success' => false, 'message' => 'This account is not a volunteer.']);
        }
        $assistanceId = filter_var($data['assistance_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $action = $data['action'] ?? null;
        if ($assistanceId === false
            || !in_array($action, ['accept', 'decline', 'complete'], true)) {
            self::respond(422, ['success' => false, 'message' => 'Choose a valid volunteer request action.']);
        }

        try {
            VolunteerModel::respondToAssistance($userId, $assistanceId, $action);
            self::respond(200, ['success' => true]);
        } catch (InvalidArgumentException $e) {
            self::respond(409, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            self::fail('volunteer request action', $e, 'Unable to update the volunteer request.');
        }
    }

    public static function rateIncident(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null || $profile['role'] !== 'resident') {
            self::respond(403, ['success' => false, 'message' => 'Only residents can rate assistance.']);
        }

        $incidentId = filter_var($data['incident_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5],
        ]);
        if (isset($data['review']) && !is_string($data['review'])) {
            self::respond(422, ['success' => false, 'message' => 'Enter a valid review.']);
        }
        $review = trim($data['review'] ?? '');
        if ($incidentId === false || $rating === false || strlen($review) > 1000) {
            self::respond(422, ['success' => false, 'message' => 'Enter a rating from 1 to 5 and a valid review.']);
        }

        try {
            VolunteerModel::rateIncident(
                $userId,
                $incidentId,
                $rating,
                $review !== '' ? $review : null
            );
            self::respond(201, ['success' => true]);
        } catch (InvalidArgumentException $e) {
            self::respond(409, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            self::fail('volunteer rating', $e, 'Unable to save your rating.');
        }
    }

    private static function coordinates(array $data): array
    {
        $latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $latitude < -90 || $latitude > 90
            || $longitude === false || $longitude < -180 || $longitude > 180) {
            self::respond(422, ['success' => false, 'message' => 'A valid location is required.']);
        }

        return [(float) $latitude, (float) $longitude];
    }

    private static function authenticatedVolunteer(array $data): int
    {
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, [
                'success' => false,
                'message' => 'Your session expired. Please sign in again.',
            ]);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null
            || $profile['role'] !== 'volunteer'
            || $profile['approval_status'] !== 'approved') {
            self::respond(403, [
                'success' => false,
                'message' => 'This account is not an approved volunteer.',
            ]);
        }

        return (int) $userId;
    }
}
