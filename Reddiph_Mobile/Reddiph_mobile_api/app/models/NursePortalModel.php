<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/IncidentTriageCategory.php';
require_once __DIR__ . '/../services/AuditLogService.php';

final class NursePortalModel
{
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
        $incoming = count(array_filter(
            $cases,
            static fn (array $case): bool => $case['status'] === 'en_route'
        ));
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
                'active_cases' => count($cases),
                'next_arrival' => $arrivals[0] ?? null,
            ],
        ];
    }

    public static function cases(int $nurseId): array
    {
        $statement = self::db()->prepare(
            "SELECT i.incident_id, i.incident_type, i.status AS incident_status,
                i.triage_category,
                i.location,
                COALESCE(NULLIF((
                    SELECT GROUP_CONCAT(
                        NULLIF(TRIM(patient.patient_name), '')
                        ORDER BY patient.id SEPARATOR ', '
                    )
                    FROM incident_patients patient
                    WHERE patient.incident_id = i.incident_id
                ), ''), NULLIF(TRIM(i.patient_name), ''), 'N/A') AS patient_name,
                i.patient_gender, i.patient_age,
                i.eta_minutes, i.responder_name, i.transport_name, i.transport_type,
                i.reported_at, i.notes, i.nurse_notes, i.nurse_heart_rate,
                i.nurse_spo2, i.nurse_blood_pressure, i.nurse_gcs,
                COALESCE(h.name, i.hospital_name) AS hospital_name,
                COALESCE((
                    SELECT assigned_ambulance.status
                    FROM ambulances assigned_ambulance
                    WHERE assigned_ambulance.hospital_id = nurse.hospital_id
                      AND (
                        assigned_ambulance.id = i.assigned_ambulance_id
                        OR assigned_ambulance.call_sign = i.transport_name
                      )
                    ORDER BY
                        (assigned_ambulance.id = i.assigned_ambulance_id) DESC,
                        assigned_ambulance.id DESC
                    LIMIT 1
                ), 'awaiting_transport') AS transport_status,
                COALESCE((
                    SELECT assigned_ambulance.eta_minutes
                    FROM ambulances assigned_ambulance
                    WHERE assigned_ambulance.hospital_id = nurse.hospital_id
                      AND (
                        assigned_ambulance.id = i.assigned_ambulance_id
                        OR assigned_ambulance.call_sign = i.transport_name
                      )
                    ORDER BY
                        (assigned_ambulance.id = i.assigned_ambulance_id) DESC,
                        assigned_ambulance.id DESC
                    LIMIT 1
                ), i.eta_minutes) AS live_eta,
                (SELECT MIN(read_alert.status = 'read')
                 FROM staff_alerts read_alert
                 WHERE read_alert.incident_id = i.incident_id
                   AND read_alert.recipient_user_id = nurse.id
                   AND read_alert.recipient_role = 'nurse'
                   AND read_alert.channel = 'admin') AS is_seen
             FROM incidents i
             INNER JOIN user_type nurse ON nurse.id = :nurse_id AND nurse.role = 'nurse'
             LEFT JOIN hospitals h ON h.id = nurse.hospital_id
             WHERE (nurse.hospital_id = i.hospital_id
                    OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name))
               AND i.status IN ('open','dispatched','referred')
               AND EXISTS (
                    SELECT 1
                    FROM staff_alerts alert
                    WHERE alert.incident_id = i.incident_id
                      AND alert.recipient_user_id = nurse.id
                      AND alert.recipient_role = 'nurse'
                      AND alert.channel = 'admin'
               )
             ORDER BY CASE triage_category
                        WHEN 'emergent' THEN 1
                        WHEN 'urgent' THEN 2
                        WHEN 'non_urgent' THEN 3
                        WHEN 'expectant' THEN 4
                        ELSE 5
                      END,
                      i.reported_at DESC, i.incident_id DESC"
        );
        $statement->execute(['nurse_id' => $nurseId]);
        $cases = [];
        foreach ($statement->fetchAll() as $row) {
            $id = (int) $row['incident_id'];
            $transportStatus = (string) $row['transport_status'];
            $status = $transportStatus === 'arrived'
                ? 'arrived'
                : ($transportStatus === 'en_route' ? 'en_route' : 'notified');
            $cases[] = [
                'id' => $id,
                'title' => (string) $row['incident_type'],
                'incident_status' => (string) $row['incident_status'],
                'triage_category' => $row['triage_category'],
                'triage_category_label' => IncidentTriageCategory::label(
                    $row['triage_category'] !== null ? (string) $row['triage_category'] : null
                ),
                'status' => $status,
                'patient_name' => self::patientName($row),
                'patient_age' => $row['patient_age'] === null ? null : (int) $row['patient_age'],
                'patient_gender' => (string) ($row['patient_gender'] ?? 'unknown'),
                'location' => (string) $row['location'],
                'hospital' => (string) $row['hospital_name'],
                'eta_minutes' => $row['live_eta'] === null ? null : (int) $row['live_eta'],
                'responder' => trim((string) ($row['responder_name'] ?? '')) ?: 'Responder not assigned',
                'unit' => trim((string) ($row['transport_name'] ?? '')) ?: 'Unit not assigned',
                'transport_type' => (string) ($row['transport_type'] ?? ''),
                'note' => (string) ($row['nurse_notes'] ?? ''),
                'responder_notes' => (string) ($row['notes'] ?? ''),
                'heart_rate' => $row['nurse_heart_rate'] === null ? null : (int) $row['nurse_heart_rate'],
                'spo2' => $row['nurse_spo2'] === null ? null : (int) $row['nurse_spo2'],
                'blood_pressure' => $row['nurse_blood_pressure'],
                'gcs' => $row['nurse_gcs'] === null ? null : (int) $row['nurse_gcs'],
                'unseen' => !(bool) $row['is_seen'],
                'reported_at' => (string) $row['reported_at'],
            ];
        }

        return $cases;
    }

    public static function caseHistory(int $nurseId): array
    {
        $statement = self::db()->prepare(
            "SELECT i.incident_id, i.incident_type, i.status, i.triage_category,
                i.location,
                COALESCE(NULLIF((
                    SELECT GROUP_CONCAT(
                        NULLIF(TRIM(patient.patient_name), '')
                        ORDER BY patient.id SEPARATOR ', '
                    )
                    FROM incident_patients patient
                    WHERE patient.incident_id = i.incident_id
                ), ''), NULLIF(TRIM(i.patient_name), ''), 'N/A') AS patient_name,
                i.patient_gender, i.patient_age, i.responder_name,
                i.transport_name, i.reported_at, i.updated_at, i.notes,
                i.nurse_notes, i.nurse_heart_rate, i.nurse_spo2,
                i.nurse_blood_pressure,
                (
                    SELECT notification.created_at
                    FROM staff_alerts notification
                    WHERE notification.incident_id = i.incident_id
                      AND notification.recipient_user_id = nurse.id
                      AND notification.recipient_role = 'nurse'
                      AND notification.channel = 'admin'
                    ORDER BY notification.created_at DESC, notification.id DESC
                    LIMIT 1
                ) AS notified_at
             FROM incidents i
             INNER JOIN user_type nurse
                ON nurse.id = :nurse_id AND nurse.role = 'nurse'
             WHERE (nurse.hospital_id = i.hospital_id
                    OR (i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name))
               AND EXISTS (
                    SELECT 1
                    FROM staff_alerts alert
                    WHERE alert.incident_id = i.incident_id
                      AND alert.recipient_user_id = nurse.id
                      AND alert.recipient_role = 'nurse'
                      AND alert.channel = 'admin'
               )
             ORDER BY notified_at DESC, i.reported_at DESC, i.incident_id DESC"
        );
        $statement->execute(['nurse_id' => $nurseId]);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['incident_id'],
                'title' => (string) $row['incident_type'],
                'status' => (string) $row['status'],
                'triage_category_label' => IncidentTriageCategory::label(
                    $row['triage_category'] !== null
                        ? (string) $row['triage_category']
                        : null
                ),
                'patient_name' => self::patientName($row),
                'patient_gender' => (string) ($row['patient_gender'] ?? 'unknown'),
                'patient_age' => $row['patient_age'] === null
                    ? null
                    : (int) $row['patient_age'],
                'location' => (string) $row['location'],
                'reported_at' => (string) $row['reported_at'],
                'updated_at' => (string) $row['updated_at'],
                'notified_at' => (string) ($row['notified_at'] ?? ''),
                'responder' => trim((string) ($row['responder_name'] ?? ''))
                    ?: 'Responder not assigned',
                'unit' => trim((string) ($row['transport_name'] ?? ''))
                    ?: 'Unit not assigned',
                'note' => (string) ($row['nurse_notes'] ?? ''),
                'responder_notes' => (string) ($row['notes'] ?? ''),
                'heart_rate' => $row['nurse_heart_rate'] === null
                    ? null
                    : (int) $row['nurse_heart_rate'],
                'spo2' => $row['nurse_spo2'] === null
                    ? null
                    : (int) $row['nurse_spo2'],
                'blood_pressure' => $row['nurse_blood_pressure'],
            ];
        }, $statement->fetchAll());
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

    public static function saveSchedules(int $nurseId, array $dates, array $data): int
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $staffId = self::ensureScheduleStaffId($db, $nurseId);
            $statement = $db->prepare(
                "INSERT IGNORE INTO staff_shifts
                    (staff_id, shift_date, shift_type, shift_start, shift_end)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $created = 0;
            foreach ($dates as $date) {
                $statement->execute([
                    $staffId,
                    $date,
                    $data['type'],
                    $data['start'] !== '' ? $data['start'] : null,
                    $data['end'] !== '' ? $data['end'] : null,
                ]);
                if ($statement->rowCount() === 1) {
                    $scheduleId = (string) $db->lastInsertId();
                    self::audit($db, $nurseId, 'schedule_created', 'staff_shift', (int) $scheduleId);
                    $created++;
                }
            }
            $db->commit();

            return $created;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function saveSchedule(int $nurseId, int $scheduleId, array $data): bool
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $staffId = self::findScheduleStaffId($db, $nurseId);
            if ($staffId === null) {
                $db->commit();
                return false;
            }
            $owner = $db->prepare(
                'SELECT id FROM staff_shifts WHERE id = ? AND staff_id = ? FOR UPDATE'
            );
            $owner->execute([$scheduleId, $staffId]);
            if ($owner->fetchColumn() === false) {
                $db->commit();
                return false;
            }
            $statement = $db->prepare(
                'UPDATE staff_shifts
                 SET shift_date = ?, shift_type = ?, shift_start = ?, shift_end = ?
                 WHERE id = ? AND staff_id = ?'
            );
            $statement->execute([
                $data['date'],
                $data['type'],
                $data['start'] !== '' ? $data['start'] : null,
                $data['end'] !== '' ? $data['end'] : null,
                $scheduleId,
                $staffId,
            ]);
            if ($statement->rowCount() > 0) {
                self::audit($db, $nurseId, 'schedule_updated', 'staff_shift', $scheduleId);
            }
            $db->commit();

            return true;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function deleteSchedule(int $nurseId, int $scheduleId): bool
    {
        $db = self::db();
        $staffId = self::findScheduleStaffId($db, $nurseId);
        if ($staffId === null) {
            return false;
        }
        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                'DELETE FROM staff_shifts WHERE id = ? AND staff_id = ?'
            );
            $statement->execute([$scheduleId, $staffId]);
            $deleted = $statement->rowCount() === 1;
            if ($deleted) {
                self::audit($db, $nurseId, 'schedule_deleted', 'staff_shift', $scheduleId);
            }
            $db->commit();

            return $deleted;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
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
        $active = $activeOnly ? "AND i.status IN ('open','dispatched','referred')" : '';
        $stmt = self::db()->prepare(
            "SELECT 1 FROM incidents i
             INNER JOIN user_type u ON u.id = :nurse_id AND u.role = 'nurse'
             WHERE i.incident_id = :incident_id
               AND (i.hospital_id = u.hospital_id OR (i.hospital_id IS NULL AND i.hospital_name = u.hospital_name))
               {$active}
               AND EXISTS (SELECT 1 FROM staff_alerts a WHERE a.incident_id = i.incident_id
                 AND a.recipient_user_id = u.id AND a.recipient_role = 'nurse' AND a.channel = 'admin')
             LIMIT 1"
        );
        $stmt->execute(['nurse_id' => $nurseId, 'incident_id' => $incidentId]);

        return (bool) $stmt->fetchColumn();
    }

    private static function patientName(array $row): string
    {
        $name = trim((string) ($row['patient_name'] ?? ''));
        return $name !== '' ? $name : 'N/A';
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

    private static function findScheduleStaffId(PDO $db, int $nurseId): ?int
    {
        $statement = $db->prepare(
            "SELECT staff.id
             FROM staff
             INNER JOIN user_type nurse
                ON nurse.id = staff.user_id AND nurse.role = 'nurse'
             WHERE nurse.id = ?
               AND (staff.hospital_id = nurse.hospital_id OR staff.hospital_id IS NULL)
             ORDER BY (staff.hospital_id = nurse.hospital_id) DESC, staff.id DESC
             LIMIT 1"
        );
        $statement->execute([$nurseId]);
        $staffId = $statement->fetchColumn();

        return $staffId === false ? null : (int) $staffId;
    }

    private static function ensureScheduleStaffId(PDO $db, int $nurseId): int
    {
        $nurseStatement = $db->prepare(
            "SELECT id, hospital_id, full_name, profile_image
             FROM user_type WHERE id = ? AND role = 'nurse' LIMIT 1 FOR UPDATE"
        );
        $nurseStatement->execute([$nurseId]);
        $nurse = $nurseStatement->fetch();
        if (!$nurse) {
            throw new RuntimeException('Nurse account not found.');
        }

        $staffStatement = $db->prepare(
            'SELECT id FROM staff
             WHERE user_id = ? AND (hospital_id = ? OR hospital_id IS NULL)
             ORDER BY (hospital_id = ?) DESC, id DESC
             LIMIT 1 FOR UPDATE'
        );
        $staffStatement->execute([
            $nurseId,
            $nurse['hospital_id'],
            $nurse['hospital_id'],
        ]);
        $staffId = $staffStatement->fetchColumn();
        if ($staffId !== false) {
            return (int) $staffId;
        }

        $insert = $db->prepare(
            "INSERT INTO staff
                (hospital_id, user_id, profile_image, full_name, department)
             VALUES (?, ?, ?, ?, 'Nurse')"
        );
        $insert->execute([
            $nurse['hospital_id'],
            $nurseId,
            $nurse['profile_image'],
            $nurse['full_name'],
        ]);

        return (int) $db->lastInsertId();
    }

    private static function audit(
        PDO $db,
        int $nurseId,
        string $action,
        string $targetType,
        ?int $targetId,
        ?array $details = null
    ): void {
        $actionCode = match ($action) {
            'prep_task_updated' => 'nurse.task_updated',
            'clinical_note_saved' => 'nurse.note_saved',
            'bed_assigned' => 'nurse.bed_assigned',
            'arrival_confirmed' => 'nurse.arrival_confirmed',
            'handoff_recorded' => 'nurse.handoff_recorded',
            'shift_swap_requested' => 'nurse.shift_swap_requested',
            'schedule_created' => 'nurse.schedule_created',
            'schedule_updated' => 'nurse.schedule_updated',
            'schedule_deleted' => 'nurse.schedule_deleted',
            'settings_updated' => 'nurse.settings_updated',
            default => 'nurse.bed_updated',
        };
        AuditLogService::record(
            $db,
            $nurseId,
            $actionCode,
            $targetType,
            $targetId === null ? null : (string) $targetId,
            $details ?? []
        );
    }
}
