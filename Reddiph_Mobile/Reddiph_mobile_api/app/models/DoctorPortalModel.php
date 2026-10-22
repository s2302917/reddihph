<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

final class DoctorPortalModel
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function profile(int $doctorId): array
    {
        $stmt = self::db()->prepare(
            "SELECT u.id, u.full_name, u.hospital_id, u.profile_image,
                    h.latitude AS hospital_latitude,
                    h.longitude AS hospital_longitude,
                    COALESCE(h.name, u.hospital_name) AS hospital_name,
                    COALESCE((
                        SELECT s.is_available
                        FROM staff s
                        WHERE s.user_id = u.id
                          AND (s.hospital_id = u.hospital_id OR s.hospital_id IS NULL)
                        ORDER BY (s.hospital_id = u.hospital_id) DESC, s.id DESC
                        LIMIT 1
                    ), 1) AS is_available
             FROM user_type u
             LEFT JOIN hospitals h ON h.id = u.hospital_id
             WHERE u.id = ? AND u.role = 'doctor'
             LIMIT 1"
        );
        $stmt->execute([$doctorId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Doctor profile is not available.');
        }

        return [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'hospital_id' => $row['hospital_id'] !== null ? (int) $row['hospital_id'] : null,
            'hospital_name' => (string) ($row['hospital_name'] ?? 'Hospital'),
            'hospital_latitude' => $row['hospital_latitude'] !== null
                ? (float) $row['hospital_latitude']
                : null,
            'hospital_longitude' => $row['hospital_longitude'] !== null
                ? (float) $row['hospital_longitude']
                : null,
            'profile_image' => $row['profile_image'] !== null
                ? (string) $row['profile_image']
                : null,
            'is_available' => (bool) $row['is_available'],
        ];
    }

    public static function cases(int $doctorId, string $filter = 'all'): array
    {
        $statusSql = match ($filter) {
            'incoming' => "AND i.status IN ('critical', 'urgent', 'dispatched')",
            'in_er' => "AND i.status = 'stable'",
            'done' => "AND i.status = 'resolved'",
            default => '',
        };
        $stmt = self::db()->prepare(
            "SELECT i.incident_id, i.incident_type, i.status, i.location,
                    i.latitude, i.longitude,
                    i.patient_name, i.patient_gender, i.patient_age, i.eta_minutes,
                    i.responder_name, i.reported_at, i.resolved_at, i.notes,
                    i.responder_arrived_at, i.patient_picked_up_at,
                    i.hospital_arrived_at,
                    COALESCE(h.name, i.hospital_name) AS hospital_name,
                    (SELECT COUNT(*) FROM incident_patients ip
                     WHERE ip.incident_id = i.incident_id) AS patient_count,
                    (SELECT ip.patient_name FROM incident_patients ip
                     WHERE ip.incident_id = i.incident_id ORDER BY ip.id LIMIT 1)
                        AS reported_patient_name,
                    (SELECT ip.patient_age FROM incident_patients ip
                     WHERE ip.incident_id = i.incident_id ORDER BY ip.id LIMIT 1)
                        AS reported_patient_age,
                    (SELECT ip.heart_rate FROM incident_patients ip
                     WHERE ip.incident_id = i.incident_id ORDER BY ip.id LIMIT 1)
                        AS heart_rate,
                    (SELECT ip.spo2 FROM incident_patients ip
                     WHERE ip.incident_id = i.incident_id ORDER BY ip.id LIMIT 1)
                        AS spo2,
                    (SELECT ip.nurse_blood_pressure FROM incident_patients ip
                     WHERE ip.incident_id = i.incident_id ORDER BY ip.id LIMIT 1)
                        AS blood_pressure,
                    review.confirmed_at AS doctor_arrival_confirmed_at
             FROM incidents i
             INNER JOIN user_type doctor
                ON doctor.id = ? AND doctor.role = 'doctor'
               AND doctor.hospital_id = i.hospital_id
             LEFT JOIN hospitals h ON h.id = doctor.hospital_id
             LEFT JOIN doctor_case_reviews review
                ON review.incident_id = i.incident_id
               AND review.doctor_user_id = doctor.id
             WHERE EXISTS (
                SELECT 1 FROM staff_alerts alert
                WHERE alert.incident_id = i.incident_id
                  AND alert.recipient_user_id = doctor.id
                  AND alert.recipient_role = 'doctor'
                  AND alert.channel = 'admin'
             )
             AND EXISTS (
                SELECT 1 FROM incident_patients submitted
                WHERE submitted.incident_id = i.incident_id
             )
             {$statusSql}
             ORDER BY CASE i.status
                WHEN 'critical' THEN 1
                WHEN 'urgent' THEN 2
                WHEN 'dispatched' THEN 2
                WHEN 'stable' THEN 3
                ELSE 4
             END, i.reported_at DESC, i.incident_id DESC"
        );
        $stmt->execute([$doctorId]);

        return array_map([self::class, 'normalizeCase'], $stmt->fetchAll());
    }

    public static function caseDetails(int $doctorId, int $incidentId): ?array
    {
        $cases = self::db()->prepare(
            "SELECT i.incident_id, i.incident_type, i.status, i.location,
                    i.latitude, i.longitude,
                    i.patient_name, i.patient_gender, i.patient_age, i.eta_minutes,
                    i.responder_name, i.reported_at, i.notes, i.resolved_at,
                    i.responder_arrived_at, i.patient_picked_up_at,
                    i.hospital_arrived_at,
                    COALESCE(h.name, i.hospital_name) AS hospital_name,
                    review.clinical_note, review.confirmed_at AS doctor_arrival_confirmed_at
             FROM incidents i
             INNER JOIN user_type doctor
                ON doctor.id = ? AND doctor.role = 'doctor'
               AND doctor.hospital_id = i.hospital_id
             LEFT JOIN hospitals h ON h.id = doctor.hospital_id
             LEFT JOIN doctor_case_reviews review
                ON review.incident_id = i.incident_id
               AND review.doctor_user_id = doctor.id
             WHERE i.incident_id = ?
               AND EXISTS (
                 SELECT 1 FROM staff_alerts alert
                 WHERE alert.incident_id = i.incident_id
                   AND alert.recipient_user_id = doctor.id
                   AND alert.recipient_role = 'doctor'
                   AND alert.channel = 'admin'
               )
             LIMIT 1"
        );
        $cases->execute([$doctorId, $incidentId]);
        $case = $cases->fetch();
        if ($case === false) {
            return null;
        }

        $patients = self::db()->prepare(
            "SELECT patient_name, patient_age, patient_gender, heart_rate, spo2,
                    nurse_blood_pressure AS blood_pressure, notes
             FROM incident_patients
             WHERE incident_id = ?
             ORDER BY id"
        );
        $patients->execute([$incidentId]);
        $patientRows = $patients->fetchAll();

        $timeline = [
            self::timelineEntry(
                'Reported by responder',
                $case['responder_name'] !== null ? (string) $case['responder_name'] : '',
                (string) $case['reported_at']
            ),
        ];
        foreach ([
            'responder_arrived_at' => ['Responder arrived', (string) $case['location']],
            'patient_picked_up_at' => ['Patient picked up', 'En route to hospital'],
            'hospital_arrived_at' => ['Arrived at hospital', (string) $case['hospital_name']],
        ] as $field => [$title, $detail]) {
            if ($case[$field] !== null) {
                $timeline[] = self::timelineEntry($title, $detail, (string) $case[$field]);
            }
        }
        if ($case['doctor_arrival_confirmed_at'] !== null) {
            $timeline[] = self::timelineEntry(
                'Doctor arrival confirmed',
                (string) $case['hospital_name'],
                (string) $case['doctor_arrival_confirmed_at']
            );
        }
        usort($timeline, static fn (array $a, array $b): int =>
            strcmp($a['occurred_at'], $b['occurred_at'])
        );

        $normalized = self::normalizeCase([
            'incident_id' => $case['incident_id'],
            'incident_type' => $case['incident_type'],
            'status' => $case['status'],
            'location' => $case['location'],
            'latitude' => $case['latitude'],
            'longitude' => $case['longitude'],
            'patient_name' => $case['patient_name'],
            'patient_gender' => $case['patient_gender'],
            'patient_age' => $case['patient_age'],
            'eta_minutes' => $case['eta_minutes'],
            'responder_name' => $case['responder_name'],
            'reported_at' => $case['reported_at'],
            'resolved_at' => $case['resolved_at'],
            'notes' => $case['notes'],
            'hospital_name' => $case['hospital_name'],
            'patient_count' => count($patientRows),
            'reported_patient_name' => $patientRows[0]['patient_name'] ?? null,
            'reported_patient_age' => $patientRows[0]['patient_age'] ?? null,
            'heart_rate' => $patientRows[0]['heart_rate'] ?? null,
            'spo2' => $patientRows[0]['spo2'] ?? null,
            'blood_pressure' => $patientRows[0]['blood_pressure'] ?? null,
            'doctor_arrival_confirmed_at' => $case['doctor_arrival_confirmed_at'],
        ]);
        $normalized['clinical_note'] = $case['clinical_note'] !== null
            ? (string) $case['clinical_note']
            : '';
        $normalized['patients'] = array_map(static fn (array $patient): array => [
            'name' => (string) $patient['patient_name'],
            'age' => (int) $patient['patient_age'],
            'gender' => (string) $patient['patient_gender'],
            'heart_rate' => $patient['heart_rate'] !== null ? (int) $patient['heart_rate'] : null,
            'spo2' => $patient['spo2'] !== null ? (int) $patient['spo2'] : null,
            'blood_pressure' => $patient['blood_pressure'] !== null
                ? (string) $patient['blood_pressure']
                : null,
            'notes' => $patient['notes'] !== null ? (string) $patient['notes'] : '',
        ], $patientRows);
        $normalized['timeline'] = $timeline;

        return $normalized;
    }

    public static function saveCaseReview(
        int $doctorId,
        int $incidentId,
        string $clinicalNote,
        bool $confirmArrival
    ): ?array {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $authorized = $pdo->prepare(
                "SELECT 1
                 FROM incidents i
                 JOIN user_type doctor
                   ON doctor.id = ? AND doctor.role = 'doctor'
                  AND doctor.hospital_id = i.hospital_id
                 JOIN staff_alerts alert
                   ON alert.incident_id = i.incident_id
                  AND alert.recipient_user_id = doctor.id
                  AND alert.recipient_role = 'doctor'
                  AND alert.channel = 'admin'
                 WHERE i.incident_id = ?
                 LIMIT 1 FOR UPDATE"
            );
            $authorized->execute([$doctorId, $incidentId]);
            if ($authorized->fetchColumn() === false) {
                $pdo->rollBack();
                return null;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO doctor_case_reviews
                    (incident_id, doctor_user_id, clinical_note, confirmed_at)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    clinical_note = COALESCE(VALUES(clinical_note), clinical_note),
                    confirmed_at = COALESCE(confirmed_at, VALUES(confirmed_at)),
                    updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([
                $incidentId,
                $doctorId,
                $clinicalNote,
                $confirmArrival
                    ? (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
                        ->format('Y-m-d H:i:s')
                    : null,
            ]);
            $pdo->commit();

            return self::caseDetails($doctorId, $incidentId);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function history(int $doctorId, string $period): array
    {
        $dateCondition = match ($period) {
            'week' => "AND i.reported_at >= :start_date AND i.reported_at < :end_date",
            'month' => "AND i.reported_at >= :start_date AND i.reported_at < :end_date",
            default => '',
        };
        $params = ['doctor_id' => $doctorId];
        if ($period === 'week') {
            $start = new DateTimeImmutable('monday this week', new DateTimeZone('Asia/Manila'));
            $end = $start->modify('+1 week');
            $params += [
                'start_date' => $start->format('Y-m-d 00:00:00'),
                'end_date' => $end->format('Y-m-d 00:00:00'),
            ];
        } elseif ($period === 'month') {
            $start = new DateTimeImmutable('first day of this month', new DateTimeZone('Asia/Manila'));
            $end = $start->modify('+1 month');
            $params += [
                'start_date' => $start->format('Y-m-d 00:00:00'),
                'end_date' => $end->format('Y-m-d 00:00:00'),
            ];
        }
        $stmt = self::db()->prepare(
            "SELECT i.incident_id, i.incident_type, i.status, i.patient_name,
                    i.reported_at, i.resolved_at, review.confirmed_at
             FROM incidents i
             INNER JOIN user_type doctor
                ON doctor.id = :hospital_doctor_id
               AND doctor.role = 'doctor'
               AND doctor.hospital_id = i.hospital_id
             LEFT JOIN doctor_case_reviews review
                ON review.incident_id = i.incident_id
               AND review.doctor_user_id = :review_doctor_id
             WHERE EXISTS (
                SELECT 1 FROM staff_alerts alert
                WHERE alert.incident_id = i.incident_id
                  AND alert.recipient_user_id = :doctor_id
                  AND alert.recipient_role = 'doctor'
                  AND alert.channel = 'admin'
             ) {$dateCondition}
             ORDER BY i.reported_at DESC, i.incident_id DESC
             LIMIT 500"
        );
        $params['hospital_doctor_id'] = $doctorId;
        $params['review_doctor_id'] = $doctorId;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $cases = array_map(static function (array $row): array {
            $reported = (string) $row['reported_at'];
            $resolved = $row['resolved_at'] !== null ? (string) $row['resolved_at'] : null;
            $confirmed = $row['confirmed_at'] !== null ? (string) $row['confirmed_at'] : null;

            return [
                'id' => (int) $row['incident_id'],
                'title' => (string) $row['incident_type'],
                'status' => (string) $row['status'],
                'patient' => $row['patient_name'] !== null
                    ? (string) $row['patient_name']
                    : 'Patient name unavailable',
                'reported_at' => $reported,
                'reported_label' => (new DateTimeImmutable($reported, new DateTimeZone('Asia/Manila')))
                    ->format('M j, Y g:i A'),
                'resolved_at' => $resolved,
                'confirmed_at' => $confirmed,
            ];
        }, $rows);

        $week = array_fill(0, 7, 0);
        $critical = 0;
        $totalMinutes = 0;
        $durationCount = 0;
        foreach ($cases as $case) {
            if ($case['status'] === 'critical') {
                $critical++;
            }
            if ($case['confirmed_at'] !== null) {
                $minutes = (strtotime($case['confirmed_at']) - strtotime($case['reported_at'])) / 60;
                if ($minutes >= 0) {
                    $totalMinutes += $minutes;
                    $durationCount++;
                }
            }
            $day = (int) (new DateTimeImmutable(
                $case['reported_at'],
                new DateTimeZone('Asia/Manila')
            ))->format('N') - 1;
            $week[$day]++;
        }

        return [
            'cases' => $cases,
            'stats' => [
                'total' => count($cases),
                'critical' => $critical,
                'average_minutes' => $durationCount > 0
                    ? (int) round($totalMinutes / $durationCount)
                    : null,
                'weekly_counts' => $week,
            ],
        ];
    }

    public static function schedule(int $doctorId, string $month): array
    {
        $staffId = self::findStaffId($doctorId);
        if ($staffId === null) {
            return [];
        }
        $start = new DateTimeImmutable($month . '-01', new DateTimeZone('Asia/Manila'));
        $stmt = self::db()->prepare(
            "SELECT id, shift_date AS date, shift_type AS type,
                    shift_start AS start, shift_end AS end, shift_status AS status
             FROM staff_shifts
             WHERE staff_id = ? AND shift_date >= ? AND shift_date < ?
             ORDER BY shift_date, shift_start, id"
        );
        $stmt->execute([
            $staffId,
            $start->format('Y-m-d'),
            $start->modify('+1 month')->format('Y-m-d'),
        ]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'date' => (string) $row['date'],
            'type' => (string) ($row['type'] ?? 'morning'),
            'start' => $row['start'] !== null ? substr((string) $row['start'], 0, 5) : '',
            'end' => $row['end'] !== null ? substr((string) $row['end'], 0, 5) : '',
            'status' => (string) $row['status'],
        ], $stmt->fetchAll());
    }

    public static function saveSchedule(
        int $doctorId,
        ?int $scheduleId,
        array $data
    ): int|false {
        $pdo = self::db();
        $staffId = self::ensureStaffId($pdo, $doctorId);
        if ($scheduleId === null) {
            $stmt = $pdo->prepare(
                "INSERT INTO staff_shifts
                    (staff_id, shift_date, shift_type, shift_start, shift_end)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $staffId,
                $data['date'],
                $data['type'],
                $data['start'] !== '' ? $data['start'] : null,
                $data['end'] !== '' ? $data['end'] : null,
            ]);
            return (int) $pdo->lastInsertId();
        }
        $stmt = $pdo->prepare(
            "UPDATE staff_shifts
             SET shift_date = ?, shift_type = ?, shift_start = ?, shift_end = ?
             WHERE id = ? AND staff_id = ?"
        );
        $stmt->execute([
            $data['date'],
            $data['type'],
            $data['start'] !== '' ? $data['start'] : null,
            $data['end'] !== '' ? $data['end'] : null,
            $scheduleId,
            $staffId,
        ]);
        if ($stmt->rowCount() > 0) {
            return $scheduleId;
        }
        $exists = $pdo->prepare('SELECT 1 FROM staff_shifts WHERE id = ? AND staff_id = ?');
        $exists->execute([$scheduleId, $staffId]);

        return $exists->fetchColumn() !== false ? $scheduleId : false;
    }

    public static function deleteSchedule(int $doctorId, int $scheduleId): bool
    {
        $staffId = self::findStaffId($doctorId);
        if ($staffId === null) {
            return false;
        }
        $stmt = self::db()->prepare('DELETE FROM staff_shifts WHERE id = ? AND staff_id = ?');
        $stmt->execute([$scheduleId, $staffId]);

        return $stmt->rowCount() === 1;
    }

    private static function findStaffId(int $doctorId): ?int
    {
        $stmt = self::db()->prepare(
            "SELECT s.id
             FROM staff s
             JOIN user_type u ON u.id = s.user_id AND u.role = 'doctor'
             WHERE u.id = ? AND (s.hospital_id = u.hospital_id OR s.hospital_id IS NULL)
             ORDER BY (s.hospital_id = u.hospital_id) DESC, s.id DESC
             LIMIT 1"
        );
        $stmt->execute([$doctorId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private static function ensureStaffId(PDO $pdo, int $doctorId): int
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id, hospital_id, full_name, profile_image
                 FROM user_type WHERE id = ? AND role = 'doctor'
                 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$doctorId]);
            $doctor = $stmt->fetch();
            if ($doctor === false) {
                throw new RuntimeException('Doctor account is not available.');
            }
            $staffId = self::findStaffId($doctorId);
            if ($staffId === null) {
                $insert = $pdo->prepare(
                    "INSERT INTO staff
                        (hospital_id, user_id, profile_image, full_name, department)
                     VALUES (?, ?, ?, ?, 'Doctor')"
                );
                $insert->execute([
                    $doctor['hospital_id'],
                    $doctorId,
                    $doctor['profile_image'],
                    $doctor['full_name'],
                ]);
                $staffId = (int) $pdo->lastInsertId();
            }
            $pdo->commit();

            return $staffId;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function normalizeCase(array $row): array
    {
        $patient = trim((string) ($row['reported_patient_name'] ?? $row['patient_name'] ?? ''));
        if ($patient === '') {
            $gender = ucfirst((string) ($row['patient_gender'] ?? 'unknown'));
            $patient = $gender === 'Unknown' ? 'Patient details unavailable' : $gender;
            $age = $row['reported_patient_age'] ?? $row['patient_age'] ?? null;
            if ($age !== null) {
                $patient .= ', ' . (int) $age;
            }
        }
        $status = (string) $row['status'];

        return [
            'id' => (int) $row['incident_id'],
            'title' => (string) $row['incident_type'],
            'status' => $status,
            'status_label' => $status === 'dispatched' ? 'Urgent' : ucfirst($status),
            'location' => (string) $row['location'],
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'patient' => $patient,
            'patient_count' => (int) ($row['patient_count'] ?? 1),
            'patient_age' => isset($row['reported_patient_age'])
                ? (int) $row['reported_patient_age']
                : ($row['patient_age'] !== null ? (int) $row['patient_age'] : null),
            'patient_gender' => (string) ($row['patient_gender'] ?? 'unknown'),
            'eta_minutes' => $row['eta_minutes'] !== null ? (int) $row['eta_minutes'] : null,
            'responder' => (string) ($row['responder_name'] ?? ''),
            'hospital' => (string) ($row['hospital_name'] ?? ''),
            'reported_at' => (string) $row['reported_at'],
            'resolved_at' => $row['resolved_at'] !== null ? (string) $row['resolved_at'] : null,
            'notes' => (string) ($row['notes'] ?? ''),
            'heart_rate' => $row['heart_rate'] !== null ? (int) $row['heart_rate'] : null,
            'spo2' => $row['spo2'] !== null ? (int) $row['spo2'] : null,
            'blood_pressure' => $row['blood_pressure'] !== null
                ? (string) $row['blood_pressure']
                : null,
            'doctor_arrived_at' => $row['doctor_arrival_confirmed_at'] !== null
                ? (string) $row['doctor_arrival_confirmed_at']
                : null,
        ];
    }

    private static function timelineEntry(string $title, string $detail, string $time): array
    {
        return [
            'title' => $title,
            'detail' => $detail,
            'occurred_at' => $time,
            'time_label' => (new DateTimeImmutable($time, new DateTimeZone('Asia/Manila')))
                ->format('H:i'),
        ];
    }
}
