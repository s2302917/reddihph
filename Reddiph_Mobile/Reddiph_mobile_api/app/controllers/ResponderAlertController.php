<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/DispatchModel.php';
require_once __DIR__ . '/../models/ResponderDashboardModel.php';
require_once __DIR__ . '/../models/ResponderProfileModel.php';
require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';
require_once __DIR__ . '/../services/AuditLogService.php';

final class ResponderAlertController extends ApiController
{
    private const OPEN_STATUSES = "'open', 'dispatched', 'referred'";

    public static function handle(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        $action = $data['action'] ?? 'queue';
        try {
            if ($action === 'queue' || $action === 'acknowledge') {
                self::forResponder($userId);
                if ($action === 'queue') {
                    self::queue($userId, self::origin($data));
                }
                self::acknowledge($userId, $data['alert_id'] ?? null);
            }
            if ($action === 'alert') {
                self::alert($userId, $data['incident_id'] ?? null);
            }
            self::respond(422, ['success' => false, 'message' => 'Unknown alert action.']);
        } catch (Throwable $e) {
            self::fail('responder alert', $e, 'Unable to process the responder alert.');
        }
    }

    private static function forResponder(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM user_type WHERE id = ? AND role = 'responder' LIMIT 1"
        );
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            self::respond(403, ['success' => false, 'message' => 'This account is not a responder.']);
        }
    }

    private static function queue(int $userId, ?array $origin): void
    {
        ResponderDashboardModel::updatePresence($userId, $origin);
        if (!ResponderProfileModel::isEligibleNow($userId)) {
            self::respond(200, ['success' => true, 'alerts' => []]);
        }
        $profile = ResponderDashboardModel::profile($userId);
        if ($profile !== null) {
            self::dispatchWaitingIncidents($profile);
        }
        $stmt = Database::connection()->prepare(
            'SELECT a.id, i.incident_id, i.incident_type, i.location, i.eta_minutes,
                    b.name AS barangay
             FROM responder_alerts a
             JOIN incidents i ON i.incident_id = a.incident_id
             LEFT JOIN barangays b ON b.id = i.barangay_id
             WHERE a.responder_user_id = ? AND a.status = \'pending\'
               AND i.status IN (' . self::OPEN_STATUSES . ')
             ORDER BY a.created_at, a.id
             LIMIT 5'
        );
        $stmt->execute([$userId]);
        $alerts = array_map(
            static fn(array $row): array => [
                'alert_id' => (int) $row['id'],
                'incident_id' => (int) $row['incident_id'],
                'incident_type' => $row['incident_type'],
                'location' => $row['location'],
                'barangay' => $row['barangay'],
                'eta_minutes' => $row['eta_minutes'] !== null ? (int) $row['eta_minutes'] : null,
            ],
            $stmt->fetchAll()
        );
        self::respond(200, ['success' => true, 'alerts' => $alerts]);
    }

    /**
     * Rechecks unassigned local SOS cases when an on-duty responder checks in.
     * This covers incidents created before a responder's first location heartbeat.
     */
    private static function dispatchWaitingIncidents(array $profile): void
    {
        if ($profile['barangay_id'] === null && $profile['hospital_id'] === null) {
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $scope = $profile['barangay_id'] !== null
                ? 'i.barangay_id = ?'
                : 'i.hospital_id = ?';
            $scopeId = $profile['barangay_id'] ?? $profile['hospital_id'];
            $stmt = $pdo->prepare(
                'SELECT i.incident_id, i.reporter_user_id, i.latitude, i.longitude,
                        i.hospital_id, i.barangay_id
                 FROM incidents i
                 WHERE ' . $scope . '
                   AND i.status IN (' . self::OPEN_STATUSES . ')
                   AND i.assigned_responder_user_id IS NULL
                   AND (i.responder_name IS NULL OR i.responder_name = \'\')
                   AND i.reporter_user_id IS NOT NULL
                 ORDER BY i.reported_at, i.incident_id
                 LIMIT 10
                 FOR UPDATE'
            );
            $stmt->execute([$scopeId]);
            $incidents = $stmt->fetchAll();

            foreach ($incidents as $incident) {
                if ($incident['latitude'] === null || $incident['longitude'] === null) {
                    continue;
                }
                $pair = DispatchModel::availableResponderPair(
                    $incident['barangay_id'] !== null ? (int) $incident['barangay_id'] : null,
                    $incident['reporter_user_id'] !== null
                    ? (int) $incident['reporter_user_id']
                    : null,
                    (float) $incident['latitude'],
                    (float) $incident['longitude']
                );
                if ($pair === null) {
                    continue;
                }
                $primaryResponder = $pair['responder'];
                $driverResponder = $pair['driver'];

                $transportHospitalId = $primaryResponder['hospital_id']
                    ?? ($incident['hospital_id'] !== null ? (int) $incident['hospital_id'] : null);
                $transport = $transportHospitalId === null
                    ? null
                    : DispatchModel::availableTransport($transportHospitalId);
                $eta = null;
                if ($primaryResponder['latitude'] !== null && $primaryResponder['longitude'] !== null) {
                    $eta = DispatchModel::etaMinutes(
                        $primaryResponder['latitude'],
                        $primaryResponder['longitude'],
                        (float) $incident['latitude'],
                        (float) $incident['longitude']
                    );
                }

                $update = $pdo->prepare(
                    'UPDATE incidents
                     SET assigned_responder_user_id = ?, responder_name = ?,
                         assigned_ambulance_id = ?, transport_name = ?,
                         transport_type = ?, driver_name = ?,
                         eta_minutes = COALESCE(?, eta_minutes), status = CASE WHEN status = \'open\' THEN \'dispatched\' ELSE status END
                     WHERE incident_id = ?
                       AND status IN (' . self::OPEN_STATUSES . ')'
                );
                $update->execute([
                    $primaryResponder['id'],
                    $primaryResponder['full_name'],
                    $transport['id'] ?? null,
                    $transport['call_sign'] ?? null,
                    $transport['vehicle_type'] ?? null,
                    $driverResponder['full_name'] ?? null,
                    $eta,
                    $incident['incident_id'],
                ]);
                if ($update->rowCount() !== 1) {
                    continue;
                }

                $assignmentRoleSupported = $pdo->prepare(
                    'SELECT 1 FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
                );
                $assignmentRoleSupported->execute(['responder_alerts', 'assignment_role']);
                $supportsAssignmentRole = (int) $assignmentRoleSupported->fetchColumn() === 1;
                $assignments = [
                    [
                        'responder_user_id' => $primaryResponder['id'],
                        'assignment_role' => 'responder',
                    ]
                ];
                if ($driverResponder !== null) {
                    $assignments[] = [
                        'responder_user_id' => $driverResponder['id'],
                        'assignment_role' => 'driver',
                    ];
                }
                foreach ($assignments as $assignment) {
                    if ($supportsAssignmentRole) {
                        $pdo->prepare(
                            "INSERT INTO responder_alerts
                                (incident_id, responder_user_id, assignment_role, status, created_at)
                             VALUES (?, ?, ?, 'pending', NOW())
                             ON DUPLICATE KEY UPDATE
                                status = 'pending', created_at = NOW(), acknowledged_at = NULL"
                        )->execute([
                                    (int) $incident['incident_id'],
                                    $assignment['responder_user_id'],
                                    $assignment['assignment_role'],
                                ]);
                        continue;
                    }
                    $pdo->prepare(
                        "INSERT INTO responder_alerts
                            (incident_id, responder_user_id, status, created_at)
                         VALUES (?, ?, 'pending', NOW())
                         ON DUPLICATE KEY UPDATE
                            status = 'pending', created_at = NOW(), acknowledged_at = NULL"
                    )->execute([
                                (int) $incident['incident_id'],
                                $assignment['responder_user_id'],
                            ]);
                }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function origin(array $data): ?array
    {
        $latitude = filter_var($data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        return $latitude !== false && $longitude !== false
            && abs($latitude) <= 90 && abs($longitude) <= 180
            ? [$latitude, $longitude]
            : null;
    }

    private static function acknowledge(int $userId, mixed $alertId): void
    {
        $id = filter_var($alertId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            self::respond(422, ['success' => false, 'message' => 'A valid alert is required.']);
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE responder_alerts SET status = 'acknowledged', acknowledged_at = NOW()
                 WHERE id = ? AND responder_user_id = ? AND status = 'pending'"
            );
            $stmt->execute([$id, $userId]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                self::respond(409, ['success' => false, 'message' => 'This alert is no longer pending.']);
            }
            AuditLogService::record(
                $pdo,
                $userId,
                'responder.alert_acknowledged',
                'responder_alert',
                (string) $id
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        self::respond(200, ['success' => true]);
    }

    private static function alert(int $userId, mixed $incidentId): void
    {
        $reporter = UserModel::findReporter($userId);
        if ($reporter === null || $reporter['role'] !== 'resident') {
            self::respond(403, ['success' => false, 'message' => 'Only residents can alert their assigned responder.']);
        }
        $id = filter_var($incidentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            self::respond(422, ['success' => false, 'message' => 'A valid incident is required.']);
        }
        $stmt = Database::connection()->prepare(
            'SELECT assigned_responder_user_id FROM incidents
             WHERE incident_id = ? AND reporter_user_id = ?
               AND status IN (' . self::OPEN_STATUSES . ')
             LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $responderId = $stmt->fetchColumn();
        if ($responderId === false) {
            self::respond(404, ['success' => false, 'message' => 'No active incident was found for this account.']);
        }
        if ($responderId === null) {
            self::respond(409, ['success' => false, 'message' => 'A responder has not been assigned yet.']);
        }

        Database::connection()->prepare(
            "INSERT INTO responder_alerts (incident_id, responder_user_id, status, created_at)
             VALUES (?, ?, 'pending', NOW())
             ON DUPLICATE KEY UPDATE
                status = 'pending', created_at = NOW(), acknowledged_at = NULL"
        )->execute([$id, (int) $responderId]);
        self::respond(200, ['success' => true, 'message' => 'The assigned responder has been alerted.']);
    }
}
