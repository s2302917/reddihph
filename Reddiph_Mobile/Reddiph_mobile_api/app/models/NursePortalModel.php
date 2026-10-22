<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

final class NursePortalModel
{
    private const DEFAULT_TASKS = [
        'ER bay reserved',
        'Blood type on file',
        'Doctor on standby',
        'Oxygen ready',
        'Trauma team notified',
    ];

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function profile(int $nurseId): array
    {
        $statement = self::db()->prepare(
            "SELECT u.id, u.full_name, u.hospital_id, u.license_number,
                COALESCE(h.name, u.hospital_name) AS hospital_name,
                COALESCE(s.department, 'Nurse') AS department,
                COALESCE(s.is_available, 1) AS is_available,
                COALESCE(p.notifications_enabled, 1) AS notifications_enabled,
                COALESCE(p.language, 'English') AS language,
                (SELECT MIN(TIMESTAMP(ss.shift_date, ss.shift_start))
                 FROM staff_shifts ss
                 INNER JOIN staff schedule_staff ON schedule_staff.id = ss.staff_id
                 WHERE schedule_staff.id = s.id
                   AND ss.shift_date >= CURRENT_DATE()
                   AND TIMESTAMP(ss.shift_date, ss.shift_start) > NOW()
                   AND ss.shift_start IS NOT NULL) AS next_shift
             FROM user_type u
             LEFT JOIN hospitals h ON h.id = u.hospital_id
             LEFT JOIN staff s ON s.id = (
                SELECT matching_staff.id FROM staff matching_staff
                WHERE matching_staff.user_id = u.id
                  AND (matching_staff.hospital_id = u.hospital_id OR matching_staff.hospital_id IS NULL)
                ORDER BY (matching_staff.hospital_id = u.hospital_id) DESC, matching_staff.id DESC
                LIMIT 1
             )
             LEFT JOIN nurse_mobile_preferences p ON p.nurse_id = u.id
             WHERE u.id = :nurse_id AND u.role = 'nurse'
             LIMIT 1"
        );
        $statement->execute(['nurse_id' => $nurseId]);
        $profile = $statement->fetch();
        if (!$profile) {
            throw new RuntimeException('Nurse account not found.');
        }
        $profile['id'] = (int) $profile['id'];
        $profile['hospital_id'] = $profile['hospital_id'] === null ? null : (int) $profile['hospital_id'];
        if ($profile['hospital_id'] === null) {
            throw new RuntimeException('No hospital is assigned to this nurse account.');
        }
        $profile['is_available'] = (bool) $profile['is_available'];
        $profile['notifications_enabled'] = (bool) $profile['notifications_enabled'];

        return $profile;
    }

    public static function dashboard(int $nurseId): array
    {
        $profile = self::profile($nurseId);
        $cases = self::cases($nurseId);
        $incoming = count(array_filter($cases, static fn (array $case): bool => $case['status'] === 'en_route'));
        $completed = count(array_filter($cases, static fn (array $case): bool => $case['prep_complete']));
        $beds = self::beds($nurseId);
        $freeEr = count(array_filter($beds, static fn (array $bed): bool => $bed['type'] === 'er' && $bed['status'] === 'ready'));
        $freePediatric = count(array_filter($beds, static fn (array $bed): bool => $bed['type'] === 'pediatric' && $bed['status'] === 'ready'));
        $arrivals = array_values(array_filter(
            $cases,
            static fn (array $case): bool => $case['status'] === 'en_route' && $case['eta_minutes'] !== null
        ));
        usort($arrivals, static fn (array $a, array $b): int => $a['eta_minutes'] <=> $b['eta_minutes']);

        return [
            'profile' => $profile,
            'cases' => $cases,
            'summary' => [
                'incoming' => $incoming,
                'prep_complete' => $completed,
                'prep_total' => count($cases),
                'free_er_beds' => $freeEr,
                'free_pediatric_beds' => $freePediatric,
                'next_arrival' => $arrivals[0] ?? null,
            ],
        ];
    }

