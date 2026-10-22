<?php
namespace App\Models;

class OverviewModel
{
    public static function data(): array
    {
        DatabaseModel::requireTables(['app_roles']);
        $db = DatabaseModel::connection();
        $summary = $db->query(
            "SELECT
                (SELECT COUNT(*) FROM user_type) AS user_count,
                (SELECT COUNT(*) FROM hospitals) AS hospital_count,
                (SELECT COUNT(*) FROM barangays) AS barangay_count,
                (SELECT COUNT(*) FROM incidents WHERE status <> 'resolved') AS open_incidents,
                (SELECT COUNT(*) FROM incidents WHERE DATE(reported_at) = CURRENT_DATE()) AS incidents_today,
                (SELECT COUNT(*) FROM ambulances WHERE status = 'available') AS available_ambulances,
                (SELECT COUNT(*) FROM hospital_beds WHERE status = 'ready') AS ready_beds"
        )->fetch();

        $today = $db->query('SELECT CURRENT_DATE() AS today')->fetchColumn();
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
            'SELECT incident_id, incident_type, status, location, reported_at
             FROM incidents ORDER BY reported_at DESC LIMIT 5'
        )->fetchAll();

        $roles = $db->query(
            'SELECT r.role_key, r.display_name, COUNT(u.id) AS account_count
             FROM app_roles r LEFT JOIN user_type u ON u.role = r.role_key
             GROUP BY r.role_key, r.display_name ORDER BY r.display_name'
        )->fetchAll();
        $roleCounts = [];
        $roleLabels = [];
        foreach ($roles as $role) {
            $roleCounts[$role['role_key']] = (int) $role['account_count'];
            $roleLabels[$role['role_key']] = $role['display_name'];
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
}
