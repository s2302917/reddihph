<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/FalseReportModel.php';
require_once __DIR__ . '/../services/TokenService.php';

/** Responder false-report submission and barangay-scoped admin moderation. */
final class FalseReportController extends ApiController
{
    public static function submit(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        [$userId, $profile] = self::authenticatedProfile($data, 'responder');
        $incidentId = filter_var($data['incident_id'] ?? null, FILTER_VALIDATE_INT);
        $reason = (string) ($data['reason'] ?? '');
        $notes = trim((string) ($data['notes'] ?? ''));
        if ($incidentId === false || $incidentId < 1) {
            self::respond(422, ['success' => false, 'message' => 'A valid case is required.']);
        }
        if (!in_array($reason, FalseReportModel::REASONS, true)) {
            self::respond(422, ['success' => false, 'message' => 'Choose a reason for closing this case.']);
        }
        if (strlen($notes) > 2000) {
            self::respond(422, ['success' => false, 'message' => 'Notes must be 2,000 characters or fewer.']);
        }

        try {
            $id = FalseReportModel::submit(
                (int) $incidentId,
                $userId,
                $profile['hospital_id'],
                $reason,
                $notes === '' ? null : $notes
            );
            self::respond(201, [
                'success' => true,
                'false_report_id' => $id,
                'message' => 'Case closed and sent to your Barangay Admin for review.',
            ]);
        } catch (FalseReportAlreadyExistsException|FalseReportUnavailableException $e) {
            self::respond(409, ['success' => false, 'message' => $e->getMessage()]);
        } catch (PDOException $e) {
            if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('False-report moderation schema is missing: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'False-report moderation is not set up. Run migration 20261017_false_report_moderation.sql.',
                ]);
            }
            self::fail('false report submit', $e, 'Unable to submit this case for review.');
        } catch (Throwable $e) {
            self::fail('false report submit', $e, 'Unable to submit this case for review.');
        }
    }

    public static function adminQueue(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        [$userId, $profile] = self::authenticatedProfile($data, 'barangay_admin');
        if ($profile['barangay_id'] === null) {
            self::respond(403, ['success' => false, 'message' => 'Your admin account is not assigned to a barangay.']);
        }

        try {
            self::respond(200, [
                'success' => true,
                'reports' => FalseReportModel::pendingForBarangay($profile['barangay_id']),
            ]);
        } catch (Throwable $e) {
            if ($e instanceof PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('False-report moderation schema is missing: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'False-report moderation is not set up. Run migration 20261017_false_report_moderation.sql.',
                ]);
            }
            self::fail('false report queue', $e, 'Unable to load false-report reviews.');
        }
    }

    public static function review(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        [$userId, $profile] = self::authenticatedProfile($data, 'barangay_admin');
        if ($profile['barangay_id'] === null) {
            self::respond(403, ['success' => false, 'message' => 'Your admin account is not assigned to a barangay.']);
        }

        $falseReportId = filter_var($data['false_report_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($data['decision'] ?? '');
        $banFirstWarning = ($data['ban_first_warning'] ?? false) === true;
        $banDays = isset($data['ban_days']) ? filter_var($data['ban_days'], FILTER_VALIDATE_INT) : null;
        if ($falseReportId === false || $falseReportId < 1) {
            self::respond(422, ['success' => false, 'message' => 'A valid false report is required.']);
        }
        if (!in_array($decision, ['confirm', 'dismiss'], true)
            || ($decision === 'dismiss' && ($banFirstWarning || $banDays !== null))
            || (array_key_exists('ban_days', $data) && $banDays === false)
            || ($banDays !== null && ($banDays < 1 || $banDays > 365))) {
            self::respond(422, ['success' => false, 'message' => 'Choose a valid review action.']);
        }

        try {
            $result = FalseReportModel::review(
                (int) $falseReportId,
                $userId,
                $profile['barangay_id'],
                $decision,
                $banFirstWarning,
                $banDays === false ? null : $banDays
            );
            self::respond(200, ['success' => true] + $result);
        } catch (FalseReportUnavailableException $e) {
            self::respond(404, ['success' => false, 'message' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            self::respond(422, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            if ($e instanceof PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('False-report moderation schema is missing: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'False-report moderation is not set up. Run migration 20261017_false_report_moderation.sql.',
                ]);
            }
            self::fail('false report review', $e, 'Unable to review this false report.');
        }
    }

    /** @return array{int, array{hospital_id:?int,barangay_id:?int}} */
    private static function authenticatedProfile(array $data, string $role): array
    {
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, ['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT role, hospital_id, barangay_id FROM user_type WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            self::fail('false report authorization', $e, 'Unable to verify your account.');
        }

        if (!$user || $user['role'] !== $role) {
            self::respond(403, ['success' => false, 'message' => 'This account cannot perform that action.']);
        }

        return [
            (int) $userId,
            [
                'hospital_id' => $user['hospital_id'] !== null ? (int) $user['hospital_id'] : null,
                'barangay_id' => $user['barangay_id'] !== null ? (int) $user['barangay_id'] : null,
            ],
        ];
    }
}
