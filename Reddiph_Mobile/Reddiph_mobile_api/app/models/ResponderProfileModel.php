<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/ResponderAvailability.php';

final class ResponderProfileModel
{
    public static function profile(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.full_name, u.profile_image,
                    rp.driver_responder_enabled, rp.vehicle_type, rp.call_sign,
                    rp.plate_number, rp.duty_schedule_configured,
                    rp.availability_enabled
             FROM user_type u
             LEFT JOIN responder_profiles rp ON rp.user_id = u.id
             WHERE u.id = ? AND u.role = \'responder\'
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $scheduleStmt = Database::connection()->prepare(
            'SELECT day_of_week, start_time, end_time
             FROM responder_duty_schedules
             WHERE user_id = ?
             ORDER BY day_of_week, start_time, id'
        );
        $scheduleStmt->execute([$userId]);
        $periods = array_map(
            static fn(array $period): array => [
                'day_of_week' => (int) $period['day_of_week'],
                'start_time' => (string) $period['start_time'],
                'end_time' => (string) $period['end_time'],
            ],
            $scheduleStmt->fetchAll()
        );
        $scheduleConfigured = (bool) $row['duty_schedule_configured'];
        $availabilityEnabled = $row['availability_enabled'] === null
            ? false
            : (bool) $row['availability_enabled'];
        $driverEnabled = (bool) $row['driver_responder_enabled'];
        $vehicle = null;
        if (
            $row['vehicle_type'] !== null
            && $row['call_sign'] !== null
            && $row['plate_number'] !== null
        ) {
            $vehicle = [
                'vehicle_type' => (string) $row['vehicle_type'],
                'call_sign' => (string) $row['call_sign'],
                'plate_number' => (string) $row['plate_number'],
            ];
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

        return [
            'full_name' => (string) $row['full_name'],
            'profile_image' => $row['profile_image'] !== null
                ? (string) $row['profile_image']
                : null,
            'profile_image_url' => self::profileImageUrl(
                $row['profile_image'] !== null ? (string) $row['profile_image'] : null
            ),
            'driver_responder_enabled' => $driverEnabled,
            'vehicle' => $vehicle,
            'schedule_configured' => $scheduleConfigured,
            'availability_enabled' => $availabilityEnabled,
            'is_on_duty' => $scheduleConfigured
                ? $availabilityEnabled && ResponderAvailability::isOnDuty($periods, $now)
                : false,
            'schedules' => $periods,
        ];
    }

    public static function saveVehicle(
        int $userId,
        bool $enabled,
        ?string $vehicleType = null,
        ?string $callSign = null,
        ?string $plateNumber = null
    ): void {
        $hasVehicleData = $vehicleType !== null
            || $callSign !== null
            || $plateNumber !== null;
        if ($enabled || $hasVehicleData) {
            if ($vehicleType === null || !in_array($vehicleType, ['Ambulance', 'PTV'], true)) {
                throw new InvalidArgumentException('Choose Ambulance or PTV.');
            }
            if ($callSign === null || trim($callSign) === '') {
                throw new InvalidArgumentException('Enter a valid call sign.');
            }
            if ($plateNumber === null || trim($plateNumber) === '') {
                throw new InvalidArgumentException('Enter a valid plate number.');
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::lockResponder($pdo, $userId);
            $stmt = $pdo->prepare(
                'INSERT INTO responder_profiles
                    (user_id, driver_responder_enabled, vehicle_type, call_sign, plate_number)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    driver_responder_enabled = VALUES(driver_responder_enabled),
                          vehicle_type = COALESCE(VALUES(vehicle_type), vehicle_type),
                          call_sign = COALESCE(VALUES(call_sign), call_sign),
                          plate_number = COALESCE(VALUES(plate_number), plate_number)'
            );
            $stmt->execute([
                $userId,
                $enabled ? 1 : 0,
                $vehicleType,
                $callSign,
                $plateNumber,
            ]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function saveSchedule(
        int $userId,
        array $periods,
        bool $availabilityEnabled
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::lockResponder($pdo, $userId);
            $pdo->prepare(
                'INSERT INTO responder_profiles
                    (user_id, duty_schedule_configured, availability_enabled)
                 VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE
                    duty_schedule_configured = 1,
                    availability_enabled = VALUES(availability_enabled)'
            )->execute([$userId, $availabilityEnabled ? 1 : 0]);
            $pdo->prepare(
                'DELETE FROM responder_duty_schedules WHERE user_id = ?'
            )->execute([$userId]);
            $insert = $pdo->prepare(
                'INSERT INTO responder_duty_schedules
                    (user_id, day_of_week, start_time, end_time)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($periods as $period) {
                $insert->execute([
                    $userId,
                    (int) $period['day_of_week'],
                    $period['start_time'],
                    $period['end_time'],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function isEligibleNow(int $userId): bool
    {
        $profile = self::profile($userId);
        if (
            $profile === null
            || !$profile['schedule_configured']
            || !$profile['is_on_duty']
        ) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            "SELECT approval_status, account_archived_at, banned_until,
                    login_disabled_reason
             FROM user_type WHERE id = ? AND role = 'responder' LIMIT 1"
        );
        $stmt->execute([$userId]);
        $account = $stmt->fetch();
        if (
            $account === false
            || $account['approval_status'] !== 'approved'
            || $account['account_archived_at'] !== null
            || trim((string) $account['login_disabled_reason']) !== ''
        ) {
            return false;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        if ($account['banned_until'] !== null) {
            $bannedUntil = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string) $account['banned_until'],
                new DateTimeZone('Asia/Manila')
            );
            if ($bannedUntil === false || $bannedUntil > $now) {
                return false;
            }
        }

        return true;
    }

    public static function saveProfileImage(int $userId, ?string $image): ?string
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::lockResponder($pdo, $userId);
            $select = $pdo->prepare(
                "SELECT profile_image FROM user_type
                 WHERE id = ? AND role = 'responder' FOR UPDATE"
            );
            $select->execute([$userId]);
            $previous = $select->fetchColumn();
            if ($previous === false) {
                throw new InvalidArgumentException('This account is not a responder.');
            }

            $update = $pdo->prepare(
                "UPDATE user_type SET profile_image = ?
                 WHERE id = ? AND role = 'responder'"
            );
            $update->execute([$image, $userId]);
            $pdo->commit();

            return $previous === null ? null : (string) $previous;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function availabilityCondition(): string
    {
        return "(
            rp.user_id IS NOT NULL
            AND rp.duty_schedule_configured = 1
            AND rp.availability_enabled = 1
            AND EXISTS (
                SELECT 1 FROM responder_duty_schedules ds
                WHERE ds.user_id = u.id
                  AND (
                    (ds.day_of_week = ((DAYOFWEEK(CURRENT_DATE()) + 5) % 7 + 1)
                     AND ((ds.start_time < ds.end_time
                           AND CURTIME() >= ds.start_time
                           AND CURTIME() < ds.end_time)
                          OR (ds.start_time > ds.end_time
                              AND CURTIME() >= ds.start_time)))
                    OR (ds.day_of_week = ((DAYOFWEEK(CURRENT_DATE()) + 4) % 7 + 1)
                        AND ds.start_time > ds.end_time
                        AND CURTIME() < ds.end_time)
                  )
            )
        )";
    }

    private static function lockResponder(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare(
            "SELECT id FROM user_type
             WHERE id = ? AND role = 'responder'
             FOR UPDATE"
        );
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException('This account is not a responder.');
        }
    }

    private static function profileImageUrl(?string $image): ?string
    {
        if ($image === null || trim($image) === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $image) === 1 || $image[0] === '/') {
            return $image;
        }

        $filename = basename(str_replace('\\', '/', $image));
        return $filename === ''
            ? null
            : '/reddiph_admin/assets/images/profiles/' . rawurlencode($filename);
    }
}