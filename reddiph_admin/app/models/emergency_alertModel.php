<?php

require_once __DIR__ . '/../../config/config.php';

class EmergencyAlertModel
{
    private const SUBMITTED_TO_HOSPITAL = 'EXISTS (
        SELECT 1
        FROM incident_patients submitted_patient
        WHERE submitted_patient.incident_id = incidents.incident_id
    )';

    private ?PDO $db = null;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Get emergency alert summary directly from incidents table.
     */
    public function getSummary(): array
    {
        if (!$this->db) {
            return [
                'all' => 0,
                'critical' => 0,
                'urgent' => 0,
                'stable' => 0,
            ];
        }

        try {
            $row = $this->db->query(
                "
                SELECT
                    COALESCE(SUM(i.status IN ('critical', 'urgent', 'dispatched', 'stable')), 0) AS all_count,
                    COALESCE(SUM(i.status = 'critical'), 0) AS critical,
                    COALESCE(SUM(i.status IN ('urgent', 'dispatched')), 0) AS urgent,
                    COALESCE(SUM(i.status = 'stable'), 0) AS stable
                FROM incidents i
                WHERE " . str_replace(
                    'incidents.incident_id',
                    'i.incident_id',
                    self::SUBMITTED_TO_HOSPITAL
                )
            )->fetch();

            return [
                'all' => (int) ($row['all_count'] ?? 0),
                'critical' => (int) ($row['critical'] ?? 0),
                'urgent' => (int) ($row['urgent'] ?? 0),
                'stable' => (int) ($row['stable'] ?? 0),
            ];
        } catch (Throwable $e) {
            error_log('[EmergencyAlertModel] getSummary: ' . $e->getMessage());
            return [
                'all' => 0,
                'critical' => 0,
                'urgent' => 0,
                'stable' => 0,
            ];
        }
    }

    public function getUserProfile(int $userId): ?array
    {
        if (!$this->db || $userId <= 0) {
            return null;
        }

        try {
            $statement = $this->db->prepare('SELECT full_name, role, hospital_name FROM user_type WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $userId]);
            return $statement->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('[EmergencyAlertModel] getUserProfile: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get hospital name from incidents or fallback.
     */
    public function getHospitalName(): string
    {
        if ($this->db) {
            try {
                $stmt = $this->db->query("SELECT hospital_name FROM incidents WHERE hospital_name IS NOT NULL AND hospital_name != '' LIMIT 1");
                $val = $stmt->fetchColumn();
                if ($val) {
                    return (string) $val;
                }
            } catch (Throwable $e) {
                error_log('[EmergencyAlertModel] getHospitalName: ' . $e->getMessage());
            }

            try {
                $val = $this->db->query('SELECT name FROM hospitals ORDER BY id LIMIT 1')->fetchColumn();
                if ($val) {
                    return (string) $val;
                }
            } catch (Throwable $e) {
                error_log('[EmergencyAlertModel] getHospitalName fallback: ' . $e->getMessage());
            }
        }

        return '';
    }

    /**
     * Incident columns and joins shared by the list and the details view.
     * The barangay and ETA fall back to the assigned vehicle when the incident row has none.
     */
    private const ALERT_SELECT = "
        SELECT
            i.incident_id,
            i.incident_type,
            i.reported_by,
            i.status,
            i.hospital_id,
            i.location,
            COALESCE(
                CASE WHEN i.reported_by = 'on_scene' THEN responder_barangay.name END,
                b.name,
                fallback_b.name
            ) AS barangay_name,
            i.latitude,
            i.longitude,
            i.patient_name,
            i.patient_gender,
            i.patient_age,
            COALESCE(i.eta_minutes, assigned_vehicle.eta_minutes) AS eta_minutes,
            i.responder_name,
            i.hospital_name,
            i.reported_at,
            i.resolved_at,
            i.notes,
            i.created_at,
            i.updated_at,
            i.transport_name,
            COALESCE(i.transport_type, assigned_vehicle.vehicle_type) AS transport_type,
            i.nurse_heart_rate,
            i.nurse_blood_pressure,
            i.nurse_spo2,
            i.nurse_gcs,
            i.nurse_notes,
            i.nurse_prep_completed,
            i.nurse_prep_completed_at,
            COALESCE(
                (
                    SELECT responder_user.full_name
                    FROM user_type responder_user
                    WHERE responder_user.hospital_id = i.hospital_id
                      AND responder_user.full_name = i.responder_name
                      AND responder_user.role IN ('responder', 'volunteer')
                    ORDER BY responder_user.id
                    LIMIT 1
                ),
                NULLIF(TRIM(assigned_vehicle.driver_name), ''),
                NULLIF(TRIM(i.driver_name), ''),
                NULLIF(TRIM(i.responder_name), '')
            ) AS assigned_responder_name,
            COALESCE(
                NULLIF(TRIM(assigned_vehicle.driver_name), ''),
                NULLIF(TRIM(i.driver_name), '')
            ) AS driver_display_name
        FROM incidents i
        LEFT JOIN barangays b ON b.id = i.barangay_id
        LEFT JOIN barangays responder_barangay ON responder_barangay.id = (
            SELECT responder_user.barangay_id
            FROM user_type responder_user
            WHERE responder_user.hospital_id = i.hospital_id
              AND responder_user.full_name = i.responder_name
              AND responder_user.role = 'responder'
            ORDER BY responder_user.id
            LIMIT 1
        )
        LEFT JOIN barangays fallback_b
            ON fallback_b.hospital_id = i.hospital_id
            AND i.location LIKE CONCAT('%', fallback_b.name, '%')
        LEFT JOIN ambulances assigned_vehicle
            ON assigned_vehicle.call_sign = i.transport_name
            AND assigned_vehicle.hospital_id = i.hospital_id
    ";
    /**
     * Get active incidents.
     */
    public function getAlerts(
        string $status = 'all',
        string $search = ''
    ): array {
        if (!$this->db) {
            return [];
        }

        try {
            $where = [
                "i.status IN ('critical', 'urgent', 'dispatched', 'stable')",
                str_replace('incidents.incident_id', 'i.incident_id', self::SUBMITTED_TO_HOSPITAL),
            ];

            $params = [];

            if ($status !== 'all') {
                $where[] = $status === 'urgent'
                    ? "i.status IN ('urgent', 'dispatched')"
                    : 'i.status = :status';
                if ($status !== 'urgent') {
                    $params['status'] = $status;
                }
            }

            if ($search !== '') {
                $where[] = "
                    (
                        i.incident_type LIKE :search_type
                        OR i.location LIKE :search_location
                        OR i.responder_name LIKE :search_responder
                        OR i.hospital_name LIKE :search_hospital
                        OR i.notes LIKE :search_notes
                        OR i.patient_name LIKE :search_patient
                        OR i.transport_name LIKE :search_transport
                        OR i.transport_type LIKE :search_transport_type
                        OR i.driver_name LIKE :search_driver
                        OR COALESCE(
                            CASE WHEN i.reported_by = 'on_scene' THEN responder_barangay.name END,
                            b.name,
                            fallback_b.name
                        ) LIKE :search_barangay
                    )
                ";

                $searchTerm = '%' . $search . '%';
                foreach (['type', 'location', 'responder', 'hospital', 'notes', 'patient', 'transport', 'transport_type', 'driver', 'barangay'] as $field) {
                    $params['search_' . $field] = $searchTerm;
                }
            }

            $sql = "
                " . self::ALERT_SELECT . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(i.reported_at, i.created_at) DESC, i.incident_id DESC
            ";

            $statement = $this->db->prepare($sql);
            $statement->execute($params);

            return $statement->fetchAll();

        } catch (Throwable $e) {
            error_log('[EmergencyAlertModel] getAlerts: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get one incident.
     */
    public function getAlertById(int $id): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $statement = $this->db->prepare("
                " . self::ALERT_SELECT . "
                WHERE i.incident_id = :id
                  AND " . str_replace(
                    'incidents.incident_id',
                    'i.incident_id',
                    self::SUBMITTED_TO_HOSPITAL
                ) . "
                LIMIT 1
            ");

            $statement->execute([
                'id' => $id
            ]);

            $alert = $statement->fetch();

            return $alert ?: null;

        } catch (Throwable $e) {
            error_log('[EmergencyAlertModel] getAlertById: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The supplied incidents schema does not contain
     * staff_notified/notified_at columns.
     *
     * Keep this safe until those fields are added.
     */
    public function notifyStaff(int $id): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $statement = $this->db->prepare("
                UPDATE incidents
                SET notes = CASE
                    WHEN notes IS NULL OR notes = ''
                        THEN 'Staff notification requested.'
                    ELSE CONCAT(notes, ' Staff notification requested.')
                END
                WHERE incident_id = :id
                  AND " . self::SUBMITTED_TO_HOSPITAL . "
                LIMIT 1
            ");

            $statement->execute([
                'id' => $id
            ]);

            return $statement->rowCount() > 0;

        } catch (Throwable $e) {
            error_log('[EmergencyAlertModel] notifyStaff: ' . $e->getMessage());
            return false;
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!$this->db || !in_array($status, ['critical', 'urgent', 'stable'], true)) {
            return false;
        }

        try {
            $statement = $this->db->prepare(
                "UPDATE incidents
                 SET status = :status
                 WHERE incident_id = :id
                   AND status IN ('critical', 'urgent', 'dispatched', 'stable')
                   AND " . self::SUBMITTED_TO_HOSPITAL . "
                 LIMIT 1"
            );
            $statement->execute(['status' => $status, 'id' => $id]);
            return $statement->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[EmergencyAlertModel] updateStatus: ' . $e->getMessage());
            return false;
        }
    }
}
