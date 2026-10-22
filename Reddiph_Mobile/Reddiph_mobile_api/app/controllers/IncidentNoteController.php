<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/ResponderDashboardModel.php';
require_once __DIR__ . '/../models/ResponderIncidentWorkflowModel.php';
require_once __DIR__ . '/../models/RoadRoutingModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';

final class IncidentNoteController extends ApiController
{
    public static function update(): void
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
        $note = $data['notes'] ?? null;
        if ($incidentId === false || $incidentId < 1) {
            self::respond(422, ['success' => false, 'message' => 'A valid incident is required.']);
        }
        if (!is_string($note) || trim($note) === '' || strlen(trim($note)) > 2000) {
            self::respond(422, [
                'success' => false,
                'message' => 'Enter an update with no more than 2000 characters.',
            ]);
        }

        try {
            $profile = ResponderDashboardModel::profile((int) $userId);
            if ($profile !== null) {
                if (!ResponderIncidentWorkflowModel::belongsToResponder(
                    (int) $incidentId,
                    (int) $userId,
                    $profile['hospital_id'],
                    $profile['full_name']
                )) {
                    self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
                }
                ResponderIncidentWorkflowModel::addIncidentNoteUpdate(
                    (int) $incidentId,
                    (int) $userId,
                    $profile['hospital_id'],
                    $profile['full_name'],
                    trim($note)
                );
            } else {
                $reporter = UserModel::findReporter((int) $userId);
                if ($reporter === null || $reporter['role'] !== 'resident'
                    || !RoadRoutingModel::belongsToReporter((int) $incidentId, (int) $userId)) {
                    self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
                }
                ResponderIncidentWorkflowModel::addIncidentNoteUpdate(
                    (int) $incidentId,
                    (int) $userId,
                    null,
                    (string) $reporter['full_name'],
                    trim($note),
                    true
                );
            }

            self::respond(201, [
                'success' => true,
                'message' => 'Hospital staff were notified.',
            ]);
        } catch (InvalidArgumentException $error) {
            self::respond(404, ['success' => false, 'message' => $error->getMessage()]);
        } catch (Throwable $error) {
            self::fail('incident note update', $error, 'Unable to send this update to hospital staff.');
        }
    }
}
