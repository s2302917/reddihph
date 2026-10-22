<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

/**
 * Reads hospitals and barangays used by the responder signup form.
 */
final class LocationModel
{
    /**
     * @return array<int, array{id:int,name:string,barangays:array<int, array{id:int,name:string}>}>
     */
    public static function hospitalsWithBarangays(): array
    {
        $pdo = Database::connection();
        $hospitals = $pdo->query('SELECT id, name FROM hospitals ORDER BY name')->fetchAll();
        $barangays = $pdo->query('SELECT id, hospital_id, name FROM barangays ORDER BY name')->fetchAll();

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
        $stmt = Database::connection()->prepare(
            'SELECT h.name FROM barangays b
             JOIN hospitals h ON h.id = b.hospital_id
             WHERE b.id = ? AND h.id = ?'
        );
        $stmt->execute([$barangayId, $hospitalId]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }
}
