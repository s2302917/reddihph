<?php

require_once __DIR__ . '/../../config/config.php';

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
            ' AND i.reported_at >= :period_start AND i.reported_at < :period_end',
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
            WHERE EXISTS (
                SELECT 1
                FROM staff_alerts alert
                INNER JOIN user_type doctor
                  ON doctor.id = alert.recipient_user_id
                  AND doctor.role = 'doctor'
                WHERE alert.incident_id = i.incident_id
                AND alert.recipient_user_id = :doctor_id
                AND alert.recipient_role = 'doctor'
                AND alert.channel = 'admin'
            )";
    }

    public static function history(int $doctorId, string $filter = 'all'): array
    {
        [$periodSql, $periodParameters] = self::filterCriteria($filter);
        $statement = self::connection()->prepare(
            "SELECT
                i.incident_id,
                i.incident_type,
                i.status,
                i.patient_name,
                i.reported_at
            " . self::notifiedCasesSql() . $periodSql . "
            ORDER BY i.reported_at DESC, i.incident_id DESC"
        );
        $statement->execute(['doctor_id' => $doctorId] + $periodParameters);

        return array_map(static function (array $row): array {
            $patientName = trim((string) ($row['patient_name'] ?? ''));

            return [
                'id' => (int) $row['incident_id'],
                'date' => (new DateTimeImmutable((string) $row['reported_at'], new DateTimeZone('Asia/Manila')))->format('M j, Y g:i A'),
                'case' => trim((string) $row['incident_type']),
                'patient' => $patientName !== '' ? $patientName : 'Patient name unavailable',
                'status' => (string) $row['status'],
            ];
        }, $statement->fetchAll());
    }

    public static function stats(int $doctorId, string $filter = 'all'): array
    {
        [$periodSql, $periodParameters] = self::filterCriteria($filter);
        $baseSql = self::notifiedCasesSql();
        $connection = self::connection();

        $totalStatement = $connection->prepare('SELECT COUNT(*) ' . $baseSql);
        $totalStatement->execute(['doctor_id' => $doctorId]);
        $totalNotified = (int) $totalStatement->fetchColumn();

        $periodStatement = $connection->prepare(
            'SELECT
                COUNT(*) AS received,
                COALESCE(SUM(i.status = \'resolved\'), 0) AS resolved
            ' . $baseSql . $periodSql
        );
        $periodStatement->execute(['doctor_id' => $doctorId] + $periodParameters);
        $periodStats = $periodStatement->fetch() ?: [];
        $received = (int) ($periodStats['received'] ?? 0);
        $resolved = (int) ($periodStats['resolved'] ?? 0);

        return [
            'totalNotified' => $totalNotified,
            'received' => $received,
            'resolved' => $resolved,
            'resolvedPercent' => $received > 0 ? (int) round(($resolved / $received) * 100) . '%' : '0%',
        ];
    }

    public static function updateStatus(int $doctorId, int $incidentId, string $status): bool
    {
        if (
            $doctorId <= 0 ||
            $incidentId <= 0 ||
            !in_array($status, ['critical', 'stable', 'resolved'], true)
        ) {
            return false;
        }

        $connection = self::connection();
        $authorization = $connection->prepare(
            "SELECT 1
            FROM staff_alerts
            WHERE incident_id = :incident_id
              AND recipient_user_id = :doctor_id
              AND recipient_role = 'doctor'
            LIMIT 1"
        );
        $authorization->execute([
            'incident_id' => $incidentId,
            'doctor_id' => $doctorId,
        ]);
        if (!$authorization->fetchColumn()) {
            return false;
        }

        $update = $connection->prepare(
            'UPDATE incidents SET status = :status WHERE incident_id = :incident_id LIMIT 1'
        );
        $update->execute([
            'status' => $status,
            'incident_id' => $incidentId,
        ]);

        if ($update->rowCount() > 0) {
            return true;
        }

        $currentStatus = $connection->prepare(
            'SELECT status FROM incidents WHERE incident_id = :incident_id LIMIT 1'
        );
        $currentStatus->execute(['incident_id' => $incidentId]);

        return $currentStatus->fetchColumn() === $status;
    }
}
