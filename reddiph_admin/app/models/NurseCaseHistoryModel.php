<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/IncidentTriageCategory.php';

class NurseCaseHistoryModel
{
    private static function connection(): PDO
    {
        return Database::connection();
    }

    private static function filterCriteria(string $filter): array
    {
        $timezone = new DateTimeZone('Asia/Manila');
        if ($filter === 'week') {
            $start = new DateTimeImmutable('monday this week', $timezone);
            $end = $start->modify('+1 week');
        } elseif ($filter === 'month') {
            $start = new DateTimeImmutable('first day of this month', $timezone);
            $end = $start->modify('+1 month');
        } else {
            return ['', []];
        }

        return [
            ' AND alert.created_at >= :period_start AND alert.created_at < :period_end',
            [
                'period_start' => $start->setTime(0, 0)->format('Y-m-d H:i:s'),
                'period_end' => $end->setTime(0, 0)->format('Y-m-d H:i:s'),
            ],
        ];
    }

    private static function notifiedCasesSql(): string
    {
        return "FROM staff_alerts alert
            INNER JOIN incidents i ON i.incident_id = alert.incident_id
            INNER JOIN user_type nurse
                ON nurse.id = alert.recipient_user_id
                AND nurse.role = 'nurse'
                AND (
                    nurse.hospital_id = i.hospital_id
                    OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)
                )
            WHERE alert.recipient_user_id = :nurse_id
              AND alert.recipient_role = 'nurse'
              AND alert.channel = 'admin'
              AND alert.incident_id IS NOT NULL";
    }

    public static function history(int $nurseId, string $filter = 'all'): array
    {
        [$periodSql, $periodParameters] = self::filterCriteria($filter);
        $statement = self::connection()->prepare(
            "SELECT
                i.incident_id,
                i.incident_type,
                i.status,
                i.triage_category,
                i.patient_name,
                MIN(alert.created_at) AS notified_at
            " . self::notifiedCasesSql() . $periodSql . "
            GROUP BY
                i.incident_id,
                i.incident_type,
                i.status,
                i.triage_category,
                i.patient_name
            ORDER BY notified_at DESC, i.incident_id DESC"
        );
        $statement->execute(['nurse_id' => $nurseId] + $periodParameters);

        return array_map(static function (array $row): array {
            $patientName = trim((string) ($row['patient_name'] ?? ''));

            return [
                'id' => (int) $row['incident_id'],
                'date' => (new DateTimeImmutable((string) $row['notified_at'], new DateTimeZone('Asia/Manila')))
                    ->format('M j, Y g:i A'),
                'case' => trim((string) $row['incident_type']),
                'patient' => $patientName !== '' ? $patientName : 'Patient name unavailable',
                'status' => (string) $row['status'],
                'triageCategory' => $row['triage_category'],
                'triageCategoryLabel' => IncidentTriageCategory::label(
                    $row['triage_category'] !== null ? (string) $row['triage_category'] : null
                ),
            ];
        }, $statement->fetchAll());
    }

    public static function stats(int $nurseId, string $filter, int $activeNotifiedCount): array
    {
        [$periodSql, $periodParameters] = self::filterCriteria($filter);
        $baseSql = self::notifiedCasesSql();

        $periodStatement = self::connection()->prepare(
            "SELECT
                COUNT(DISTINCT i.incident_id) AS received,
                COUNT(DISTINCT CASE WHEN i.status = 'resolved' THEN i.incident_id END) AS resolved
            " . $baseSql . $periodSql
        );
        $periodStatement->execute(['nurse_id' => $nurseId] + $periodParameters);
        $periodStats = $periodStatement->fetch() ?: [];
        $received = (int) ($periodStats['received'] ?? 0);
        $resolved = (int) ($periodStats['resolved'] ?? 0);

        return [
            'totalNotified' => $activeNotifiedCount,
            'received' => $received,
            'resolved' => $resolved,
            'resolvedPercent' => $received > 0 ? (int) round(($resolved / $received) * 100) . '%' : '0%',
        ];
    }
}
