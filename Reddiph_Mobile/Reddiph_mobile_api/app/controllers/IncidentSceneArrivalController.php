<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/ResponderIncidentWorkflowModel.php';
require_once __DIR__ . '/../services/TokenService.php';

final class IncidentSceneArrivalController extends ApiController
{
    public static function handle(): void
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

        try {
            $profileQuery = Database::connection()->prepare(
                "SELECT role, barangay_id FROM user_type WHERE id = ? LIMIT 1"
            );
            $profileQuery->execute([$userId]);
            $profile = $profileQuery->fetch();
            if (!$profile || $profile['role'] !== 'barangay_admin'
                || $profile['barangay_id'] === null) {
                self::respond(403, [
                    'success' => false,
                    'message' => 'This account cannot view Barangay Admin alerts.',
                ]);
            }

            $action = (string) ($data['action'] ?? 'queue');
            if ($action === 'queue') {
                self::respond(200, [
                    'success' => true,
                    'alerts' => ResponderIncidentWorkflowModel::pendingSceneArrivalAlerts(
                        (int) $profile['barangay_id']
                    ),
                ]);
            }
            if ($action === 'acknowledge') {
                $alertId = filter_var($data['alert_id'] ?? null, FILTER_VALIDATE_INT);
                if ($alertId === false || $alertId < 1) {
                    self::respond(422, [
                        'success' => false,
                        'message' => 'A valid arrival alert is required.',
                    ]);
                }
                $acknowledged = ResponderIncidentWorkflowModel::acknowledgeSceneArrivalAlert(
                    (int) $alertId,
                    (int) $userId,
                    (int) $profile['barangay_id']
                );
                self::respond($acknowledged ? 200 : 409, [
                    'success' => $acknowledged,
                    'message' => $acknowledged
                        ? 'Arrival alert acknowledged.'
                        : 'This arrival alert is no longer pending.',
                ]);
            }
            self::respond(422, ['success' => false, 'message' => 'Unknown alert action.']);
        } catch (Throwable $e) {
            self::fail('incident scene arrival alerts', $e, 'Unable to load arrival alerts.');
        }
    }
}
