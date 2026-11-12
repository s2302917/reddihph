<?php
namespace App\Models;

class FacilitiesModel
{
    private const PAGE_SIZE = 15;

    public static function supportsArchive(): bool
    {
        return DatabaseModel::hasColumn('hospitals', 'archived_at')
            && DatabaseModel::hasColumn('barangays', 'archived_at')
            && DatabaseModel::hasColumn('hospitals', 'is_active')
            && DatabaseModel::hasColumn('barangays', 'is_active');
    }

    public static function supportsAuditLogging(): bool
    {
        if (!DatabaseModel::hasTable('audit_logs')) {
            return false;
        }
        foreach ([
            'actor_user_id', 'platform', 'platform_scope', 'action_code', 'action',
            'source_event_key', 'entity_type', 'entity_id', 'details', 'ip_address', 'user_agent',
        ] as $column) {
            if (!DatabaseModel::hasColumn('audit_logs', $column)) {
                return false;
            }
        }
        return true;
    }

    public static function directoryCounts(string $type): array
    {
        [$table] = self::directoryTable($type);
        DatabaseModel::requireColumns($table, ['id', 'name']);
        $archiveColumn = DatabaseModel::hasColumn($table, 'archived_at');
        $activeExpression = $archiveColumn ? 'SUM(archived_at IS NULL)' : 'COUNT(*)';
        $archivedExpression = $archiveColumn ? 'SUM(archived_at IS NOT NULL)' : '0';
        $stmt = DatabaseModel::connection()->query(
            "SELECT COUNT(*) AS total, $activeExpression AS active, $archivedExpression AS archived FROM $table"
        );
        $counts = $stmt->fetch();
        return [
            'total' => (int) $counts['total'],
            'active' => (int) $counts['active'],
            'archived' => (int) $counts['archived'],
        ];
    }

    public static function countDirectory(string $type, string $status, string $query): int
    {
        [$table] = self::directoryTable($type);
        DatabaseModel::requireColumns($table, $type === 'hospitals'
            ? ['id', 'name', 'address', 'specialties']
            : ['id', 'name', 'slug', 'hospital_id']);
        [$where, $params] = self::directoryConditions($table, $type, $status, $query);
        $from = $type === 'barangays'
            ? 'barangays b LEFT JOIN hospitals h ON h.id = b.hospital_id'
            : 'hospitals h';
        $stmt = DatabaseModel::connection()->prepare("SELECT COUNT(*) FROM $from" . ($where !== '' ? " WHERE $where" : ''));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function listDirectory(string $type, string $status, string $query, int $limit, int $offset): array
    {
        [$table] = self::directoryTable($type);
        DatabaseModel::requireColumns($table, $type === 'hospitals'
            ? ['id', 'name', 'address', 'specialties', 'available_beds', 'total_beds', 'created_at']
            : ['id', 'name', 'slug', 'hospital_id', 'sort_order', 'active_dispatch_count']);
        [$where, $params] = self::directoryConditions($table, $type, $status, $query);
        $active = DatabaseModel::hasColumn($table, 'is_active') ? 'is_active' : '1 AS is_active';
        $archived = DatabaseModel::hasColumn($table, 'archived_at') ? 'archived_at' : 'NULL AS archived_at';
        if ($type === 'hospitals') {
            $select = "SELECT id, name, address, specialties, available_beds, total_beds, created_at, $active, $archived FROM hospitals h";
            $orderBy = 'name, id';
        } else {
            DatabaseModel::requireColumns('hospitals', ['id', 'name']);
            $active = DatabaseModel::hasColumn('barangays', 'is_active') ? 'b.is_active' : '1';
            $archived = DatabaseModel::hasColumn('barangays', 'archived_at') ? 'b.archived_at' : 'NULL';
            $select = "SELECT b.id, b.name, b.slug, b.hospital_id, b.sort_order, b.active_dispatch_count,
                              $active AS is_active, $archived AS archived_at, h.name AS hospital_name
                       FROM barangays b LEFT JOIN hospitals h ON h.id = b.hospital_id";
            $orderBy = 'b.name, b.id';
        }

        $limit = max(1, min(self::PAGE_SIZE, $limit));
        $offset = max(0, $offset);
        $params[] = $limit;
        $params[] = $offset;
        $stmt = DatabaseModel::connection()->prepare(
            $select . ($where !== '' ? " WHERE $where" : '') . " ORDER BY $orderBy LIMIT ? OFFSET ?"
        );
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function findDirectoryRecord(string $type, int $id, bool $includeArchived = false): ?array
    {
        [$table] = self::directoryTable($type);
        DatabaseModel::requireColumns($table, $type === 'hospitals'
            ? ['id', 'name', 'address', 'specialties', 'available_beds', 'total_beds']
            : ['id', 'name', 'slug', 'hospital_id', 'sort_order']);
        if ($type === 'hospitals') {
            $stmt = DatabaseModel::connection()->prepare(
                'SELECT id, name, address, specialties, available_beds, total_beds FROM hospitals
                 WHERE id = ?' . (
                    !$includeArchived && DatabaseModel::hasColumn($table, 'archived_at')
                        ? ' AND archived_at IS NULL'
                        : ''
                )
            );
        } else {
            $stmt = DatabaseModel::connection()->prepare(
                'SELECT id, name, slug, hospital_id, sort_order FROM barangays
                 WHERE id = ?' . (
                    !$includeArchived && DatabaseModel::hasColumn($table, 'archived_at')
                        ? ' AND archived_at IS NULL'
                        : ''
                )
            );
        }
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        return $record ?: null;
    }

