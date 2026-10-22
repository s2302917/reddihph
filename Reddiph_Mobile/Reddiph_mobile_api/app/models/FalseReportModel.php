<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

final class FalseReportAlreadyExistsException extends RuntimeException
{
}

final class FalseReportUnavailableException extends RuntimeException
{
}

/** Database operations for responder false-report flags and Barangay Admin review. */
final class FalseReportModel
{
    public const REASONS = [
        'prank_or_test',
        'wrong_location',
        'already_resolved',
        'cannot_find_anyone',
    ];

    public static function submit(
        int $incidentId,
        int $responderId,
        ?int $hospitalId,
        string $reason,
        ?string $notes
    ): int {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $incident = $pdo->prepare(
                'SELECT i.incident_id, i.reported_by, i.reporter_user_id, i.hospital_id, i.status,
                        u.role AS reporter_role
                 FROM incidents i
                 JOIN user_type u ON u.id = i.reporter_user_id
                 WHERE i.incident_id = ?
                   AND (i.assigned_responder_user_id = ?
                        OR (i.assigned_responder_user_id IS NULL
                            AND i.hospital_id <=> ?))
                 FOR UPDATE'
            );
            $incident->execute([$incidentId, $responderId, $hospitalId]);
            $case = $incident->fetch();
            if (!$case || $case['reported_by'] !== 'resident_sos'
                || $case['reporter_role'] !== 'resident'
                || $case['status'] === 'resolved') {
                throw new FalseReportUnavailableException(
                    'This resident SOS case cannot be closed as a false report.'
                );
            }

            $existing = $pdo->prepare(
                'SELECT id FROM incident_false_reports WHERE incident_id = ? LIMIT 1'
            );
            $existing->execute([$incidentId]);
            if ($existing->fetchColumn() !== false) {
                throw new FalseReportAlreadyExistsException(
                    'This case has already been submitted for false-report review.'
                );
            }

            $insert = $pdo->prepare(
                'INSERT INTO incident_false_reports
                    (incident_id, reporter_user_id, responder_user_id, reason, notes)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $incidentId,
                $case['reporter_user_id'],
                $responderId,
                $reason,
                $notes,
            ]);
            $falseReportId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "UPDATE incidents SET status = 'resolved', resolved_at = NOW()
                 WHERE incident_id = ?"
            )->execute([$incidentId]);

            $pdo->commit();

            return $falseReportId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function pendingForBarangay(int $barangayId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT f.id, f.incident_id, f.reason, f.notes, f.created_at,
                    i.incident_type, i.location, b.name AS barangay_name,
                    u.full_name AS reporter_name, u.work_email AS reporter_email,
                    u.banned_until,
                    (SELECT COUNT(*) FROM incident_false_reports warnings
                     WHERE warnings.reporter_user_id = f.reporter_user_id
                       AND warnings.review_status = 'confirmed') AS warning_count
             FROM incident_false_reports f
             JOIN incidents i ON i.incident_id = f.incident_id
             JOIN user_type u ON u.id = f.reporter_user_id
             LEFT JOIN barangays b ON b.id = i.barangay_id
             WHERE f.review_status = 'pending' AND i.barangay_id = ?
             ORDER BY f.created_at ASC, f.id ASC"
        );
        $stmt->execute([$barangayId]);

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['incident_id'] = (int) $row['incident_id'];
            $row['warning_count'] = (int) $row['warning_count'];

            return $row;
        }, $stmt->fetchAll());
    }

    /**
     * Reviews a pending false-report flag. Returns the resulting warning count and ban expiry.
     *
     * @return array{warning_count:int, banned_until:?string, ban_days:?int, automatic_ban:bool}
     */
    public static function review(
        int $falseReportId,
        int $adminId,
        int $barangayId,
        string $decision,
        bool $banFirstWarning,
        ?int $banDays
    ): array {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT f.id, f.reporter_user_id
                 FROM incident_false_reports f
                 JOIN incidents i ON i.incident_id = f.incident_id
                 WHERE f.id = ? AND f.review_status = \'pending\' AND i.barangay_id = ?
                 FOR UPDATE'
            );
            $stmt->execute([$falseReportId, $barangayId]);
            $report = $stmt->fetch();
            if (!$report) {
                throw new FalseReportUnavailableException(
                    'This pending report is no longer available in your barangay.'
                );
            }

            $reporterStmt = $pdo->prepare(
                'SELECT id, role, banned_until FROM user_type WHERE id = ? FOR UPDATE'
            );
            $reporterStmt->execute([$report['reporter_user_id']]);
            $reporter = $reporterStmt->fetch();
            if (!$reporter || $reporter['role'] !== 'resident') {
                throw new FalseReportUnavailableException(
                    'The resident account for this case is no longer available.'
                );
            }

            if ($decision === 'dismiss') {
                $pdo->prepare(
                    "UPDATE incident_false_reports
                     SET review_status = 'dismissed', reviewed_by_user_id = ?, reviewed_at = NOW()
                     WHERE id = ?"
                )->execute([$adminId, $falseReportId]);
                $pdo->commit();

                return [
                    'warning_count' => self::warningCount($pdo, (int) $report['reporter_user_id']),
                    'banned_until' => $reporter['banned_until'],
                    'ban_days' => null,
                    'automatic_ban' => false,
                ];
            }
            if ($decision !== 'confirm') {
                throw new InvalidArgumentException('Choose confirm or dismiss.');
            }

            $outcome = FalseReportPolicy::confirmed(
                self::warningCount($pdo, (int) $report['reporter_user_id']),
                $banFirstWarning,
                $banDays
            );
            $warningCount = $outcome['warning_number'];
            $automaticBan = $outcome['automatic_ban'];
            $banDays = $outcome['ban_days'];
            $bannedUntil = $banDays === null
                ? null
                : date('Y-m-d H:i:s', time() + $banDays * 86400);

            if ($bannedUntil !== null) {
                $pdo->prepare('UPDATE user_type SET banned_until = ? WHERE id = ?')
                    ->execute([$bannedUntil, $report['reporter_user_id']]);
            }

            $pdo->prepare(
                "UPDATE incident_false_reports
                 SET review_status = 'confirmed', warning_number = ?,
                     reviewed_by_user_id = ?, reviewed_at = NOW(), ban_applied_until = ?
                 WHERE id = ?"
            )->execute([$warningCount, $adminId, $bannedUntil, $falseReportId]);
            $pdo->commit();

            return [
                'warning_count' => $warningCount,
                'banned_until' => $bannedUntil ?? $reporter['banned_until'],
                'ban_days' => $banDays,
                'automatic_ban' => $automaticBan,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function warningCount(PDO $pdo, int $reporterId): int
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM incident_false_reports
             WHERE reporter_user_id = ? AND review_status = 'confirmed'"
        );
        $stmt->execute([$reporterId]);

        return (int) $stmt->fetchColumn();
    }
}

/** Pure false-report escalation rules, independently testable from database access. */
final class FalseReportPolicy
{
    /** @return array{warning_number:int,ban_days:?int,automatic_ban:bool} */
    public static function confirmed(
        int $previousWarnings,
        bool $banFirstWarning,
        ?int $banDays
    ): array
    {
        $warningNumber = $previousWarnings + 1;
        $automaticBan = $warningNumber >= 2;
        if ($banDays !== null && ($banDays < 1 || $banDays > 365)) {
            throw new InvalidArgumentException('Ban duration must be between 1 and 365 days.');
        }
        if (($automaticBan || $banFirstWarning) && $banDays === null) {
            throw new InvalidArgumentException('Choose a ban duration between 1 and 365 days.');
        }

        return [
            'warning_number' => $warningNumber,
            'ban_days' => ($automaticBan || $banFirstWarning) ? $banDays : null,
            'automatic_ban' => $automaticBan,
        ];
    }
}
