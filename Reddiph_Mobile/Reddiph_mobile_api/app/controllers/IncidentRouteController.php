<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/ResponderDashboardModel.php';
require_once __DIR__ . '/../models/ResponderIncidentWorkflowModel.php';
require_once __DIR__ . '/../models/RoadRoutingModel.php';
require_once __DIR__ . '/../models/TomTomRoutingModel.php';
require_once __DIR__ . '/../services/TokenService.php';
require_once __DIR__ . '/../models/UserModel.php';

final class IncidentRouteController extends ApiController
{
    public static function directions(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, [
                'success' => false,
                'message' => 'Your session expired. Please sign in again.',
            ]);
        }

        $incidentId = filter_var($data['incident_id'] ?? null, FILTER_VALIDATE_INT);
        $latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $destinationType = (string) ($data['destination'] ?? 'hospital');
        if (!in_array($destinationType, ['hospital', 'incident', 'responder_incident'], true)) {
            self::respond(422, ['success' => false, 'message' => 'Invalid route destination.']);
        }
        $serverOrigin = $destinationType === 'responder_incident';
        if ($incidentId === false || $incidentId < 1
            || (!$serverOrigin && (
                $latitude === false || $latitude < -90 || $latitude > 90
                || $longitude === false || $longitude < -180 || $longitude > 180
            ))) {
            self::respond(422, [
                'success' => false,
                'message' => 'A valid incident and current location are required.',
            ]);
        }

        try {
            $profile = ResponderDashboardModel::profile((int) $userId);
            $routeLatitude = (float) $latitude;
            $routeLongitude = (float) $longitude;
            if ($destinationType === 'incident') {
                if ($profile === null || !ResponderIncidentWorkflowModel::belongsToResponder(
                    (int) $incidentId,
                    (int) $userId,
                    $profile['hospital_id'],
                    $profile['full_name']
                )) {
                    self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
                }
                $destination = ResponderIncidentWorkflowModel::incidentDestinationForResponder(
                    (int) $incidentId,
                    (int) $userId,
                    $profile['hospital_id'],
                    $profile['full_name']
                );
                if ($destination === null) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'This active incident has no valid destination coordinates.',
                    ]);
                }
                $transportAssigned = false;
            } elseif ($destinationType === 'responder_incident') {
                $reporter = UserModel::findReporter((int) $userId);
                if ($profile !== null || $reporter === null || $reporter['role'] !== 'resident'
                    || !RoadRoutingModel::belongsToReporter((int) $incidentId, (int) $userId)) {
                    self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
                }
                $endpoints = RoadRoutingModel::responderRouteForReporter(
                    (int) $incidentId,
                    (int) $userId
                );
                if ($endpoints === null) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'A recent responder location is not available for this incident.',
                    ]);
                }
                $routeLatitude = $endpoints['origin']['latitude'];
                $routeLongitude = $endpoints['origin']['longitude'];
                $destination = $endpoints['destination'];
                $transportAssigned = false;
            } else {
                if ($profile !== null) {
                    if (!ResponderIncidentWorkflowModel::belongsToResponder(
                        (int) $incidentId,
                        (int) $userId,
                        $profile['hospital_id'],
                        $profile['full_name']
                    )) {
                        self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
                    }
                } else {
                    $reporter = UserModel::findReporter((int) $userId);
                    if ($reporter === null || $reporter['role'] !== 'resident'
                        || !RoadRoutingModel::belongsToReporter((int) $incidentId, (int) $userId)) {
                        self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
                    }
                }

                $destination = RoadRoutingModel::hospitalForIncident((int) $incidentId);
                if ($destination === null) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'The assigned hospital has no coordinates.',
                    ]);
                }
                $transportAssigned = $destination['transport_assigned'];
            }
            $route = null;
            if (TomTomRoutingModel::isConfigured()) {
                try {
                    $route = TomTomRoutingModel::route(
                        $routeLatitude,
                        $routeLongitude,
                        $destination['latitude'],
                        $destination['longitude']
                    );
                } catch (RuntimeException $error) {
                    error_log(
                        'TomTom traffic routing unavailable; using the private road graph: '
                        . $error->getMessage()
                    );
                }
            }
            if ($route === null) {
                $route = RoadRoutingModel::route(
                    $routeLatitude,
                    $routeLongitude,
                    $destination['latitude'],
                    $destination['longitude']
                );
            }
            $response = [
                'success' => true,
                'transport_assigned' => $transportAssigned,
                'route' => $route,
            ];
            if ($destinationType === 'incident' || $destinationType === 'responder_incident') {
                $response['destination'] = $destination;
            } else {
                $response['hospital'] = $destination;
            }
            self::respond(200, $response);
        } catch (PDOException $error) {
            if (in_array($error->getCode(), ['42S02', '42S22'], true)) {
                error_log('Mobile API route graph schema is missing: ' . $error->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'The private road network is not installed. Apply the route migration and import the road graph.',
                ]);
            }
            self::fail('incident route', $error, 'Unable to calculate the route.');
        } catch (RuntimeException $error) {
            self::respond(422, [
                'success' => false,
                'message' => $error->getMessage(),
            ]);
        } catch (Throwable $error) {
            self::fail('incident route', $error, 'Unable to calculate the route.');
        }
    }
}