    public static function cases(int $nurseId): array
    {
        $profile = self::profile($nurseId);
        self::seedCaseTasks($nurseId, (int) $profile['hospital_id']);
        $statement = self::db()->prepare(
            "SELECT DISTINCT i.incident_id, i.incident_type, i.status AS incident_status,
                i.location, i.patient_name, i.patient_gender, i.patient_age,
                i.eta_minutes, i.responder_name, i.transport_name, i.transport_type,
                i.reported_at, i.notes, i.nurse_notes, i.nurse_heart_rate,
                i.nurse_spo2, i.nurse_blood_pressure, i.nurse_bed_id,
                COALESCE(a.status, 'awaiting_transport') AS transport_status,
                COALESCE(a.eta_minutes, i.eta_minutes) AS live_eta,
                COALESCE(bed.bed_type, '') AS bed_type,
                bed.bed_number,
                COALESCE(lifecycle.arrived_at, a.arrived_at) AS arrived_at,
                lifecycle.arrived_at AS lifecycle_arrived_at,
                lifecycle.handed_off_at,
                (SELECT MIN(read_alert.status = 'read')
                 FROM staff_alerts read_alert
                 WHERE read_alert.incident_id = i.incident_id
                   AND read_alert.recipient_user_id = nurse.id
                   AND read_alert.recipient_role = 'nurse'
                   AND read_alert.channel = 'admin') AS is_seen
             FROM incidents i
             INNER JOIN user_type nurse ON nurse.id = :nurse_id AND nurse.role = 'nurse'
             INNER JOIN staff_alerts alert
                ON alert.incident_id = i.incident_id
               AND alert.recipient_user_id = nurse.id
               AND alert.recipient_role = 'nurse'
               AND alert.channel = 'admin'
             LEFT JOIN ambulances a
                ON a.call_sign = i.transport_name
               AND a.hospital_id = nurse.hospital_id
             LEFT JOIN hospital_beds bed
                ON bed.id = i.nurse_bed_id AND bed.hospital_id = nurse.hospital_id
             LEFT JOIN nurse_case_lifecycle lifecycle ON lifecycle.incident_id = i.incident_id
             WHERE (nurse.hospital_id = i.hospital_id
                    OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name))
               AND i.status IN ('critical','urgent','dispatched','stable')
               AND EXISTS (SELECT 1 FROM incident_patients p WHERE p.incident_id = i.incident_id)
               AND COALESCE((
                    SELECT current_staff.is_available
                    FROM staff current_staff
                    WHERE current_staff.user_id = nurse.id
                      AND (current_staff.hospital_id = nurse.hospital_id
                           OR current_staff.hospital_id IS NULL)
                    ORDER BY (current_staff.hospital_id = nurse.hospital_id) DESC,
                             current_staff.id DESC
                    LIMIT 1
               ), 1) = 1
               AND EXISTS (
                    SELECT 1
                    FROM staff duty_staff
                    INNER JOIN staff_shifts duty_shift
                        ON duty_shift.staff_id = duty_staff.id
                    WHERE duty_staff.user_id = nurse.id
                      AND (duty_staff.hospital_id = nurse.hospital_id
                           OR duty_staff.hospital_id IS NULL)
                      AND alert.created_at >= TIMESTAMP(duty_shift.shift_date, duty_shift.shift_start)
                      AND alert.created_at < DATE_ADD(
                            TIMESTAMP(duty_shift.shift_date, duty_shift.shift_end),
                            INTERVAL IF(duty_shift.shift_end <= duty_shift.shift_start, 1, 0) DAY
                      )
               )
             ORDER BY FIELD(incident_status, 'critical','urgent','dispatched','stable'),
                      i.reported_at DESC, i.incident_id DESC"
        );
        $statement->execute(['nurse_id' => $nurseId]);
        $cases = [];
        foreach ($statement->fetchAll() as $row) {
            $id = (int) $row['incident_id'];
            $tasks = self::tasks($id);
            $taskDone = count(array_filter($tasks, static fn (array $task): bool => $task['done']));
            $transportStatus = (string) $row['transport_status'];
            $status = $row['handed_off_at'] !== null
                ? 'handed_off'
                : ($row['arrived_at'] !== null || $transportStatus === 'arrived'
                    ? 'arrived'
                    : ($transportStatus === 'en_route' ? 'en_route' : 'notified'));
            $cases[] = [
                'id' => $id,
                'title' => (string) $row['incident_type'],
                'priority' => $row['incident_status'] === 'dispatched' ? 'Urgent' : ucfirst((string) $row['incident_status']),
                'status' => $status,
                'patient_name' => self::patientName($row),
                'patient_age' => $row['patient_age'] === null ? null : (int) $row['patient_age'],
                'patient_gender' => (string) ($row['patient_gender'] ?? 'unknown'),
                'location' => (string) $row['location'],
                'eta_minutes' => $row['live_eta'] === null ? null : (int) $row['live_eta'],
                'responder' => trim((string) ($row['responder_name'] ?? '')) ?: 'Responder not assigned',
                'unit' => trim((string) ($row['transport_name'] ?? '')) ?: 'Unit not assigned',
                'transport_type' => (string) ($row['transport_type'] ?? ''),
                'bed_id' => $row['nurse_bed_id'] === null ? null : (int) $row['nurse_bed_id'],
                'bed_label' => $row['bed_number'] === null ? null :
                    (($row['bed_type'] === 'er' ? 'ER-' : 'PD-') . (int) $row['bed_number']),
                'note' => (string) ($row['nurse_notes'] ?? ''),
                'responder_notes' => (string) ($row['notes'] ?? ''),
                'heart_rate' => $row['nurse_heart_rate'] === null ? null : (int) $row['nurse_heart_rate'],
                'spo2' => $row['nurse_spo2'] === null ? null : (int) $row['nurse_spo2'],
                'blood_pressure' => $row['nurse_blood_pressure'],
                'tasks' => $tasks,
                'prep_done' => $taskDone,
                'prep_total' => count($tasks),
                'prep_complete' => count($tasks) > 0 && $taskDone === count($tasks),
                'unseen' => !(bool) $row['is_seen'],
                'reported_at' => (string) $row['reported_at'],
                'arrived_at' => $row['arrived_at'],
                'arrival_confirmed' => $row['lifecycle_arrived_at'] !== null,
            ];
        }

