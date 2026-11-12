<?php
namespace App\Models;

class OverviewModel
{
    public static function data(): array
    {
        $schemaRemediation = 'Restore the Reddiph backup schema and confirm the standard source tables and columns are present.';
        DatabaseModel::requireColumns('user_type', ['id', 'role'], $schemaRemediation);
        DatabaseModel::requireColumns('hospitals', ['id'], $schemaRemediation);
        DatabaseModel::requireColumns('barangays', ['id'], $schemaRemediation);
        DatabaseModel::requireColumns('incidents', [
            'incident_id', 'incident_type', 'status', 'triage_category', 'location', 'reported_at',
        ], $schemaRemediation);
        DatabaseModel::requireColumns('ambulances', ['status'], $schemaRemediation);
        DatabaseModel::requireColumns('hospital_beds', ['status'], $schemaRemediation);

        $db = DatabaseModel::connection();
        $hospitalConditions = self::activeRecordConditions('hospitals', 'h');
        $barangayConditions = array_merge(
            self::activeRecordConditions('barangays', 'b'),
            self::activeRecordConditions('hospitals', 'h')
        );
        $summary = $db->query(
            "SELECT
                (SELECT COUNT(*) FROM user_type) AS user_count,
                (SELECT COUNT(*) FROM hospitals h"
                    . ($hospitalConditions ? ' WHERE ' . implode(' AND ', $hospitalConditions) : '')
                    . ") AS hospital_count,
                (SELECT COUNT(*) FROM barangays b JOIN hospitals h ON h.id = b.hospital_id"
                    . ($barangayConditions ? ' WHERE ' . implode(' AND ', $barangayConditions) : '')
                    . ") AS barangay_count,
                (SELECT COUNT(*) FROM incidents WHERE status IN ('open', 'dispatched', 'referred')) AS open_incidents,
                (SELECT COUNT(*) FROM incidents
                 WHERE reported_at >= CURRENT_DATE()
                   AND reported_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)) AS incidents_today,
                (SELECT COUNT(*) FROM ambulances WHERE status = 'available') AS available_ambulances,
                (SELECT COUNT(*) FROM hospital_beds WHERE status = 'ready') AS ready_beds"
        )->fetch();
        if (!$summary) {
            throw new \RuntimeException('The overview summary query returned no result.');
        }

        $today = $db->query('SELECT CURRENT_DATE() AS today')->fetchColumn();
        if (!is_string($today) || $today === '') {
            throw new \RuntimeException('The database did not return the current date for the overview.');
        }
        $history = $db->prepare(
            'SELECT DATE(reported_at) AS report_date, COUNT(*) AS incident_count
             FROM incidents
             WHERE reported_at >= DATE_SUB(?, INTERVAL 6 DAY)
               AND reported_at < DATE_ADD(?, INTERVAL 1 DAY)
             GROUP BY DATE(reported_at)'
        );
        $history->execute([$today, $today]);
        $history = $history->fetchAll();
        $historyByDate = [];
        foreach ($history as $row) {
            $historyByDate[$row['report_date']] = (int) $row['incident_count'];
        }

        $chart = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $date = new \DateTimeImmutable($today . " -$offset days");
            $key = $date->format('Y-m-d');
            $chart[] = [
                'label' => $date->format('D'),
                'date' => $key,
                'count' => $historyByDate[$key] ?? 0,
            ];
        }

        $incidents = $db->query(
            'SELECT incident_id, incident_type, status, triage_category, location, reported_at
             FROM incidents ORDER BY reported_at DESC LIMIT 5'
        )->fetchAll();

        $roles = $db->query(
            'SELECT role AS role_key, COUNT(*) AS account_count
             FROM user_type GROUP BY role ORDER BY role'
        )->fetchAll();
        $roleCounts = [];
        $roleLabels = [];
        foreach ($roles as $role) {
            $roleCounts[$role['role_key']] = (int) $role['account_count'];
            $roleLabels[$role['role_key']] = ucwords(str_replace('_', ' ', $role['role_key']));
        }
        $summary['user_count'] = (int) $summary['user_count'];
        $summary['hospital_count'] = (int) $summary['hospital_count'];
        $summary['barangay_count'] = (int) $summary['barangay_count'];
        $summary['open_incidents'] = (int) $summary['open_incidents'];
        $summary['incidents_today'] = (int) $summary['incidents_today'];
        $summary['available_ambulances'] = (int) $summary['available_ambulances'];
        $summary['ready_beds'] = (int) $summary['ready_beds'];

        return [
            'summary' => $summary,
            'roles' => $roleCounts,
            'roleLabels' => $roleLabels,
            'incidentHistory' => $chart,
            'recentIncidents' => $incidents,
        ];
    }

    private static function activeRecordConditions(string $table, string $alias): array
    {
        $conditions = [];
        if (DatabaseModel::hasColumn($table, 'is_active')) {
            $conditions[] = $alias . '.is_active = 1';
        }
        if (DatabaseModel::hasColumn($table, 'archived_at')) {
            $conditions[] = $alias . '.archived_at IS NULL';
        }
        return $conditions;
    }
}
