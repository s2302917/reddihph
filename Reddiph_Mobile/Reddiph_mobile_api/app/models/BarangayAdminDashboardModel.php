<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

final class BarangayAdminDashboardModel
{
    public static function summary(int $barangayId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT
                SUM(i.reported_at >= CURDATE()) AS sos_today,
                SUM(i.hospital_arrived_at IS NULL AND i.status <> 'resolved') AS in_progress,
                SUM(i.hospital_arrived_at >= CURDATE()) AS closed
             FROM incidents i
             WHERE i.barangay_id = ?"
        );
        $stmt->execute([$barangayId]);
        $counts = $stmt->fetch() ?: [];

        $falseReports = $pdo->prepare(
            'SELECT COUNT(*)
             FROM incident_false_reports fr
             JOIN incidents i ON i.incident_id = fr.incident_id
             WHERE i.barangay_id = ?'
        );
        $falseReports->execute([$barangayId]);

        return [
            'sos_today' => (int) ($counts['sos_today'] ?? 0),
            'in_progress' => (int) ($counts['in_progress'] ?? 0),
            'closed' => (int) ($counts['closed'] ?? 0),
            'false_reports' => (int) $falseReports->fetchColumn(),
        ];
    }

    public static function recentActivity(int $barangayId, int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.incident_id, i.incident_type, i.status, i.location, i.reported_at,
                    i.responder_arrived_at, i.hospital_sent_at, i.patient_picked_up_at,
                    i.hospital_arrived_at, i.responder_name
             FROM incidents i
             WHERE i.barangay_id = ?
             ORDER BY i.updated_at DESC, i.incident_id DESC
             LIMIT 25'
        );
        $stmt->execute([$barangayId]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $case = 'Case #RS-' . (int) $row['incident_id'];
            $events[] = [
                'incident_id' => (int) $row['incident_id'],
                'incident_type' => $row['incident_type'],
                'status' => $row['status'],
                'location' => $row['location'],
                'activity_title' => 'SOS incident reported',
                'message' => $case . ' · ' . (string) $row['incident_type']
                    . ' at ' . (string) $row['location'],
                'occurred_at' => $row['reported_at'],
            ];
            foreach ([
                'responder_arrived_at' => 'Responder arrived at the scene',
                'hospital_sent_at' => 'Responder started transport to the hospital',
                'patient_picked_up_at' => 'Patient picked up',
                'hospital_arrived_at' => 'Patient arrived at the hospital',
            ] as $field => $title) {
                if ($row[$field] !== null) {
                    $events[] = [
                        'incident_id' => (int) $row['incident_id'],
                        'incident_type' => $row['incident_type'],
                        'status' => $row['status'],
                        'location' => $row['location'],
                        'activity_title' => $title,
                        'message' => $case . ' · ' . (string) ($row['responder_name'] ?? 'Responder')
                            . ' · ' . (string) $row['location'],
                        'occurred_at' => $row[$field],
                    ];
                }
            }
        }
        usort($events, static fn (array $a, array $b): int =>
            strcmp((string) $b['occurred_at'], (string) $a['occurred_at'])
        );

        return array_slice($events, 0, max(1, min(10, $limit)));
    }

    public static function notifications(int $barangayId): array
    {
        $pdo = Database::connection();
        $items = [];

        $stmt = $pdo->prepare(
            "SELECT id, role, full_name, created_at
             FROM user_type
             WHERE barangay_id = ? AND approval_status = 'pending'
               AND role IN ('responder','volunteer')
             ORDER BY created_at DESC LIMIT 25"
        );
        $stmt->execute([$barangayId]);
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'id' => 'signup-' . (int) $row['id'],
                'type' => 'signup',
                'title' => 'New ' . ucfirst((string) $row['role']) . ' signup',
                'message' => (string) $row['full_name'] . ' is waiting for approval.',
                'created_at' => $row['created_at'],
            ];
        }

        $stmt = $pdo->prepare(
            "SELECT fr.id, fr.created_at, i.incident_id, i.incident_type, i.location
             FROM incident_false_reports fr
             JOIN incidents i ON i.incident_id = fr.incident_id
             WHERE i.barangay_id = ? AND fr.review_status = 'pending'
             ORDER BY fr.created_at DESC LIMIT 25"
        );
        $stmt->execute([$barangayId]);
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'id' => 'false-report-' . (int) $row['id'],
                'type' => 'false_report',
                'title' => 'False report needs review',
                'message' => 'Case #' . (int) $row['incident_id'] . ' - '
                    . (string) $row['incident_type'] . ' at ' . (string) $row['location'],
                'created_at' => $row['created_at'],
            ];
        }

        $stmt = $pdo->prepare(
            "SELECT alert.id, alert.created_at, alert.incident_id, incident.incident_type,
                    incident.location, responder.full_name AS responder_name
             FROM incident_scene_arrival_alerts alert
             JOIN incidents incident ON incident.incident_id = alert.incident_id
             JOIN user_type admin ON admin.id = alert.barangay_admin_user_id
             JOIN user_type responder ON responder.id = alert.responder_user_id
             WHERE admin.barangay_id = ? AND alert.status = 'queued'
             ORDER BY alert.created_at DESC LIMIT 25"
        );
        $stmt->execute([$barangayId]);
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'id' => 'arrival-' . (int) $row['id'],
                'type' => 'arrival',
                'title' => 'Responder arrived at the scene',
                'message' => (string) $row['responder_name'] . ' arrived for Case #'
                    . (int) $row['incident_id'] . ' (' . (string) $row['location'] . ').',
                'created_at' => $row['created_at'],
            ];
        }

        $summary = self::summary($barangayId);
        $items[] = [
            'id' => 'daily-summary-' . date('Y-m-d'),
            'type' => 'daily_summary',
            'title' => 'Daily summary',
            'message' => $summary['sos_today'] . ' SOS reports today, '
                . $summary['in_progress'] . ' in progress, '
                . $summary['closed'] . ' closed.',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        usort($items, static fn (array $a, array $b): int =>
            strcmp((string) $b['created_at'], (string) $a['created_at'])
        );

        return $items;
    }

    public static function pendingSignups(int $barangayId): array
    {
        return self::signupAccounts($barangayId, 'pending')['items'];
    }

    public static function signupCounts(int $barangayId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT approval_status, COUNT(*) AS total
             FROM user_type
             WHERE barangay_id = ? AND role IN ('responder','volunteer')
             GROUP BY approval_status"
        );
        $stmt->execute([$barangayId]);
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['approval_status']] = (int) $row['total'];
        }

        return $counts;
    }

    public static function signupAccounts(
        int $barangayId,
        string $status,
        int $page = 1,
        int $pageSize = 50
    ): array {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose a valid signup status.');
        }
        $offset = ($page - 1) * $pageSize;
        $stmt = Database::connection()->prepare(
            "SELECT account.id, account.role, account.full_name, account.work_email,
                    account.phone_number, account.age, account.gender,
                    account.hospital_name, account.created_at, account.profile_image,
                    account.approval_status, account.reviewed_at,
                    account.license_number,
                    reviewer.full_name AS reviewed_by_name,
                    barangay.name AS barangay_name,
                    volunteer.vehicle_type
             FROM user_type account
             LEFT JOIN user_type reviewer
                ON reviewer.id = account.reviewed_by_user_id
             LEFT JOIN barangays barangay
                ON barangay.id = account.barangay_id
             LEFT JOIN volunteer_profiles volunteer
                ON volunteer.user_id = account.id
             WHERE account.barangay_id = ? AND account.approval_status = ?
               AND account.role IN ('responder','volunteer')
             ORDER BY account.created_at DESC, account.id DESC
             LIMIT {$pageSize} OFFSET {$offset}"
        );
        $stmt->execute([$barangayId, $status]);
        $items = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['age'] = $row['age'] !== null ? (int) $row['age'] : null;
            return $row;
        }, $stmt->fetchAll());

        $count = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM user_type
             WHERE barangay_id = ? AND approval_status = ?
               AND role IN ('responder','volunteer')
            "
        );
        $count->execute([$barangayId, $status]);
        $total = (int) $count->fetchColumn();

        return [
            'items' => $items,
            'status' => $status,
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => (int) ceil($total / $pageSize),
        ];
    }

    public static function reviewSignup(
        int $accountId,
        int $reviewerId,
        int $barangayId,
        string $decision,
        string $currentStatus = 'pending'
    ): bool {
        $nextStatus = match ([$currentStatus, $decision]) {
            ['pending', 'approve'], ['rejected', 'approve'] => 'approved',
            ['pending', 'reject'], ['approved', 'revoke'] => 'rejected',
            default => throw new InvalidArgumentException(
                'This signup status transition is not allowed.'
            ),
        };

        if (!in_array($currentStatus, ['pending', 'approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose a valid current signup status.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $current = $pdo->prepare(
                "SELECT id FROM user_type
                 WHERE id = ? AND barangay_id = ? AND approval_status = ?
                   AND role IN ('responder','volunteer')
                 FOR UPDATE"
            );
            $current->execute([$accountId, $barangayId, $currentStatus]);
            if (!$current->fetchColumn()) {
                $pdo->rollBack();
                return false;
            }
            $pdo->prepare(
                'UPDATE user_type
                 SET approval_status = ?, reviewed_at = NOW(), reviewed_by_user_id = ?
                 WHERE id = ?'
            )->execute([$nextStatus, $reviewerId, $accountId]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function incidentLogs(
        int $barangayId,
        string $search,
        int $page,
        int $pageSize = 10
    ): array {
        $where = 'i.barangay_id = ?';
        $params = [$barangayId];
        if ($search !== '') {
            $where .= " AND (CAST(i.incident_id AS CHAR) LIKE ? OR i.incident_type LIKE ?
                OR i.location LIKE ? OR i.status LIKE ? OR i.responder_name LIKE ?
                OR COALESCE(reporter.full_name, '') LIKE ?)";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $count = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM incidents i
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             WHERE {$where}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $stmt = Database::connection()->prepare(
            "SELECT i.incident_id, i.incident_type, i.status, i.location, i.reported_at,
                    i.responder_name, i.hospital_arrived_at,
                    COALESCE(reporter.full_name, i.patient_name, 'Unknown reporter') AS reporter_name
             FROM incidents i
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             WHERE {$where}
             ORDER BY i.reported_at DESC, i.incident_id DESC
             LIMIT {$pageSize} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'items' => array_map([self::class, 'normalizeIncident'], $stmt->fetchAll()),
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => (int) ceil($total / $pageSize),
        ];
    }

    public static function exportLogs(
        int $barangayId,
        string $period,
        array $includes
    ): array {
        [$start, $end] = self::exportPeriodBounds($period);
        $pdo = Database::connection();
        $records = [];

        if (in_array('sos', $includes, true)) {
            $stmt = $pdo->prepare(
                'SELECT i.incident_id, i.reported_at AS occurred_at,
                        i.incident_type, i.status, i.location, i.reported_by,
                        reporter.full_name AS reporter_name,
                        reporter.phone_number AS reporter_phone,
                        i.responder_name,
                        COALESCE(hospital.name, i.hospital_name) AS hospital_name
                 FROM incidents i
                 LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
                 LEFT JOIN hospitals hospital ON hospital.id = i.hospital_id
                 WHERE i.barangay_id = ? AND i.reported_at >= ? AND i.reported_at < ?
                 ORDER BY i.reported_at DESC, i.incident_id DESC'
            );
            $stmt->execute([$barangayId, $start, $end]);
            $records['sos'] = $stmt->fetchAll();
        }

        if (in_array('hospital', $includes, true)) {
            $stmt = $pdo->prepare(
                'SELECT i.incident_id,
                        COALESCE(i.hospital_arrived_at, i.hospital_sent_at) AS occurred_at,
                        i.hospital_sent_at, i.hospital_arrived_at,
                        i.incident_type, i.status, i.location,
                        i.responder_name,
                        COALESCE(hospital.name, i.hospital_name) AS hospital_name
                 FROM incidents i
                 LEFT JOIN hospitals hospital ON hospital.id = i.hospital_id
                 WHERE i.barangay_id = ?
                   AND (
                     (i.hospital_arrived_at >= ? AND i.hospital_arrived_at < ?)
                     OR (
                       i.hospital_arrived_at IS NULL
                       AND i.hospital_sent_at >= ? AND i.hospital_sent_at < ?
                     )
                   )
                 ORDER BY occurred_at DESC, i.incident_id DESC'
            );
            $stmt->execute([$barangayId, $start, $end, $start, $end]);
            $records['hospital'] = $stmt->fetchAll();
        }

        if (in_array('volunteers', $includes, true)) {
            $stmt = $pdo->prepare(
                "SELECT id, full_name, work_email, phone_number, hospital_name,
                        approval_status, created_at AS occurred_at
                 FROM user_type
                 WHERE barangay_id = ? AND role = 'volunteer'
                   AND created_at >= ? AND created_at < ?
                 ORDER BY created_at DESC, id DESC"
            );
            $stmt->execute([$barangayId, $start, $end]);
            $records['volunteers'] = $stmt->fetchAll();
        }

        if (in_array('false_reports', $includes, true)) {
            $stmt = $pdo->prepare(
                'SELECT fr.id, fr.incident_id, fr.created_at AS occurred_at,
                        fr.reason, fr.notes, fr.review_status, fr.warning_number,
                        i.incident_type, i.status, i.location,
                        reporter.full_name AS reporter_name,
                        reporter.phone_number AS reporter_phone,
                        responder.full_name AS responder_name
                 FROM incident_false_reports fr
                 JOIN incidents i ON i.incident_id = fr.incident_id
                 LEFT JOIN user_type reporter ON reporter.id = fr.reporter_user_id
                 LEFT JOIN user_type responder ON responder.id = fr.responder_user_id
                 WHERE i.barangay_id = ? AND fr.created_at >= ? AND fr.created_at < ?
                 ORDER BY fr.created_at DESC, fr.id DESC'
            );
            $stmt->execute([$barangayId, $start, $end]);
            $records['false_reports'] = $stmt->fetchAll();
        }

        return [
            'period' => $period,
            'period_start' => $start,
            'period_end_exclusive' => $end,
            'includes' => array_values(array_unique($includes)),
            'records' => $records,
        ];
    }

    private static function exportPeriodBounds(string $period): array
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
        $start = match ($period) {
            'today' => $today,
            'this_week' => $today->modify('monday this week'),
            'this_month' => $today->modify('first day of this month'),
            default => throw new InvalidArgumentException('Choose a valid export period.'),
        };
        $end = match ($period) {
            'today' => $start->modify('+1 day'),
            'this_week' => $start->modify('+1 week'),
            'this_month' => $start->modify('+1 month'),
        };

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    public static function incidentDetail(int $incidentId, int $barangayId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, b.name AS barangay_name, hospital.name AS hospital_display_name,
                    hospital.address AS hospital_address,
                    reporter.full_name AS reporter_name, reporter.work_email AS reporter_email,
                    reporter.phone_number AS reporter_phone,
                    responder.full_name AS assigned_responder_name,
                    responder.work_email AS responder_email,
                    responder.phone_number AS responder_phone,
                    nurse.full_name AS preparation_nurse_name,
                    responder_alert.created_at AS responder_assigned_at,
                    responder_alert.acknowledged_at AS responder_opened_at
             FROM incidents i
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN hospitals hospital ON hospital.id = i.hospital_id
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             LEFT JOIN user_type responder ON responder.id = COALESCE(
                i.assigned_responder_user_id,
                (SELECT candidate.id FROM user_type candidate
                 WHERE candidate.role = \'responder\'
                   AND candidate.barangay_id = i.barangay_id
                   AND candidate.full_name = i.responder_name
                 ORDER BY candidate.id LIMIT 1)
             )
             LEFT JOIN user_type nurse ON nurse.id = i.nurse_prep_completed_by
             LEFT JOIN responder_alerts responder_alert
                ON responder_alert.incident_id = i.incident_id
               AND responder_alert.responder_user_id = responder.id
             WHERE i.incident_id = ? AND i.barangay_id = ? LIMIT 1'
        );
        $stmt->execute([$incidentId, $barangayId]);
        $incident = $stmt->fetch();
        if (!$incident) {
            return null;
        }

        $patientQuery = Database::connection()->prepare(
            'SELECT patient.id, patient.patient_name, patient.patient_age, patient.patient_gender,
                    patient.heart_rate, patient.spo2, patient.nurse_blood_pressure, patient.notes,
                    patient.created_at
             FROM incident_patients patient
             WHERE patient.incident_id = ?
             ORDER BY patient.id'
        );
        $patientQuery->execute([$incidentId]);
        $patients = $patientQuery->fetchAll();
        $conditions = Database::connection()->prepare(
            'SELECT condition_code FROM incident_patient_conditions WHERE patient_id = ? ORDER BY condition_code'
        );
        foreach ($patients as &$patient) {
            $conditions->execute([(int) $patient['id']]);
            $patient['conditions'] = array_column($conditions->fetchAll(), 'condition_code');
            foreach (['id', 'patient_age', 'heart_rate', 'spo2'] as $field) {
                if ($patient[$field] !== null) {
                    $patient[$field] = (int) $patient[$field];
                }
            }
        }
        unset($patient);

        $falseReport = Database::connection()->prepare(
            'SELECT reason, notes, review_status, created_at, reviewed_at
             FROM incident_false_reports WHERE incident_id = ? LIMIT 1'
        );
        $falseReport->execute([$incidentId]);

        return [
            'incident_id' => (int) $incident['incident_id'],
            'incident_type' => $incident['incident_type'],
            'status' => $incident['status'],
            'location' => $incident['location'],
            'barangay' => $incident['barangay_name'],
            'reported_at' => $incident['reported_at'],
            'resolved_at' => $incident['resolved_at'],
            'responder_assigned_at' => $incident['responder_assigned_at'],
            'responder_opened_at' => $incident['responder_opened_at'],
            'patient_summary' => [
                'name' => $incident['patient_name'],
                'age' => $incident['patient_age'] !== null ? (int) $incident['patient_age'] : null,
                'gender' => $incident['patient_gender'],
                'eta_minutes' => $incident['eta_minutes'] !== null ? (int) $incident['eta_minutes'] : null,
            ],
            'responder_arrived_at' => $incident['responder_arrived_at'],
            'hospital_sent_at' => $incident['hospital_sent_at'],
            'patient_picked_up_at' => $incident['patient_picked_up_at'],
            'hospital_arrived_at' => $incident['hospital_arrived_at'],
            'reporter' => [
                'name' => $incident['reporter_name'] ?? $incident['patient_name'],
                'email' => $incident['reporter_email'],
                'phone' => $incident['reporter_phone'],
            ],
            'patients' => $patients,
            'responder' => [
                'name' => $incident['assigned_responder_name'] ?? $incident['responder_name'],
                'email' => $incident['responder_email'],
                'phone' => $incident['responder_phone'],
            ],
            'hospital' => $incident['hospital_display_name'] ?? $incident['hospital_name'],
            'hospital_address' => $incident['hospital_address'],
            'transport' => [
                'name' => $incident['transport_name'],
                'type' => $incident['transport_type'],
                'driver' => $incident['driver_name'],
            ],
            'nurse_preparation' => [
                'completed' => (bool) $incident['nurse_prep_completed'],
                'completed_at' => $incident['nurse_prep_completed_at'],
                'nurse_name' => $incident['preparation_nurse_name'],
                'heart_rate' => $incident['nurse_heart_rate'] !== null
                    ? (int) $incident['nurse_heart_rate']
                    : null,
                'blood_pressure' => $incident['nurse_blood_pressure'],
                'spo2' => $incident['nurse_spo2'] !== null
                    ? (int) $incident['nurse_spo2']
                    : null,
                'gcs' => $incident['nurse_gcs'] !== null
                    ? (int) $incident['nurse_gcs']
                    : null,
                'notes' => $incident['nurse_notes'],
            ],
            'notes' => $incident['notes'],
            'false_report' => $falseReport->fetch() ?: null,
        ];
    }

    private static function normalizeIncident(array $row): array
    {
        return [
            'incident_id' => (int) $row['incident_id'],
            'incident_type' => (string) $row['incident_type'],
            'status' => (string) $row['status'],
            'location' => (string) $row['location'],
            'reported_at' => $row['reported_at'],
            'hospital_arrived_at' => $row['hospital_arrived_at'] ?? null,
            'responder_name' => $row['responder_name'] ?? null,
            'reporter_name' => $row['reporter_name'] ?? null,
        ];
    }
}