    public static function activeHospitalsForForms(): array
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'name']);
        $conditions = [];
        if (DatabaseModel::hasColumn('hospitals', 'archived_at')) {
            $conditions[] = 'archived_at IS NULL';
        }
        if (DatabaseModel::hasColumn('hospitals', 'is_active')) {
            $conditions[] = 'is_active = 1';
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        return DatabaseModel::connection()->query(
            'SELECT id, name FROM hospitals' . $where . ' ORDER BY name, id'
        )->fetchAll();
    }

    private static function directoryTable(string $type): array
    {
        return match ($type) {
            'hospitals' => ['hospitals'],
            'barangays' => ['barangays'],
            default => throw new \InvalidArgumentException('Choose hospitals or barangays.'),
        };
    }

    private static function directoryConditions(
        string $table,
        string $type,
        string $status,
        string $query
    ): array {
        if (!in_array($status, ['active', 'archived'], true)) {
            throw new \InvalidArgumentException('Choose active or archived directory records.');
        }
        $conditions = [];
        $params = [];
        $alias = $type === 'barangays' ? 'b.' : '';
        if (DatabaseModel::hasColumn($table, 'archived_at')) {
            $conditions[] = $alias . 'archived_at IS ' . ($status === 'archived' ? 'NOT ' : '') . 'NULL';
        } elseif ($status === 'archived') {
            $conditions[] = '1 = 0';
        }
        if ($query !== '') {
            $columns = $type === 'hospitals'
                ? ['h.name', 'h.address', 'h.specialties']
                : ['b.name', 'b.slug', 'h.name'];
            $parts = array_map(static fn (string $column): string => "$column LIKE ?", $columns);
            foreach ($columns as $_) {
                $params[] = '%' . $query . '%';
            }
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }
        return [implode(' AND ', $conditions), $params];
    }

    public static function createHospital(array $data): int
    {
        DatabaseModel::requireColumns('hospitals', [
            'name', 'address', 'specialties', 'available_beds', 'total_beds',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'INSERT INTO hospitals (name, address, specialties, available_beds, total_beds)
             VALUES (:name, :address, :specialties, :available_beds, :total_beds)'
        );
        $stmt->execute($data);
        return (int) DatabaseModel::connection()->lastInsertId();
    }

    public static function updateHospital(int $id, array $data): void
    {
        DatabaseModel::requireColumns('hospitals', [
            'id', 'name', 'address', 'specialties', 'available_beds', 'total_beds',
        ]);
        $condition = DatabaseModel::hasColumn('hospitals', 'is_active') ? ' AND is_active = 1' : '';
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE hospitals SET name = :name, address = :address, specialties = :specialties,
                available_beds = :available_beds, total_beds = :total_beds WHERE id = :id' . $condition
        );
        $stmt->execute($data + ['id' => $id]);
        self::assertUpdated($stmt->rowCount(), 'hospital', $id);
    }

    public static function archiveHospital(int $id): void
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'is_active', 'archived_at']);
        DatabaseModel::requireColumns('barangays', ['hospital_id', 'archived_at']);
        $stmt = DatabaseModel::connection()->prepare(
            'SELECT COUNT(*) FROM barangays WHERE hospital_id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Reassign or archive this hospital’s barangays before archiving the hospital.');
        }
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE hospitals SET is_active = 0, archived_at = CURRENT_TIMESTAMP WHERE id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$id]);
        self::assertArchiveTransition($stmt->rowCount(), 'hospital');
    }

    public static function restoreHospital(int $id): void
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'is_active', 'archived_at']);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE hospitals SET is_active = 1, archived_at = NULL WHERE id = ? AND is_active = 0'
        );
        $stmt->execute([$id]);
        self::assertArchived($stmt->rowCount(), 'hospital', $id);
    }

    public static function createBarangay(array $data): int
    {
        DatabaseModel::requireColumns('barangays', [
            'name', 'slug', 'hospital_id', 'sort_order',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'INSERT INTO barangays (name, slug, hospital_id, sort_order) VALUES (:name, :slug, :hospital_id, :sort_order)'
        );
        $stmt->execute($data);
        return (int) DatabaseModel::connection()->lastInsertId();
    }

    public static function updateBarangay(int $id, array $data): void
    {
        DatabaseModel::requireColumns('barangays', [
            'id', 'name', 'slug', 'hospital_id', 'sort_order',
        ]);
        $condition = DatabaseModel::hasColumn('barangays', 'archived_at') ? ' AND archived_at IS NULL' : '';
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE barangays SET name = :name, slug = :slug, hospital_id = :hospital_id,
                sort_order = :sort_order WHERE id = :id' . $condition
        );
        $stmt->execute($data + ['id' => $id]);
        self::assertUpdated($stmt->rowCount(), 'barangay', $id);
    }

    public static function archiveBarangay(int $id): void
    {
        DatabaseModel::requireColumns('barangays', ['id', 'is_active', 'archived_at']);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE barangays SET is_active = 0, archived_at = CURRENT_TIMESTAMP WHERE id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$id]);
        self::assertArchiveTransition($stmt->rowCount(), 'barangay');
    }

    public static function setBarangayVisibility(int $id, bool $visible): void
    {
        DatabaseModel::requireColumns('barangays', ['id', 'is_active', 'archived_at']);
        $db = DatabaseModel::connection();
        $stmt = $db->prepare(
            'UPDATE barangays SET is_active = ? WHERE id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$visible ? 1 : 0, $id]);
        if ($stmt->rowCount() > 0) {
            return;
        }

        $current = $db->prepare('SELECT is_active, archived_at FROM barangays WHERE id = ?');
        $current->execute([$id]);
        $barangay = $current->fetch();
        if (!$barangay || $barangay['archived_at'] !== null) {
            throw new \RuntimeException('The selected barangay was not found or has been archived.');
        }
        if ((bool) $barangay['is_active'] !== $visible) {
            throw new \RuntimeException('The barangay visibility could not be updated.');
        }
    }

    public static function restoreBarangay(int $id): void
    {
        DatabaseModel::requireColumns('barangays', ['id', 'hospital_id', 'is_active', 'archived_at']);
        $parent = DatabaseModel::connection()->prepare(
            'SELECT h.is_active FROM barangays b
             LEFT JOIN hospitals h ON h.id = b.hospital_id
             WHERE b.id = ?'
        );
        $parent->execute([$id]);
        if ((int) $parent->fetchColumn() !== 1) {
            throw new \RuntimeException('Restore the barangay’s hospital before restoring this barangay.');
        }
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE barangays SET is_active = 1, archived_at = NULL WHERE id = ? AND is_active = 0'
        );
        $stmt->execute([$id]);
        self::assertArchived($stmt->rowCount(), 'barangay', $id);
    }

    private static function assertArchived(int $affectedRows, string $type, int $id): void
    {
        if ($affectedRows === 0) {
            throw new \RuntimeException('The selected ' . $type . ' was not found or is not archived.');
        }
    }

    private static function assertArchiveTransition(int $affectedRows, string $type): void
    {
        if ($affectedRows === 0) {
            throw new \RuntimeException('The selected ' . $type . ' was not found or is already archived.');
        }
    }

    private static function assertUpdated(int $affectedRows, string $type, int $id): void
    {
        if ($affectedRows === 0) {
            $stmt = DatabaseModel::connection()->prepare(
                'SELECT COUNT(*) FROM ' . ($type === 'hospital' ? 'hospitals' : 'barangays') . ' WHERE id = ?'
            );
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() === 0) {
                throw new \RuntimeException('The selected ' . $type . ' was not found or is already archived.');
            }
        }
    }
}
