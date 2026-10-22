<?php
namespace App\Models;

class FacilitiesModel
{
    public static function hospitals(): array
    {
        DatabaseModel::requireColumns('hospitals', [
            'id', 'name', 'address', 'available_beds', 'total_beds', 'specialties', 'is_active',
        ]);
        return DatabaseModel::connection()->query(
            'SELECT id, name, address, available_beds, total_beds, specialties
             FROM hospitals WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    public static function barangays(): array
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'name']);
        DatabaseModel::requireColumns('barangays', [
            'id', 'name', 'slug', 'hospital_id', 'active_dispatch_count', 'sort_order', 'is_active', 'archived_at',
        ]);
        return DatabaseModel::connection()->query(
            'SELECT b.id, b.name, b.slug, b.hospital_id, b.active_dispatch_count, b.sort_order,
                    b.is_active, h.name AS hospital_name
             FROM barangays b
             LEFT JOIN hospitals h ON h.id = b.hospital_id
             WHERE b.archived_at IS NULL
             ORDER BY h.name, b.sort_order, b.name'
        )->fetchAll();
    }

    public static function catalog(): array
    {
        $hospitals = self::hospitals();
        $barangays = self::barangays();
        foreach ($hospitals as &$hospital) {
            $hospital['barangays'] = array_values(array_filter(
                $barangays,
                static fn (array $barangay): bool => (int) $barangay['hospital_id'] === (int) $hospital['id']
            ));
        }
        unset($hospital);

        return ['hospitals' => $hospitals, 'barangayCount' => count($barangays)];
    }

    public static function archived(): array
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'name', 'archived_at']);
        DatabaseModel::requireColumns('barangays', ['id', 'name', 'archived_at']);
        return [
            'hospitals' => DatabaseModel::connection()->query(
                'SELECT id, name FROM hospitals WHERE archived_at IS NOT NULL ORDER BY name'
            )->fetchAll(),
            'barangays' => DatabaseModel::connection()->query(
                'SELECT id, name FROM barangays WHERE archived_at IS NOT NULL ORDER BY name'
            )->fetchAll(),
        ];
    }

    public static function createHospital(array $data): int
    {
        DatabaseModel::requireColumns('hospitals', [
            'name', 'address', 'specialties', 'available_beds', 'total_beds', 'is_active', 'archived_at',
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
            'id', 'name', 'address', 'specialties', 'available_beds', 'total_beds', 'is_active', 'archived_at',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE hospitals SET name = :name, address = :address, specialties = :specialties,
                available_beds = :available_beds, total_beds = :total_beds WHERE id = :id AND is_active = 1'
        );
        $stmt->execute($data + ['id' => $id]);
        self::assertUpdated($stmt->rowCount(), 'hospital', $id);
    }

    public static function archiveHospital(int $id): void
    {
        DatabaseModel::requireColumns('hospitals', ['id', 'is_active', 'archived_at']);
        DatabaseModel::requireColumns('barangays', ['hospital_id', 'is_active']);
        $stmt = DatabaseModel::connection()->prepare('SELECT COUNT(*) FROM barangays WHERE hospital_id = ? AND is_active = 1');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Reassign or archive this hospital’s barangays before archiving the hospital.');
        }
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE hospitals SET is_active = 0, archived_at = CURRENT_TIMESTAMP WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([$id]);
        self::assertUpdated($stmt->rowCount(), 'hospital', $id);
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
            'name', 'slug', 'hospital_id', 'sort_order', 'is_active', 'archived_at',
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
            'id', 'name', 'slug', 'hospital_id', 'sort_order', 'is_active', 'archived_at',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'UPDATE barangays SET name = :name, slug = :slug, hospital_id = :hospital_id,
                sort_order = :sort_order WHERE id = :id AND archived_at IS NULL'
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
        self::assertUpdated($stmt->rowCount(), 'barangay', $id);
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
