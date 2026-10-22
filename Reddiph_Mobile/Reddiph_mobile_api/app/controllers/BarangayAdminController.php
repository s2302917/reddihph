<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/BarangayAdminDashboardModel.php';
require_once __DIR__ . '/../services/TokenService.php';

final class BarangayAdminController extends ApiController
{
    public static function handle(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        try {
            $profileQuery = Database::connection()->prepare(
                "SELECT role, barangay_id FROM user_type WHERE id = ? LIMIT 1"
            );
            $profileQuery->execute([$userId]);
            $profile = $profileQuery->fetch();
            if (!$profile || $profile['role'] !== 'barangay_admin' || $profile['barangay_id'] === null) {
                self::respond(403, ['success' => false, 'message' => 'This account cannot view the Barangay Admin portal.']);
            }

            $barangayId = (int) $profile['barangay_id'];
            $action = (string) ($data['action'] ?? 'dashboard');
            switch ($action) {
                case 'dashboard':
                    self::respond(200, [
                        'success' => true,
                        'summary' => BarangayAdminDashboardModel::summary($barangayId),
                        'activity' => BarangayAdminDashboardModel::recentActivity($barangayId),
                    ]);
                case 'notifications':
                    self::respond(200, [
                        'success' => true,
                        'notifications' => BarangayAdminDashboardModel::notifications($barangayId),
                    ]);
                case 'approvals':
                    self::respond(200, [
                        'success' => true,
                        'signups' => BarangayAdminDashboardModel::pendingSignups($barangayId),
                        'counts' => BarangayAdminDashboardModel::signupCounts($barangayId),
                    ]);
                case 'signup_list':
                    $status = $data['status'] ?? null;
                    $page = filter_var($data['page'] ?? 1, FILTER_VALIDATE_INT);
                    if (!is_string($status)
                        || !in_array($status, ['pending', 'approved', 'rejected'], true)
                        || $page === false
                        || $page < 1) {
                        self::respond(422, [
                            'success' => false,
                            'message' => 'Choose a valid signup status and page.',
                        ]);
                    }
                    self::respond(200, [
                        'success' => true,
                    ] + BarangayAdminDashboardModel::signupAccounts(
                        $barangayId,
                        $status,
                        min($page, 100000)
                    ));
                case 'review_signup':
                    $accountId = filter_var($data['account_id'] ?? null, FILTER_VALIDATE_INT);
                    $decision = (string) ($data['decision'] ?? '');
                    $currentStatus = (string) ($data['current_status'] ?? 'pending');
                    if ($accountId === false || $accountId < 1
                        || !in_array($currentStatus, ['pending', 'approved', 'rejected'], true)
                        || !in_array($decision, ['approve', 'reject', 'revoke'], true)
                        || !self::validSignupTransition($currentStatus, $decision)) {
                        self::respond(422, ['success' => false, 'message' => 'Choose a valid signup review action.']);
                    }
                    $updated = BarangayAdminDashboardModel::reviewSignup(
                        (int) $accountId,
                        (int) $userId,
                        $barangayId,
                        $decision,
                        $currentStatus
                    );
                    self::respond($updated ? 200 : 409, [
                        'success' => $updated,
                        'message' => $updated
                            ? 'Signup review saved.'
                            : 'This signup has changed. Refresh and try again.',
                    ]);
                case 'logs':
                    $page = filter_var($data['page'] ?? 1, FILTER_VALIDATE_INT);
                    if ($page === false || $page < 1) {
                        self::respond(422, ['success' => false, 'message' => 'Choose a valid log page.']);
                    }
                    self::respond(200, [
                        'success' => true,
                    ] + BarangayAdminDashboardModel::incidentLogs(
                        $barangayId,
                        trim((string) ($data['search'] ?? '')),
                        min($page, 100000)
                    ));
                case 'logs_export':
                    $period = $data['period'] ?? null;
                    $includes = $data['includes'] ?? null;
                    if (!is_string($period)
                        || !in_array($period, ['today', 'this_week', 'this_month'], true)
                        || !is_array($includes)
                        || $includes === []
                        || count($includes) > 4
                        || array_filter($includes, static fn ($value): bool =>
                            !is_string($value)
                            || !in_array($value, [
                                'sos',
                                'hospital',
                                'volunteers',
                                'false_reports',
                            ], true)
                        ) !== []) {
                        self::respond(422, [
                            'success' => false,
                            'message' => 'Choose a valid export period and at least one record type.',
                        ]);
                    }
                    self::respond(200, [
                        'success' => true,
                    ] + BarangayAdminDashboardModel::exportLogs(
                        $barangayId,
                        $period,
                        array_values(array_unique($includes))
                    ));
                case 'incident_detail':
                    $incidentId = filter_var($data['incident_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($incidentId === false || $incidentId < 1) {
                        self::respond(422, ['success' => false, 'message' => 'Choose a valid incident.']);
                    }
                    $incident = BarangayAdminDashboardModel::incidentDetail((int) $incidentId, $barangayId);
                    if ($incident === null) {
                        self::respond(404, ['success' => false, 'message' => 'Incident not found.']);
                    }
                    self::respond(200, ['success' => true, 'incident' => $incident]);
                default:
                    self::respond(422, ['success' => false, 'message' => 'Unknown Barangay Admin action.']);
            }
        } catch (Throwable $e) {
            if ($e instanceof PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('Barangay Admin portal migration is missing: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'The Barangay Admin portal database migration has not been applied.',
                ]);
            }
            self::fail('barangay admin portal', $e, 'Unable to load Barangay Admin data.');
        }
    }

    private static function validSignupTransition(string $status, string $decision): bool
    {
        return match ([$status, $decision]) {
            ['pending', 'approve'],
            ['pending', 'reject'],
            ['approved', 'revoke'],
            ['rejected', 'approve'] => true,
            default => false,
        };
    }
}