        return $cases;
    }

    public static function markSeen(int $nurseId, int $incidentId): bool
    {
        $statement = self::db()->prepare(
            "UPDATE staff_alerts SET status = 'read'
             WHERE incident_id = :incident_id AND recipient_user_id = :nurse_id
               AND recipient_role = 'nurse' AND channel = 'admin'"
        );
        $statement->execute(['incident_id' => $incidentId, 'nurse_id' => $nurseId]);

        return $statement->rowCount() > 0 || self::hasCaseAccess($nurseId, $incidentId);
    }

    public static function toggleTask(int $nurseId, int $incidentId, int $taskId, bool $done): ?array
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            if (!self::hasCaseAccess($nurseId, $incidentId, true)) {
                $db->rollBack();
                return null;
            }
            $stmt = $db->prepare(
                'SELECT t.task_name FROM nurse_case_prep_tasks t
                 INNER JOIN incidents i ON i.incident_id = t.incident_id
                 INNER JOIN user_type u ON u.id = :nurse_id AND u.role = \'nurse\'
                 WHERE t.id = :task_id AND t.incident_id = :incident_id
                   AND (i.hospital_id = u.hospital_id
                        OR (i.hospital_id IS NULL AND i.hospital_name = u.hospital_name))
                 FOR UPDATE'
            );
            $stmt->execute(['nurse_id' => $nurseId, 'task_id' => $taskId, 'incident_id' => $incidentId]);
            $taskName = $stmt->fetchColumn();
            if ($taskName === false) {
                $db->rollBack();
                return null;
            }
            $db->prepare(
                'UPDATE nurse_case_prep_tasks
                 SET is_done = :done, done_by = :done_by, done_at = :done_at
                 WHERE id = :task_id'
            )->execute([
                'done' => $done ? 1 : 0,
                'done_by' => $done ? $nurseId : null,
                'done_at' => $done ? date('Y-m-d H:i:s') : null,
                'task_id' => $taskId,
            ]);
            self::syncPrepCompletion($db, $nurseId, $incidentId);
            self::audit($db, $nurseId, 'prep_task_updated', 'prep_task', $taskId, [
                'incident_id' => $incidentId,
                'task_name' => $taskName,
                'done' => $done,
            ]);
            $db->commit();

            return self::caseDetails($nurseId, $incidentId);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function saveNote(int $nurseId, int $incidentId, string $note): ?array
    {
        if (strlen($note) > 2000) {
            throw new InvalidArgumentException('Clinical note must be 2,000 characters or fewer.');
        }
        if (!self::hasCaseAccess($nurseId, $incidentId, true)) {
            return null;
        }
        $db = self::db();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE incidents SET nurse_notes = :note WHERE incident_id = :incident_id')
                ->execute(['note' => $note, 'incident_id' => $incidentId]);
            self::audit($db, $nurseId, 'clinical_note_saved', 'incident', $incidentId);
            $db->commit();
            return self::caseDetails($nurseId, $incidentId);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function assignBed(int $nurseId, int $incidentId, int $bedId): ?array
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            if (!self::hasCaseAccess($nurseId, $incidentId, true)) {
                $db->rollBack();
                return null;
            }
            $current = $db->prepare('SELECT nurse_bed_id FROM incidents WHERE incident_id = ?');
            $current->execute([$incidentId]);
            $initialBedId = $current->fetchColumn();
            if ($initialBedId !== null && $initialBedId !== false && (int) $initialBedId === $bedId) {
                $db->commit();
                return self::caseDetails($nurseId, $incidentId);
            }
            $bed = $db->prepare(
                "SELECT b.status, b.hospital_id,
                    (SELECT assigned.incident_id FROM incidents assigned
                     WHERE assigned.nurse_bed_id = b.id
                       AND assigned.status IN ('critical','urgent','dispatched','stable')
                     LIMIT 1) AS incident_id
                 FROM hospital_beds b
                 INNER JOIN user_type u ON u.id = :nurse_id AND u.role = \'nurse\'
                 WHERE b.id = :bed_id AND b.hospital_id = u.hospital_id FOR UPDATE"
            );
            $bed->execute(['nurse_id' => $nurseId, 'bed_id' => $bedId]);
            $bedDetails = $bed->fetch();
            if (!$bedDetails || $bedDetails['status'] !== 'ready') {
                throw new InvalidArgumentException('That bed is no longer available. Refresh and choose another bed.');
            }
            if ($bedDetails['incident_id'] !== null) {
                throw new InvalidArgumentException('That bed is already assigned to an active patient.');
            }
            $oldBedStatus = null;
            if ($initialBedId !== null && $initialBedId !== false) {
                $oldBed = $db->prepare('SELECT status FROM hospital_beds WHERE id = ? FOR UPDATE');
                $oldBed->execute([(int) $initialBedId]);
                $oldBedStatus = $oldBed->fetchColumn();
            }
            $current = $db->prepare(
                'SELECT nurse_bed_id FROM incidents WHERE incident_id = ? FOR UPDATE'
            );
            $current->execute([$incidentId]);
            $oldBedId = $current->fetchColumn();
            if (($oldBedId === null || $oldBedId === false) !== ($initialBedId === null || $initialBedId === false)
                || ($oldBedId !== null && $oldBedId !== false
                    && (int) $oldBedId !== (int) $initialBedId)) {
                throw new InvalidArgumentException('The bed assignment changed. Refresh and try again.');
            }
            if ($oldBedId !== null && $oldBedId !== false && (int) $oldBedId === $bedId) {
                $db->commit();
                return self::caseDetails($nurseId, $incidentId);
            }
            if ($oldBedId !== false && $oldBedId !== null) {
                if ($oldBedStatus !== 'preparing') {
                    throw new InvalidArgumentException('The case already has an occupied bed assigned.');
                }
                $db->prepare("UPDATE hospital_beds SET status = 'ready' WHERE id = ? AND status = 'preparing'")
                    ->execute([(int) $oldBedId]);
            }
            $db->prepare("UPDATE hospital_beds SET status = 'preparing' WHERE id = ?")
                ->execute([$bedId]);
            $db->prepare('UPDATE incidents SET nurse_bed_id = ? WHERE incident_id = ?')
                ->execute([$bedId, $incidentId]);
            self::completeFirstPrepTask($db, $nurseId, $incidentId);
            self::syncPrepCompletion($db, $nurseId, $incidentId);
            self::audit($db, $nurseId, 'bed_assigned', 'bed', $bedId, ['incident_id' => $incidentId]);
            self::syncBedAggregates($db, (int) $bedDetails['hospital_id']);
            $db->commit();

            return self::caseDetails($nurseId, $incidentId);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function confirmArrival(int $nurseId, int $incidentId): ?array
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            if (!self::hasCaseAccess($nurseId, $incidentId, true)) {
                $db->rollBack();
                return null;
            }
            $stmt = $db->prepare(
                'SELECT i.nurse_bed_id, u.hospital_id, ambulance.status AS ambulance_status,
                    lifecycle.arrived_at AS lifecycle_arrived_at
                 FROM incidents i
                 INNER JOIN user_type u ON u.id = :nurse_id AND u.role = \'nurse\'
                 LEFT JOIN ambulances ambulance
                    ON ambulance.call_sign = i.transport_name
                   AND ambulance.hospital_id = u.hospital_id
                 LEFT JOIN nurse_case_lifecycle lifecycle
                    ON lifecycle.incident_id = i.incident_id
                 WHERE i.incident_id = :incident_id'
            );
            $stmt->execute(['nurse_id' => $nurseId, 'incident_id' => $incidentId]);
            $case = $stmt->fetch();
            if (!$case || $case['nurse_bed_id'] === null) {
                throw new InvalidArgumentException('Assign a bed before confirming arrival.');
            }
            if ($case['lifecycle_arrived_at'] === null
                && $case['ambulance_status'] !== 'arrived') {
                throw new InvalidArgumentException('Confirm arrival after the transport is marked arrived.');
            }
            $bed = $db->prepare(
                'SELECT status FROM hospital_beds
                 WHERE id = ? AND hospital_id = ? FOR UPDATE'
            );
            $bed->execute([(int) $case['nurse_bed_id'], (int) $case['hospital_id']]);
            $bedStatus = $bed->fetchColumn();
            if (!in_array($bedStatus, ['preparing', 'occupied'], true)) {
                throw new InvalidArgumentException('The assigned bed is no longer reserved.');
            }
            $caseLock = $db->prepare(
                'SELECT nurse_bed_id FROM incidents WHERE incident_id = ? FOR UPDATE'
            );
            $caseLock->execute([$incidentId]);
            if ((int) $caseLock->fetchColumn() !== (int) $case['nurse_bed_id']) {
                throw new InvalidArgumentException('The bed assignment changed. Refresh and try again.');
            }
            $db->prepare(
                "INSERT INTO nurse_case_lifecycle (incident_id, arrived_at, arrived_by)
                 VALUES (?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE arrived_at = COALESCE(arrived_at, NOW()),
                                         arrived_by = COALESCE(arrived_by, VALUES(arrived_by))"
            )->execute([$incidentId, $nurseId]);
            if ($bedStatus === 'preparing') {
                $db->prepare(
                    "UPDATE hospital_beds SET status = 'occupied'
                     WHERE id = ? AND hospital_id = ? AND status = 'preparing'"
                )->execute([(int) $case['nurse_bed_id'], (int) $case['hospital_id']]);
            }
            self::audit($db, $nurseId, 'arrival_confirmed', 'incident', $incidentId);
            self::syncBedAggregates($db, (int) $case['hospital_id']);
            $db->commit();

            return self::caseDetails($nurseId, $incidentId);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function beds(int $nurseId): array
    {
        $profile = self::profile($nurseId);
        self::initializeBeds((int) $profile['hospital_id']);
        $stmt = self::db()->prepare(
            "SELECT b.id, b.bed_type, b.bed_number, b.status,
                i.incident_id, i.patient_name, i.patient_age, i.patient_gender
             FROM hospital_beds b
             INNER JOIN user_type u ON u.id = :nurse_id AND u.role = 'nurse'
             LEFT JOIN incidents i ON i.nurse_bed_id = b.id
               AND i.status IN ('critical','urgent','dispatched','stable')
               AND EXISTS (
                    SELECT 1 FROM staff_alerts case_alert
                    WHERE case_alert.incident_id = i.incident_id
                      AND case_alert.recipient_user_id = u.id
                      AND case_alert.recipient_role = 'nurse'
                      AND case_alert.channel = 'admin'
               )
             WHERE b.hospital_id = u.hospital_id
             ORDER BY FIELD(b.bed_type,'er','pediatric'), b.bed_number"
        );
        $stmt->execute(['nurse_id' => $nurseId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'type' => (string) $row['bed_type'],
            'number' => (int) $row['bed_number'],
            'label' => ($row['bed_type'] === 'er' ? 'ER-' : 'PD-') . (int) $row['bed_number'],
            'status' => (string) $row['status'],
            'incident_id' => $row['incident_id'] === null ? null : (int) $row['incident_id'],
            'patient_name' => $row['patient_name'],
        ], $stmt->fetchAll());
    }

    private static function initializeBeds(int $hospitalId): void
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $hospitalQuery = $db->prepare(
                'SELECT id, available_beds, total_beds
                 FROM hospitals WHERE id = ? LIMIT 1 FOR UPDATE'
            );
            $hospitalQuery->execute([$hospitalId]);
            $hospital = $hospitalQuery->fetch();
            if (!$hospital) {
                throw new RuntimeException('Hospital record was not found.');
            }
            $count = $db->prepare('SELECT COUNT(*) FROM hospital_beds WHERE hospital_id = ?');
            $count->execute([$hospitalId]);
            if ((int) $count->fetchColumn() === 0) {
                $total = (int) $hospital['total_beds'];
                $available = (int) $hospital['available_beds'];
                if ($total > 10000 || $available > $total) {
                    throw new RuntimeException('Hospital bed capacity is invalid.');
                }
                $erTotal = (int) ceil($total / 2);
                $erAvailable = (int) ceil($available / 2);
                $pediatricAvailable = (int) floor($available / 2);
                $insert = $db->prepare(
                    'INSERT INTO hospital_beds (hospital_id,bed_type,bed_number,status)
                     VALUES (?,?,?,?)'
                );
                for ($number = 1; $number <= $erTotal; $number++) {
                    $insert->execute([
                        $hospitalId,
                        'er',
                        $number,
                        $number <= $erAvailable ? 'ready' : 'occupied',
                    ]);
                }
                $pediatricTotal = $total - $erTotal;
                for ($number = 1; $number <= $pediatricTotal; $number++) {
                    $insert->execute([
                        $hospitalId,
                        'pediatric',
                        $number,
                        $number <= $pediatricAvailable ? 'ready' : 'occupied',
                    ]);
                }
            }
            self::syncBedAggregates($db, $hospitalId);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function syncBedAggregates(PDO $db, int $hospitalId): void
    {
        $db->prepare(
            "UPDATE hospitals
             SET available_beds = (
                SELECT COUNT(*) FROM hospital_beds
                WHERE hospital_id = :beds_hospital_id
                  AND status IN ('ready', 'preparing')
             )
             WHERE id = :hospital_id"
        )->execute([
            'beds_hospital_id' => $hospitalId,
            'hospital_id' => $hospitalId,
        ]);
        $db->prepare(
            "INSERT INTO facility_status (hospital_id,metric_key,current_value,max_value,status_label)
             SELECT h.id, 'bed_capacity',
                (SELECT COUNT(*) FROM hospital_beds b
                 WHERE b.hospital_id = h.id AND b.status IN ('ready','preparing')),
                h.total_beds, NULL
             FROM hospitals h
             WHERE h.id = :hospital_id
             ON DUPLICATE KEY UPDATE
                current_value = VALUES(current_value),
                max_value = VALUES(max_value),
                status_label = VALUES(status_label)"
        )->execute(['hospital_id' => $hospitalId]);
    }

    public static function updateBed(int $nurseId, int $bedId, string $action, ?int $incidentId): array
    {
        if (!in_array($action, ['release', 'discharge', 'cleaning', 'ready', 'reserve'], true)) {
            throw new InvalidArgumentException('Choose a valid bed action.');
        }
        $db = self::db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "SELECT b.status, b.hospital_id,
                    (SELECT assigned.incident_id FROM incidents assigned
                     WHERE assigned.nurse_bed_id = b.id
                       AND assigned.status IN ('critical','urgent','dispatched','stable')
                     ORDER BY assigned.updated_at DESC LIMIT 1) AS incident_id
                 FROM hospital_beds b
                 INNER JOIN user_type u ON u.id = :nurse_id AND u.role = \'nurse\'
                 WHERE b.id = :bed_id AND b.hospital_id = u.hospital_id FOR UPDATE"
            );
            $stmt->execute(['nurse_id' => $nurseId, 'bed_id' => $bedId]);
            $bed = $stmt->fetch();
            if (!$bed) {
                throw new InvalidArgumentException('That bed is not available at your hospital.');
            }
            $status = (string) $bed['status'];
            $assignedIncidentId = $bed['incident_id'] === null
                ? null
                : (int) $bed['incident_id'];
            if ($assignedIncidentId !== null && !self::hasCaseAccess($nurseId, $assignedIncidentId, true)) {
                throw new InvalidArgumentException('This bed is assigned to a case outside your access.');
            }
            if ($action === 'reserve') {
                if ($status !== 'ready' || $assignedIncidentId !== null || $incidentId === null
                    || !self::hasCaseAccess($nurseId, $incidentId, true)) {
                    throw new InvalidArgumentException('Choose an available bed and an incoming case.');
                }
                $db->prepare('UPDATE incidents SET nurse_bed_id = ? WHERE incident_id = ? AND nurse_bed_id IS NULL')
                    ->execute([$bedId, $incidentId]);
                $verify = $db->prepare('SELECT nurse_bed_id FROM incidents WHERE incident_id = ? FOR UPDATE');
                $verify->execute([$incidentId]);
                if ((int) $verify->fetchColumn() !== $bedId) {
                    throw new InvalidArgumentException('This case already has another bed assigned.');
                }
                $status = 'preparing';
                $db->prepare("UPDATE hospital_beds SET status = 'preparing' WHERE id = ?")->execute([$bedId]);
                self::completeFirstPrepTask($db, $nurseId, $incidentId);
                self::syncPrepCompletion($db, $nurseId, $incidentId);
            } elseif ($action === 'release') {
                if ($status !== 'preparing') {
                    throw new InvalidArgumentException('Only a reserved bed can be released.');
                }
                if ($assignedIncidentId !== null) {
                    $db->prepare(
                        "UPDATE incidents SET nurse_bed_id = NULL
                         WHERE incident_id = ? AND nurse_bed_id = ?"
                    )->execute([$assignedIncidentId, $bedId]);
                    $db->prepare(
                        "UPDATE nurse_case_prep_tasks
                         SET is_done = 0, done_by = NULL, done_at = NULL
                         WHERE incident_id = ? AND task_name = 'ER bay reserved'"
                    )->execute([$assignedIncidentId]);
                    self::syncPrepCompletion($db, $nurseId, $assignedIncidentId);
                }
                $status = 'ready';
                $db->prepare("UPDATE hospital_beds SET status = 'ready' WHERE id = ?")->execute([$bedId]);
            } elseif ($action === 'discharge') {
                if ($status !== 'occupied') {
                    throw new InvalidArgumentException('Only an occupied bed can be discharged.');
                }
                if ($assignedIncidentId === null) {
                    throw new InvalidArgumentException('No active patient is assigned to this bed.');
                }
                $db->prepare(
                    "UPDATE incidents SET status = 'resolved', resolved_at = NOW(), nurse_bed_id = NULL
                     WHERE incident_id = ? AND nurse_bed_id = ?"
                )->execute([$assignedIncidentId, $bedId]);
                $status = 'cleaning';
                $db->prepare("UPDATE hospital_beds SET status = 'cleaning' WHERE id = ?")->execute([$bedId]);
            } else {
                if ($action === 'ready' && $status !== 'cleaning') {
                    throw new InvalidArgumentException('Only a bed being cleaned can be marked ready.');
                }
                if ($action === 'ready' && $assignedIncidentId !== null) {
                    throw new InvalidArgumentException('Remove the active case assignment before marking this bed ready.');
                }
                if ($action === 'cleaning' && $status === 'occupied') {
                    throw new InvalidArgumentException('Discharge the patient before cleaning the bed.');
                }
                if ($action === 'cleaning' && $status === 'preparing') {
                    throw new InvalidArgumentException('Release the reserved bed before cleaning it.');
                }
                if ($action === 'cleaning') {
                    $assigned = $db->prepare(
                        "SELECT incident_id FROM incidents
                         WHERE nurse_bed_id = ? AND status IN ('critical','urgent','dispatched','stable')
                         LIMIT 1 FOR UPDATE"
                    );
                    $assigned->execute([$bedId]);
                    if ($assigned->fetchColumn() !== false) {
                        throw new InvalidArgumentException('Release this bed assignment before cleaning it.');
                    }
                }
                $status = $action === 'cleaning' ? 'cleaning' : 'ready';
                $db->prepare('UPDATE hospital_beds SET status = ? WHERE id = ?')->execute([$status, $bedId]);
            }
            self::audit($db, $nurseId, 'bed_' . $action, 'bed', $bedId, ['status' => $status, 'incident_id' => $incidentId]);
            self::syncBedAggregates($db, (int) $bed['hospital_id']);
            $db->commit();
            return self::beds($nurseId);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function handoffs(int $nurseId): array
    {
        $stmt = self::db()->prepare(
            "SELECT h.id, h.incident_id, h.from_responder, h.pulse, h.spo2,
                h.blood_pressure, h.notes, h.created_at, i.patient_name,
                i.patient_age, i.patient_gender, i.incident_type,
                bed.bed_type, bed.bed_number
             FROM nurse_patient_handoffs h
             INNER JOIN incidents i ON i.incident_id = h.incident_id
             LEFT JOIN hospital_beds bed ON bed.id = h.bed_id
             WHERE h.nurse_id = :nurse_id
             ORDER BY h.created_at DESC, h.id DESC"
        );
        $stmt->execute(['nurse_id' => $nurseId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'incident_id' => (int) $row['incident_id'],
            'patient_name' => self::patientName($row),
            'case' => (string) $row['incident_type'],
            'bed_label' => $row['bed_number'] === null ? '' :
                (($row['bed_type'] === 'er' ? 'ER-' : 'PD-') . (int) $row['bed_number']),
            'from_responder' => (string) ($row['from_responder'] ?? 'Responder'),
            'pulse' => (string) ($row['pulse'] ?? ''),
            'spo2' => (string) ($row['spo2'] ?? ''),
            'blood_pressure' => (string) ($row['blood_pressure'] ?? ''),
            'notes' => (string) ($row['notes'] ?? ''),
            'created_at' => (string) $row['created_at'],
        ], $stmt->fetchAll());
    }

    public static function createHandoff(int $nurseId, int $incidentId, array $data): array
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            if (!self::hasCaseAccess($nurseId, $incidentId, false)) {
                throw new InvalidArgumentException('This patient was not assigned to your account.');
            }
            $bedLookup = $db->prepare(
                'SELECT i.nurse_bed_id FROM incidents i WHERE i.incident_id = ?'
            );
            $bedLookup->execute([$incidentId]);
            $bedId = $bedLookup->fetchColumn();
            if ($bedId === false || $bedId === null) {
                throw new InvalidArgumentException('Assign a bed before recording a handoff.');
            }
            $bedLock = $db->prepare(
                "SELECT b.status FROM hospital_beds b
                 INNER JOIN user_type u ON u.id = ? AND u.role = 'nurse'
                 WHERE b.id = ? AND b.hospital_id = u.hospital_id FOR UPDATE"
            );
            $bedLock->execute([$nurseId, (int) $bedId]);
            if ($bedLock->fetchColumn() !== 'occupied') {
                throw new InvalidArgumentException('The patient bed is not marked occupied.');
            }
            $lock = $db->prepare(
                'SELECT l.arrived_at, l.handed_off_at, i.responder_name, i.nurse_bed_id
                 FROM nurse_case_lifecycle l
                 INNER JOIN incidents i ON i.incident_id = l.incident_id
                 WHERE l.incident_id = ? FOR UPDATE'
            );
            $lock->execute([$incidentId]);
            $case = $lock->fetch();
            if (!$case || $case['arrived_at'] === null
                || $case['handed_off_at'] !== null || $case['nurse_bed_id'] === null
                || (int) $case['nurse_bed_id'] !== (int) $bedId) {
                throw new InvalidArgumentException('Confirm the patient arrival before recording a handoff.');
            }
            $db->prepare(
                'INSERT INTO nurse_patient_handoffs
                 (incident_id, nurse_id, bed_id, from_responder, pulse, spo2, blood_pressure, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $incidentId,
                $nurseId,
                (int) $case['nurse_bed_id'],
                $case['responder_name'],
                $data['pulse'],
                $data['spo2'],
                $data['blood_pressure'],
                $data['notes'],
            ]);
            $db->prepare(
                'UPDATE incidents SET
                    nurse_heart_rate = COALESCE(:pulse, nurse_heart_rate),
                    nurse_spo2 = COALESCE(:spo2, nurse_spo2),
                    nurse_blood_pressure = COALESCE(:blood_pressure, nurse_blood_pressure),
                    nurse_notes = COALESCE(NULLIF(:notes, \'\'), nurse_notes)
                 WHERE incident_id = :incident_id'
            )->execute([
                'pulse' => $data['pulse'] === null ? null : (int) $data['pulse'],
                'spo2' => $data['spo2'] === null ? null : (int) $data['spo2'],
                'blood_pressure' => $data['blood_pressure'],
                'notes' => $data['notes'],
                'incident_id' => $incidentId,
            ]);
            $db->prepare(
                'UPDATE nurse_case_lifecycle SET handed_off_at = NOW(), handed_off_by = ?
                 WHERE incident_id = ?'
            )->execute([$nurseId, $incidentId]);
            self::audit($db, $nurseId, 'handoff_recorded', 'incident', $incidentId);
            $db->commit();
            return self::handoffs($nurseId);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function schedule(int $nurseId, string $month): array
    {
        $stmt = self::db()->prepare(
            "SELECT s.id, s.shift_date, s.shift_type, s.shift_start, s.shift_end,
                s.shift_status, staff.department
             FROM staff_shifts s
             INNER JOIN staff ON staff.id = s.staff_id
            INNER JOIN user_type nurse ON nurse.id = staff.user_id AND nurse.role = 'nurse'
            WHERE staff.user_id = :nurse_id
              AND staff.id = (
                   SELECT matching_staff.id
                   FROM staff matching_staff
                    WHERE matching_staff.user_id = nurse.id
                      AND (matching_staff.hospital_id = nurse.hospital_id
                           OR matching_staff.hospital_id IS NULL)
                    ORDER BY (matching_staff.hospital_id = nurse.hospital_id) DESC,
                             matching_staff.id DESC
                    LIMIT 1
               )
               AND s.shift_date >= :start_date AND s.shift_date < :end_date
             ORDER BY s.shift_date, s.shift_start"
        );
        $start = new DateTimeImmutable($month . '-01', new DateTimeZone('Asia/Manila'));
        $stmt->execute([
            'nurse_id' => $nurseId,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->modify('+1 month')->format('Y-m-d'),
        ]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'date' => (string) $row['shift_date'],
            'type' => self::shiftType($row),
            'start' => $row['shift_start'] === null ? '' : substr((string) $row['shift_start'], 0, 5),
            'end' => $row['shift_end'] === null ? '' : substr((string) $row['shift_end'], 0, 5),
            'status' => (string) $row['shift_status'],
            'unit' => (string) ($row['department'] ?? 'Nurse'),
        ], $stmt->fetchAll());
    }

    public static function requestShiftSwap(int $nurseId, string $date, ?int $shiftId, string $message): void
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            if ($shiftId !== null) {
                $owner = $db->prepare(
                    'SELECT s.id FROM staff_shifts s
                     INNER JOIN staff st ON st.id = s.staff_id
                     WHERE s.id = :shift_id AND st.user_id = :nurse_id
                     LIMIT 1 FOR UPDATE'
                );
                $owner->execute(['shift_id' => $shiftId, 'nurse_id' => $nurseId]);
                if ($owner->fetchColumn() === false) {
                    throw new InvalidArgumentException('That shift does not belong to your account.');
                }
            }
            $db->prepare(
                'INSERT INTO nurse_shift_swap_requests (nurse_id, shift_id, requested_date, message)
                 VALUES (:nurse_id, :shift_id, :requested_date, :message)'
            )->execute([
                'nurse_id' => $nurseId,
                'shift_id' => $shiftId,
                'requested_date' => $date,
                'message' => $message,
            ]);
            self::audit($db, $nurseId, 'shift_swap_requested', 'shift', $shiftId, ['date' => $date]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function updateSettings(int $nurseId, bool $available, bool $notifications, string $language): array
    {
        if (!in_array($language, ['English', 'Filipino', 'Hiligaynon'], true)) {
            throw new InvalidArgumentException('Choose a supported language.');
        }
        $db = self::db();
        $db->beginTransaction();
        try {
            $nurse = $db->prepare(
                "SELECT u.id, u.hospital_id, u.profile_image, u.full_name
                 FROM user_type u WHERE u.id = ? AND u.role = 'nurse' FOR UPDATE"
            );
            $nurse->execute([$nurseId]);
            $profile = $nurse->fetch();
            if (!$profile) {
                throw new RuntimeException('Nurse account not found.');
            }
            if ($profile['hospital_id'] === null) {
                throw new RuntimeException('No hospital is assigned to this nurse account.');
            }
            $staff = $db->prepare(
                'SELECT id FROM staff WHERE user_id = ?
                 AND (hospital_id = ? OR hospital_id IS NULL)
                 ORDER BY (hospital_id = ?) DESC, id DESC LIMIT 1 FOR UPDATE'
            );
            $staff->execute([$nurseId, $profile['hospital_id'], $profile['hospital_id']]);
            $staffId = $staff->fetchColumn();
            if ($staffId === false) {
                $db->prepare(
                    'INSERT INTO staff (hospital_id,user_id,profile_image,full_name,department,is_available)
                     VALUES (?,?,?, ?, \'Nurse\', ?)'
                )->execute([
                    $profile['hospital_id'], $nurseId, $profile['profile_image'],
                    $profile['full_name'], $available ? 1 : 0,
                ]);
                $staffId = (int) $db->lastInsertId();
            } else {
                $db->prepare('UPDATE staff SET is_available = ? WHERE id = ?')
                    ->execute([$available ? 1 : 0, (int) $staffId]);
            }
            $db->prepare(
                'INSERT INTO nurse_mobile_preferences (nurse_id,notifications_enabled,language)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE notifications_enabled = VALUES(notifications_enabled),
                                         language = VALUES(language)'
            )->execute([$nurseId, $notifications ? 1 : 0, $language]);
            self::audit($db, $nurseId, 'settings_updated', 'nurse', $nurseId, [
                'is_available' => $available,
                'notifications_enabled' => $notifications,
                'language' => $language,
            ]);
            $db->commit();
            return self::profile($nurseId);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function caseDetails(int $nurseId, int $incidentId): ?array
    {
        if (!self::hasCaseAccess($nurseId, $incidentId, true)) {
            return null;
        }
        self::markSeen($nurseId, $incidentId);
        foreach (self::cases($nurseId) as $case) {
            if ($case['id'] === $incidentId) {
                return $case;
            }
        }

        return null;
    }

    private static function hasCaseAccess(int $nurseId, int $incidentId, bool $activeOnly = false): bool
    {
        $active = $activeOnly ? "AND i.status IN ('critical','urgent','dispatched','stable')" : '';
        $stmt = self::db()->prepare(
            "SELECT 1 FROM incidents i
             INNER JOIN user_type u ON u.id = :nurse_id AND u.role = 'nurse'
             WHERE i.incident_id = :incident_id
               AND (i.hospital_id = u.hospital_id OR (i.hospital_id IS NULL AND i.hospital_name = u.hospital_name))
               {$active}
               AND EXISTS (SELECT 1 FROM staff_alerts a WHERE a.incident_id = i.incident_id
                 AND a.recipient_user_id = u.id AND a.recipient_role = 'nurse' AND a.channel = 'admin')
               AND COALESCE((
                    SELECT current_staff.is_available FROM staff current_staff
                    WHERE current_staff.user_id = u.id
                      AND (current_staff.hospital_id = u.hospital_id
                           OR current_staff.hospital_id IS NULL)
                    ORDER BY (current_staff.hospital_id = u.hospital_id) DESC,
                             current_staff.id DESC
                    LIMIT 1
               ), 1) = 1
               AND EXISTS (
                    SELECT 1 FROM staff_alerts scoped_alert
                    INNER JOIN staff duty_staff ON duty_staff.user_id = u.id
                      AND (duty_staff.hospital_id = u.hospital_id
                           OR duty_staff.hospital_id IS NULL)
                    INNER JOIN staff_shifts duty_shift ON duty_shift.staff_id = duty_staff.id
                    WHERE scoped_alert.incident_id = i.incident_id
                      AND scoped_alert.recipient_user_id = u.id
                      AND scoped_alert.recipient_role = 'nurse'
                      AND scoped_alert.channel = 'admin'
                      AND scoped_alert.created_at >= TIMESTAMP(
                            duty_shift.shift_date, duty_shift.shift_start
                      )
                      AND scoped_alert.created_at < DATE_ADD(
                            TIMESTAMP(duty_shift.shift_date, duty_shift.shift_end),
                            INTERVAL IF(duty_shift.shift_end <= duty_shift.shift_start, 1, 0) DAY
                      )
               )
             LIMIT 1"
        );
        $stmt->execute(['nurse_id' => $nurseId, 'incident_id' => $incidentId]);

        return (bool) $stmt->fetchColumn();
    }

    private static function seedCaseTasks(int $nurseId, int $hospitalId): void
    {
        $db = self::db();
        foreach (self::casesForTaskSeed($nurseId) as $incidentId) {
            foreach (self::DEFAULT_TASKS as $taskName) {
                $db->prepare(
                    'INSERT IGNORE INTO nurse_case_prep_tasks (incident_id, task_name)
                     VALUES (?, ?)'
                )->execute([$incidentId, $taskName]);
            }
            $templates = $db->prepare(
                'SELECT task_name FROM nurse_prep_templates
                 WHERE hospital_id = ? AND is_active = 1 ORDER BY sort_order, id'
            );
            $templates->execute([$hospitalId]);
            foreach ($templates->fetchAll(PDO::FETCH_COLUMN) as $taskName) {
                $db->prepare(
                    'INSERT IGNORE INTO nurse_case_prep_tasks (incident_id, task_name)
                     VALUES (?, ?)'
                )->execute([$incidentId, $taskName]);
            }
        }
    }

    private static function casesForTaskSeed(int $nurseId): array
    {
        $stmt = self::db()->prepare(
            "SELECT DISTINCT i.incident_id FROM incidents i
             INNER JOIN staff_alerts a ON a.incident_id = i.incident_id
             INNER JOIN user_type u ON u.id = a.recipient_user_id AND u.role = 'nurse'
             WHERE a.recipient_user_id = ?
               AND a.channel = 'admin'
               AND (i.hospital_id = u.hospital_id
                    OR (i.hospital_id IS NULL AND i.hospital_name = u.hospital_name))
               AND i.status IN ('critical','urgent','dispatched','stable')
               AND EXISTS (SELECT 1 FROM incident_patients p WHERE p.incident_id = i.incident_id)"
        );
        $stmt->execute([$nurseId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function tasks(int $incidentId): array
    {
        $stmt = self::db()->prepare(
            'SELECT id, task_name, is_done, done_by, done_at
             FROM nurse_case_prep_tasks WHERE incident_id = ? ORDER BY id'
        );
        $stmt->execute([$incidentId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['task_name'],
            'done' => (bool) $row['is_done'],
            'done_by' => $row['done_by'] === null ? null : (int) $row['done_by'],
            'done_at' => $row['done_at'],
        ], $stmt->fetchAll());
    }

    private static function completeFirstPrepTask(PDO $db, int $nurseId, int $incidentId): void
    {
        $stmt = $db->prepare(
            "UPDATE nurse_case_prep_tasks
             SET is_done = 1, done_by = ?, done_at = NOW()
             WHERE incident_id = ? AND task_name = 'ER bay reserved'"
        );
        $stmt->execute([$nurseId, $incidentId]);
    }

    private static function syncPrepCompletion(PDO $db, int $nurseId, int $incidentId): void
    {
        $db->prepare(
            'UPDATE incidents i
             SET i.nurse_prep_completed = (
                SELECT COUNT(*) > 0 AND SUM(t.is_done) = COUNT(*)
                FROM nurse_case_prep_tasks t WHERE t.incident_id = i.incident_id
             ),
             i.nurse_prep_completed_by = IF(
                (SELECT COUNT(*) > 0 AND SUM(t.is_done) = COUNT(*)
                 FROM nurse_case_prep_tasks t WHERE t.incident_id = i.incident_id),
                :nurse_id, NULL
             ),
             i.nurse_prep_completed_at = IF(
                (SELECT COUNT(*) > 0 AND SUM(t.is_done) = COUNT(*)
                 FROM nurse_case_prep_tasks t WHERE t.incident_id = i.incident_id),
                NOW(), NULL
             )
             WHERE i.incident_id = :incident_id'
        )->execute(['nurse_id' => $nurseId, 'incident_id' => $incidentId]);
    }

    private static function patientName(array $row): string
    {
        $name = trim((string) ($row['patient_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $gender = (string) ($row['patient_gender'] ?? '');
        $age = $row['patient_age'] ?? null;
        return trim(($gender !== '' && $gender !== 'unknown' ? ucfirst($gender) : 'Patient')
            . ($age !== null ? ', ' . (int) $age : ''));
    }

    private static function shiftType(array $row): string
    {
        if ($row['shift_type'] === 'oncall') {
            return 'on_call';
        }
        if ($row['shift_type'] === 'leave') {
            return 'off';
        }
        if ($row['shift_type'] === 'evening') {
            return 'night';
        }
        return 'day';
    }

    private static function audit(
        PDO $db,
        int $nurseId,
        string $action,
        string $targetType,
        ?int $targetId,
        ?array $details = null
    ): void {
        $db->prepare(
            'INSERT INTO nurse_audit_log (nurse_id,action,target_type,target_id,details)
             VALUES (?,?,?,?,?)'
        )->execute([
            $nurseId,
            $action,
            $targetType,
            $targetId,
            $details === null ? null : json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }
}
