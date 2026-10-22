<?php
/**
 * dispatchModel.php
 *
 * Database access for the Incoming Dispatches page.
 */

class dispatchModel
{
    private ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::connection();
            try {
                $this->ensureDispatchColumns();
            } catch (Throwable $e) {
                error_log('[dispatchModel] Legacy schema migration failed: ' . $e->getMessage());
            }
            try {
                $this->ensureHospitalBarangayIndexes();
            } catch (Throwable $e) {
                error_log('[dispatchModel] Hospital barangay repair failed: ' . $e->getMessage());
            }
            try {
                $this->ensureUserDutyColumns();
            } catch (Throwable $e) {
                error_log('[dispatchModel] User duty migration failed: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            error_log('[dispatchModel] Database connection failed: ' . $e->getMessage());
        }
    }

    private function ensureDispatchColumns(): void
    {
        if (!$this->db) {
            return;
        }

        $this->db->exec("ALTER TABLE incidents
            ADD COLUMN IF NOT EXISTS barangay_id INT UNSIGNED NULL,
            ADD COLUMN IF NOT EXISTS patient_name VARCHAR(160) NULL,
            ADD COLUMN IF NOT EXISTS hospital_id INT UNSIGNED NULL,
            ADD COLUMN IF NOT EXISTS transport_name VARCHAR(80) NULL,
            ADD COLUMN IF NOT EXISTS transport_type VARCHAR(40) NULL,
            ADD COLUMN IF NOT EXISTS driver_name VARCHAR(160) NULL");
        $this->db->exec("ALTER TABLE ambulances
            ADD COLUMN IF NOT EXISTS driver_name VARCHAR(160) NULL");
        $this->db->exec("ALTER TABLE barangays
            ADD COLUMN IF NOT EXISTS hospital_id INT UNSIGNED NULL AFTER id");
        $this->db->exec("ALTER TABLE user_type
            ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL,
            ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL");
        $this->db->exec("ALTER TABLE staff
            ADD COLUMN IF NOT EXISTS hospital_id INT UNSIGNED NULL,
            ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL,
            ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL");
        $this->db->exec("ALTER TABLE facility_status
            ADD COLUMN IF NOT EXISTS hospital_id INT UNSIGNED NULL");
        $this->db->exec("ALTER TABLE facility_status
            DROP INDEX IF EXISTS metric_key,
            ADD UNIQUE INDEX IF NOT EXISTS uq_facility_status_hospital_metric (hospital_id, metric_key)");
        $this->db->exec("UPDATE incidents i
                JOIN hospitals h ON h.name = i.hospital_name
                SET i.hospital_id = h.id WHERE i.hospital_id IS NULL");
    }

    private function ensureHospitalBarangayIndexes(): void
    {
        try {
            $this->db->exec("ALTER TABLE barangays
                DROP INDEX IF EXISTS slug,
                DROP INDEX IF EXISTS uq_barangays_name,
                DROP INDEX IF EXISTS idx_barangays_sort_order,
                ADD INDEX IF NOT EXISTS idx_barangays_hospital_sort (hospital_id, sort_order),
                ADD UNIQUE INDEX IF NOT EXISTS uq_barangays_hospital_slug (hospital_id, slug),
                ADD UNIQUE INDEX IF NOT EXISTS uq_barangays_hospital_name (hospital_id, name)");
        } catch (Throwable $e) {
            error_log('[dispatchModel] Barangay index migration failed: ' . $e->getMessage());
        }
    }

    private function ensureUserDutyColumns(): void
    {
        if (!$this->db) {
            return;
        }

        $this->db->exec("ALTER TABLE user_type
            ADD COLUMN IF NOT EXISTS duty_start TIME NULL,
            ADD COLUMN IF NOT EXISTS duty_end TIME NULL,
            ADD COLUMN IF NOT EXISTS duty_timezone VARCHAR(64) NULL");

        $accounts = $this->db->query("SELECT id
            FROM user_type
            WHERE role IN ('doctor', 'nurse')
            AND duty_start IS NULL
            ORDER BY hospital_id, created_at, id")->fetchAll(PDO::FETCH_COLUMN);

        $statement = $this->db->prepare("UPDATE user_type
            SET duty_start = :duty_start,
                duty_end = :duty_end,
                duty_timezone = 'Asia/Manila'
            WHERE id = :id");
        foreach ($accounts as $index => $accountId) {
            $isDayShift = $index < 3;
            $statement->execute([
                ':duty_start' => $isDayShift ? '06:00:00' : '18:00:00',
                ':duty_end' => $isDayShift ? '18:00:00' : '06:00:00',
                ':id' => (int) $accountId,
            ]);
        }

        $this->db->exec("UPDATE user_type
            SET duty_start = '06:00:00', duty_end = '18:00:00', duty_timezone = 'Asia/Manila'
            WHERE work_email IN (
                'andrea.villanueva@reddiph.com',
                'bianca.reyes@reddiph.com',
                'leo.mendoza@reddiph.com'
            )
            AND role IN ('doctor', 'nurse')");
        $this->db->exec("UPDATE user_type
            SET duty_start = '18:00:00', duty_end = '06:00:00', duty_timezone = 'Asia/Manila'
            WHERE work_email IN (
                'sofia.cruz@reddiph.com',
                'miguel.santos@reddiph.com',
                'elena.ramos@reddiph.com'
            )
            AND role IN ('doctor', 'nurse')");
    }

    /**
     * All barangays with their live active-dispatch counts.
     * Matches: barangays(id, hospital_id, name, slug, active_dispatch_count, sort_order)
     */
    public function getBarangays(?int $hospitalId = null): array
    {
        return $this->fetchAll("SELECT b.id, b.name, b.slug,
            COUNT(CASE WHEN i.status IN ('critical', 'urgent', 'dispatched') THEN 1 END) AS active_dispatch_count
            FROM barangays b
            LEFT JOIN incidents i ON (i.barangay_id = b.id OR i.location LIKE CONCAT('%', b.name, '%'))
                AND i.hospital_id = b.hospital_id
            WHERE " . ($hospitalId ? 'b.hospital_id = ' . (int) $hospitalId : '1=1') . "
            GROUP BY b.id, b.name, b.slug, b.sort_order
            ORDER BY b.sort_order, b.id");
    }

    /**
     * Refresh and return the three hospital-scoped facility metrics from source tables.
     * Matches: facility_status(metric_key, current_value, max_value, status_label)
     */
    public function getFacilityStatus(?int $hospitalId = null): array
    {
        if (!$this->db || !$hospitalId) {
            return [];
        }

        $sync = $this->db->prepare("INSERT INTO facility_status
            (hospital_id, metric_key, current_value, max_value, status_label)
            SELECT :bed_hospital_id, 'bed_capacity', h.available_beds, h.total_beds, NULL
            FROM hospitals h WHERE h.id = :bed_hospital_filter
            UNION ALL
            SELECT :ambulance_hospital_id, 'ambulance_bay',
                COALESCE(SUM(a.status = 'available'), 0), COUNT(a.id),
                IF(COUNT(a.id) = 0, 'No vehicles configured', NULL)
            FROM ambulances a
            WHERE a.hospital_id = :ambulance_hospital_filter AND a.vehicle_type = 'Ambulance'
            UNION ALL
            SELECT :ptv_hospital_id, 'ptv',
                COALESCE(SUM(a.status = 'available'), 0), COUNT(a.id),
                IF(COUNT(a.id) = 0, 'No vehicles configured', NULL)
            FROM ambulances a
            WHERE a.hospital_id = :ptv_hospital_filter AND a.vehicle_type = 'PTV'
            ON DUPLICATE KEY UPDATE
                current_value = VALUES(current_value),
                max_value = VALUES(max_value),
                status_label = VALUES(status_label)");
        $sync->execute([
            ':bed_hospital_id' => $hospitalId,
            ':bed_hospital_filter' => $hospitalId,
            ':ambulance_hospital_id' => $hospitalId,
            ':ambulance_hospital_filter' => $hospitalId,
            ':ptv_hospital_id' => $hospitalId,
            ':ptv_hospital_filter' => $hospitalId,
        ]);

        $statement = $this->db->prepare("SELECT metric_key, current_value, max_value, status_label
            FROM facility_status
            WHERE hospital_id = :hospital_id
            AND metric_key IN ('bed_capacity', 'ambulance_bay', 'ptv')
            ORDER BY FIELD(metric_key, 'bed_capacity', 'ambulance_bay', 'ptv')");
        $statement->execute([':hospital_id' => $hospitalId]);

        return $statement->fetchAll();
    }

    /**
     * Staff whose scheduled shift is active now.
     * Staff and shift ownership follow staff_shifts.staff_id -> staff.id -> user_type.id.
     */
    public function getStaffOnDuty(?int $hospitalId = null): array
    {
        if (!$this->db) {
            return [];
        }

        $timezone = new DateTimeZone('Asia/Manila');
        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $yesterday = $now->modify('-1 day')->format('Y-m-d');
        $sql = "SELECT u.id, u.full_name, u.role,
            CASE u.role WHEN 'doctor' THEN 'Doctor' ELSE 'Nurse' END AS department,
            COALESCE(staff.profile_image, u.profile_image) AS profile_image,
            u.id AS user_id, u.duty_timezone,
            scheduled_shift.id AS shift_id, scheduled_shift.shift_date,
            scheduled_shift.shift_start, scheduled_shift.shift_end,
            scheduled_shift.shift_status
            FROM user_type u
            LEFT JOIN staff ON staff.id = (
                SELECT matching_staff.id
                FROM staff matching_staff
                WHERE matching_staff.user_id = u.id
                  AND (matching_staff.hospital_id = u.hospital_id OR matching_staff.hospital_id IS NULL)
                ORDER BY (matching_staff.hospital_id = u.hospital_id) DESC, matching_staff.id DESC
                LIMIT 1
            )
            LEFT JOIN staff_shifts scheduled_shift
                ON scheduled_shift.staff_id = staff.id
               AND scheduled_shift.shift_date IN (:shift_today, :shift_yesterday)
            WHERE u.role IN ('doctor', 'nurse')";

        if ($hospitalId) {
            $sql .= " AND u.hospital_id = :hospital_id";
        }

        $sql .= " ORDER BY u.full_name, scheduled_shift.shift_date DESC, scheduled_shift.shift_start";

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':shift_today', $today);
        $statement->bindValue(':shift_yesterday', $yesterday);
        if ($hospitalId) {
            $statement->bindValue(':hospital_id', (int) $hospitalId, PDO::PARAM_INT);
        }
        $statement->execute();

        $accounts = $statement->fetchAll();
        $staffByUser = [];

        foreach ($accounts as $account) {
            $userId = (int) $account['user_id'];
            $isShiftActive = $account['shift_id'] !== null
                && $this->isScheduledShiftActive(
                    (string) $account['shift_date'],
                    $account['shift_start'] === null ? null : (string) $account['shift_start'],
                    $account['shift_end'] === null ? null : (string) $account['shift_end'],
                    $now
                );
            if (!$isShiftActive) {
                continue;
            }

            $account['is_available'] = $account['shift_status'] === 'on_duty';
            $account['department'] = $account['department'] ?? ($account['role'] === 'doctor' ? 'Doctor' : 'Nurse');
            $account['user_id'] = $userId;
            $account['profile_image'] = $account['profile_image'] ?? null;
            $account['duty_start'] = $account['shift_start'];
            $account['duty_end'] = $account['shift_end'];
            $existing = $staffByUser[$userId] ?? null;
            if ($existing === null || $account['shift_date'] > $existing['shift_date']) {
                $staffByUser[$userId] = $account;
            }
        }

        return array_values($staffByUser);
    }

    private function isScheduledShiftActive(
        string $shiftDate,
        ?string $shiftStart,
        ?string $shiftEnd,
        DateTimeImmutable $now
    ): bool
    {
        if (!$shiftStart || !$shiftEnd) {
            return false;
        }

        $start = new DateTimeImmutable($shiftDate . ' ' . $shiftStart, $now->getTimezone());
        $end = new DateTimeImmutable($shiftDate . ' ' . $shiftEnd, $now->getTimezone());
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        return $now >= $start && $now < $end;
    }

    public function getActiveDispatches(?int $hospitalId = null): array
    {
        return $this->fetchAll("SELECT i.incident_id, i.incident_type AS dispatch_purpose,
            i.patient_name, i.patient_gender, i.patient_age, i.location, i.status,
            i.eta_minutes, i.reported_at,
            COALESCE(i.transport_name, a.call_sign) AS vehicle_name,
            COALESCE(i.transport_type, a.vehicle_type) AS vehicle_type,
            COALESCE(i.driver_name, a.driver_name, i.responder_name) AS driver_name,
            COALESCE(b.id, fallback_b.id) AS barangay_id,
            COALESCE(b.slug, fallback_b.slug) AS barangay_slug,
            COALESCE(b.name, fallback_b.name) AS barangay_name
            FROM incidents i
            LEFT JOIN ambulances a ON a.call_sign = i.transport_name
                AND a.hospital_id = i.hospital_id
            LEFT JOIN barangays b ON b.id = i.barangay_id
            LEFT JOIN barangays fallback_b ON fallback_b.hospital_id = i.hospital_id
                AND i.location LIKE CONCAT('%', fallback_b.name, '%')
            WHERE i.status IN ('critical', 'urgent', 'dispatched')
              AND EXISTS (
                  SELECT 1
                  FROM incident_patients submitted_patient
                  WHERE submitted_patient.incident_id = i.incident_id
              )
            " . ($hospitalId ? 'AND i.hospital_id = ' . (int) $hospitalId : '') . "
            GROUP BY i.incident_id, i.incident_type, i.patient_name, i.patient_gender, i.patient_age,
                i.location, i.status, i.eta_minutes, i.reported_at, i.transport_name, i.transport_type,
                i.driver_name, i.responder_name, a.call_sign, a.vehicle_type, a.driver_name,
                b.id, b.slug, b.name, fallback_b.id, fallback_b.slug, fallback_b.name
            ORDER BY i.reported_at DESC");
    }

    public function getIncidentNotifications(?int $hospitalId): array
    {
        if (!$hospitalId) {
            return [];
        }
        if (!$this->db) {
            throw new RuntimeException('Database connection is unavailable for incident notifications.');
        }

        $statement = $this->db->prepare(
            "SELECT i.incident_id, i.incident_type, i.status, i.location,
                i.patient_name, i.reported_by, i.reported_at,
                COALESCE(b.name, fallback_b.name, 'Barangay unavailable') AS barangay_name,
                COALESCE(b.slug, fallback_b.slug) AS barangay_slug,
                COALESCE(reporter.role, '') AS reporter_role
             FROM incidents i
             LEFT JOIN user_type reporter ON reporter.id = i.reporter_user_id
             LEFT JOIN barangays b ON b.id = i.barangay_id
             LEFT JOIN barangays fallback_b ON fallback_b.hospital_id = i.hospital_id
                AND i.location LIKE CONCAT('%', fallback_b.name, '%')
             WHERE i.hospital_id = :hospital_id
               AND i.reported_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
               AND EXISTS (
                    SELECT 1
                    FROM incident_patients submitted_patient
                    WHERE submitted_patient.incident_id = i.incident_id
               )
             ORDER BY i.reported_at DESC, i.incident_id DESC
             LIMIT 100"
        );
        $statement->execute(['hospital_id' => $hospitalId]);

        return array_map(static function (array $incident): array {
            return [
                'id' => (int) $incident['incident_id'],
                'incidentType' => (string) $incident['incident_type'],
                'status' => (string) $incident['status'],
                'location' => (string) $incident['location'],
                'patientName' => (string) ($incident['patient_name'] ?? ''),
                'reportedBy' => (string) $incident['reported_by'],
                'reporterRole' => (string) $incident['reporter_role'],
                'reportedAt' => (string) $incident['reported_at'],
                'barangay' => (string) $incident['barangay_name'],
                'barangaySlug' => (string) ($incident['barangay_slug'] ?? ''),
            ];
        }, $statement->fetchAll());
    }

    public function getUserProfile(int $userId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $statement = $this->db->prepare('SELECT id, full_name, role, profile_image FROM user_type WHERE id = :id LIMIT 1');
            $statement->execute([':id' => $userId]);
            return $statement->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('[dispatchModel] User profile query failed: ' . $e->getMessage());
            return null;
        }
    }

    private function fetchAll(string $sql): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            return $this->db->query($sql)->fetchAll();
        } catch (Throwable $e) {
            error_log('[dispatchModel] Query failed: ' . $e->getMessage());
            return [];
        }
    }
}