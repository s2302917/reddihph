<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/IncidentTriageCategory.php';

class DoctorCaseHistoryModel
{
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
            ' AND notification.notified_at >= :period_start AND notification.notified_at < :period_end',
            [
                'period_start' => $start->setTime(0, 0)->format('Y-m-d H:i:s'),
                'period_end' => $end->setTime(0, 0)->format('Y-m-d H:i:s'),
            ],
        ];
    }

    private static function connection(): PDO
    {
        return Database::connection();
    }

    private static function notifiedCasesSql(): string
    {
        return "FROM incidents i
            INNER JOIN user_type doctor
              ON doctor.id = :doctor_id
              AND doctor.role = 'doctor'
              AND doctor.hospital_id = i.hospital_id
            INNER JOIN (
                SELECT alert.incident_id, MIN(alert.created_at) AS notified_at
                FROM staff_alerts alert
                WHERE alert.recipient_user_id = :notification_doctor_id
                  AND alert.recipient_role = 'doctor'
                  AND alert.channel = 'admin'
                GROUP BY alert.incident_id
            ) notification ON notification.incident_id = i.incident_id";
    }

    public static function history(int $doctorId, string $filter = 'all'): array
    {
        [$periodSql, $periodParameters] = self::filterCriteria($filter);
        $statement = self::connection()->prepare(
            "SELECT
                i.incident_id,
                i.incident_type,
                i.status,
                i.triage_category,
                i.patient_name,
                notification.notified_at
            " . self::notifiedCasesSql() . $periodSql . "
            ORDER BY notification.notified_at DESC, i.incident_id DESC"
        );
        $statement->execute([
            'doctor_id' => $doctorId,
            'notification_doctor_id' => $doctorId,
        ] + $periodParameters);

        return array_map(static function (array $row): array {
            $patientName = trim((string) ($row['patient_name'] ?? ''));

            return [
                'id' => (int) $row['incident_id'],
                'date' => (new DateTimeImmutable((string) $row['notified_at'], new DateTimeZone('Asia/Manila')))->format('M j, Y g:i A'),
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

    public static function stats(int $doctorId, string $filter, int $activeNotifiedCount): array
    {
        [$periodSql, $periodParameters] = self::filterCriteria($filter);
        $baseSql = self::notifiedCasesSql();
        $connection = self::connection();
        $doctorParameters = [
            'doctor_id' => $doctorId,
            'notification_doctor_id' => $doctorId,
        ];

        $periodStatement = $connection->prepare(
            'SELECT
                COUNT(*) AS received,
                COALESCE(SUM(i.status = \'resolved\'), 0) AS resolved
            ' . $baseSql . $periodSql
        );
        $periodStatement->execute($doctorParameters + $periodParameters);
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
