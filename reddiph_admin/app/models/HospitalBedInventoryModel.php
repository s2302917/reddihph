<?php

class HospitalBedInventoryModel
{
    private const MAX_BEDS = 10000;

    public static function initialize(PDO $connection, int $hospitalId): array
    {
        $connection->beginTransaction();
        try {
            $hospital = self::lockHospital($connection, $hospitalId);
            self::seedIfEmpty($connection, $hospital);
            self::resizeRows($connection, $hospitalId, (int) $hospital['total_beds']);
            self::synchronizeAggregates($connection, $hospitalId);
            $connection->commit();

            return $hospital;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function resize(PDO $connection, int $hospitalId, int $newMaximum): void
    {
        if ($newMaximum < 0 || $newMaximum > self::MAX_BEDS) {
            throw new InvalidArgumentException('Maximum bed capacity must be between 0 and ' . self::MAX_BEDS . '.');
        }

        $connection->beginTransaction();
        try {
            $hospital = self::lockHospital($connection, $hospitalId);
            self::seedIfEmpty($connection, $hospital);
            self::resizeRows($connection, $hospitalId, $newMaximum);

            $updateHospital = $connection->prepare(
                'UPDATE hospitals SET total_beds = :total_beds WHERE id = :hospital_id'
            );
            $updateHospital->execute(['total_beds' => $newMaximum, 'hospital_id' => $hospitalId]);
            self::synchronizeAggregates($connection, $hospitalId);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function adjustAvailability(PDO $connection, int $hospitalId, int $change): void
    {
        if (!in_array($change, [-1, 1], true)) {
            throw new InvalidArgumentException('Choose to add or subtract exactly one available bed.');
        }

        $connection->beginTransaction();
        try {
            $hospital = self::lockHospital($connection, $hospitalId);
            self::seedIfEmpty($connection, $hospital);

            $statusFrom = $change > 0 ? 'occupied' : 'ready';
            $statusTo = $change > 0 ? 'ready' : 'occupied';
            $bedQuery = $connection->prepare(
                "SELECT id
                FROM hospital_beds
                WHERE hospital_id = :hospital_id AND status = :status
                ORDER BY FIELD(bed_type, 'er', 'pediatric'), bed_number
                LIMIT 1
                FOR UPDATE"
            );
            $bedQuery->execute(['hospital_id' => $hospitalId, 'status' => $statusFrom]);
            $bedId = $bedQuery->fetchColumn();
            if ($bedId === false) {
                throw new InvalidArgumentException(
                    $change > 0
                        ? 'There are no occupied beds available to mark ready.'
                        : 'There are no ready beds left to subtract.'
                );
            }

            $update = $connection->prepare(
                'UPDATE hospital_beds SET status = :status WHERE id = :bed_id AND hospital_id = :hospital_id'
            );
            $update->execute(['status' => $statusTo, 'bed_id' => $bedId, 'hospital_id' => $hospitalId]);
            self::synchronizeAggregates($connection, $hospitalId);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    private static function lockHospital(PDO $connection, int $hospitalId): array
    {
        $statement = $connection->prepare(
            'SELECT id, available_beds, total_beds FROM hospitals WHERE id = :hospital_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['hospital_id' => $hospitalId]);
        $hospital = $statement->fetch();
        if (!$hospital) {
            throw new InvalidArgumentException('Hospital record was not found.');
        }
        if ((int) $hospital['total_beds'] > self::MAX_BEDS) {
            throw new RuntimeException('Hospital capacity exceeds the supported per-bed inventory limit.');
        }

        return $hospital;
    }

    private static function seedIfEmpty(PDO $connection, array $hospital): void
    {
        $hospitalId = (int) $hospital['id'];
        $count = $connection->prepare('SELECT COUNT(*) FROM hospital_beds WHERE hospital_id = :hospital_id');
        $count->execute(['hospital_id' => $hospitalId]);
        if ((int) $count->fetchColumn() !== 0) {
            return;
        }

        $total = (int) $hospital['total_beds'];
        $available = (int) $hospital['available_beds'];
        if ($available > $total) {
            throw new RuntimeException('Available beds exceed the recorded maximum capacity.');
        }

        $erTotal = (int) ceil($total / 2);
        $erAvailable = (int) ceil($available / 2);
        $pediatricAvailable = (int) floor($available / 2);
        $insert = $connection->prepare(
            'INSERT INTO hospital_beds (hospital_id, bed_type, bed_number, status)
             VALUES (:hospital_id, :bed_type, :bed_number, :status)'
        );
        for ($number = 1; $number <= $erTotal; $number++) {
            $insert->execute([
                'hospital_id' => $hospitalId,
                'bed_type' => 'er',
                'bed_number' => $number,
                'status' => $number <= $erAvailable ? 'ready' : 'occupied',
            ]);
        }
        $pediatricTotal = $total - $erTotal;
        for ($number = 1; $number <= $pediatricTotal; $number++) {
            $insert->execute([
                'hospital_id' => $hospitalId,
                'bed_type' => 'pediatric',
                'bed_number' => $number,
                'status' => $number <= $pediatricAvailable ? 'ready' : 'occupied',
            ]);
        }
    }

    private static function resizeRows(PDO $connection, int $hospitalId, int $newMaximum): void
    {
        $newTargets = ['er' => (int) ceil($newMaximum / 2), 'pediatric' => (int) floor($newMaximum / 2)];

        foreach ($newTargets as $type => $target) {
            $rows = $connection->prepare(
                'SELECT id, bed_number, status
                 FROM hospital_beds
                 WHERE hospital_id = :hospital_id AND bed_type = :bed_type
                 ORDER BY bed_number DESC
                 FOR UPDATE'
            );
            $rows->execute(['hospital_id' => $hospitalId, 'bed_type' => $type]);
            $existing = $rows->fetchAll();
            $existingCount = count($existing);

            if ($existingCount > $target) {
                $removeCount = $existingCount - $target;
                $removals = array_slice($existing, 0, $removeCount);
                foreach ($removals as $bed) {
                    if ($bed['status'] !== 'ready') {
                        throw new InvalidArgumentException(
                            'Capacity cannot be reduced because ' . strtoupper($type) . ' Bed ' .
                            (int) $bed['bed_number'] . ' is ' . $bed['status'] . '.'
                        );
                    }
                }
                $delete = $connection->prepare(
                    'DELETE FROM hospital_beds WHERE id = :bed_id AND hospital_id = :hospital_id'
                );
                foreach ($removals as $bed) {
                    $delete->execute(['bed_id' => $bed['id'], 'hospital_id' => $hospitalId]);
                }
            } elseif ($existingCount < $target) {
                $insert = $connection->prepare(
                    "INSERT INTO hospital_beds (hospital_id, bed_type, bed_number, status)
                     VALUES (:hospital_id, :bed_type, :bed_number, 'ready')"
                );
                for ($number = $existingCount + 1; $number <= $target; $number++) {
                    $insert->execute([
                        'hospital_id' => $hospitalId,
                        'bed_type' => $type,
                        'bed_number' => $number,
                    ]);
                }
            }
        }
    }

    public static function synchronizeAggregates(PDO $connection, int $hospitalId): void
    {
        $statement = $connection->prepare(
            "UPDATE hospitals
             SET available_beds = (
                 SELECT COUNT(*) FROM hospital_beds
                 WHERE hospital_id = :beds_hospital_id AND status IN ('ready', 'preparing')
             )
             WHERE id = :hospital_id"
        );
        $statement->execute(['beds_hospital_id' => $hospitalId, 'hospital_id' => $hospitalId]);

        $facility = $connection->prepare(
            "INSERT INTO facility_status (hospital_id, metric_key, current_value, max_value, status_label)
             SELECT h.id, 'bed_capacity',
                    (SELECT COUNT(*) FROM hospital_beds b WHERE b.hospital_id = h.id AND b.status IN ('ready', 'preparing')),
                    h.total_beds, NULL
             FROM hospitals h
             WHERE h.id = :hospital_id
             ON DUPLICATE KEY UPDATE
                current_value = VALUES(current_value),
                max_value = VALUES(max_value),
                status_label = VALUES(status_label)"
        );
        $facility->execute(['hospital_id' => $hospitalId]);
    }
}
