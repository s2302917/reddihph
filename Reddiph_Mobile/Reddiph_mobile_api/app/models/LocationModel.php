<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

/**
 * Reads hospitals and barangays used by the responder signup form.
 */
final class LocationModel
{
    private static array $columnCache = [];

    /**
     * @return array<int, array{id:int,name:string,barangays:array<int, array{id:int,name:string}>}>
     */
    public static function hospitalsWithBarangays(): array
    {
        $pdo = Database::connection();
        $hospitalConditions = self::activeConditions($pdo, 'hospitals', 'h');
        $barangayConditions = array_merge(
            self::activeConditions($pdo, 'barangays', 'b'),
            self::activeConditions($pdo, 'hospitals', 'h')
        );
        $hospitals = $pdo->query(
            'SELECT h.id, h.name FROM hospitals h'
            . ($hospitalConditions ? ' WHERE ' . implode(' AND ', $hospitalConditions) : '')
            . ' ORDER BY h.name'
        )->fetchAll();
        $barangays = $pdo->query(
            'SELECT b.id, b.hospital_id, b.name
             FROM barangays b JOIN hospitals h ON h.id = b.hospital_id'
            . ($barangayConditions ? ' WHERE ' . implode(' AND ', $barangayConditions) : '')
            . ' ORDER BY b.name'
        )->fetchAll();

        $byHospital = [];
        foreach ($barangays as $barangay) {
            $byHospital[(int) $barangay['hospital_id']][] = [
                'id' => (int) $barangay['id'],
                'name' => (string) $barangay['name'],
            ];
        }

        $result = [];
        foreach ($hospitals as $hospital) {
            $result[] = [
                'id' => (int) $hospital['id'],
                'name' => (string) $hospital['name'],
                'barangays' => $byHospital[(int) $hospital['id']] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Returns the hospital name when the barangay belongs to the hospital, otherwise null.
     */
    public static function hospitalNameForBarangay(int $hospitalId, int $barangayId): ?string
    {
        $pdo = Database::connection();
        $conditions = array_merge(
            ['b.id = ?', 'h.id = ?'],
            self::activeConditions($pdo, 'barangays', 'b'),
            self::activeConditions($pdo, 'hospitals', 'h')
        );
        $stmt = $pdo->prepare(
            'SELECT h.name FROM barangays b
             JOIN hospitals h ON h.id = b.hospital_id
             WHERE ' . implode(' AND ', $conditions)
        );
        $stmt->execute([$barangayId, $hospitalId]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    private static function activeConditions(\PDO $pdo, string $table, string $alias): array
    {
        $conditions = [];
        if (self::hasColumn($pdo, $table, 'is_active')) {
            $conditions[] = $alias . '.is_active = 1';
        }
        if (self::hasColumn($pdo, $table, 'archived_at')) {
            $conditions[] = $alias . '.archived_at IS NULL';
        }
        return $conditions;
    }

    private static function hasColumn(\PDO $pdo, string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (!array_key_exists($cacheKey, self::$columnCache)) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
            );
            $stmt->execute([$table, $column]);
            self::$columnCache[$cacheKey] = (int) $stmt->fetchColumn() > 0;
        }

        return self::$columnCache[$cacheKey];
    }
}
