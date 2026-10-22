<?php
namespace App\Models;

class AuditLogsModel
{
    public static function record(
        ?int $actorId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $details = [],
        string $scope = 'superadmin'
    ): void {
        DatabaseModel::requireColumns('audit_logs', [
            'actor_user_id', 'platform_scope', 'action', 'entity_type', 'entity_id',
            'details', 'ip_address', 'user_agent',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'INSERT INTO audit_logs (actor_user_id, platform_scope, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $encoded = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Could not encode the audit event.');
        }
        $stmt->execute([
            $actorId,
            $scope,
            $action,
            $entityType,
            $entityId,
            $encoded,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : null,
        ]);
    }

    public static function search(array $filters): array
    {
        DatabaseModel::requireColumns('audit_logs', [
            'actor_user_id', 'platform_scope', 'action', 'entity_type', 'entity_id',
            'details', 'ip_address', 'created_at',
        ]);
        $conditions = [];
        $params = [];
        if ($filters['scope'] !== '') {
            $conditions[] = 'a.platform_scope = ?';
            $params[] = $filters['scope'];
        }
        if ($filters['action'] !== '') {
            $conditions[] = 'a.action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        if ($filters['query'] !== '') {
            $conditions[] = '(a.action LIKE ? OR a.entity_type LIKE ? OR a.entity_id LIKE ? OR a.details LIKE ? OR u.full_name LIKE ?)';
            $term = '%' . $filters['query'] . '%';
            array_push($params, $term, $term, $term, $term, $term);
        }
        if ($filters['from'] !== '') {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
            $conditions[] = 'a.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $filters['to'];
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = DatabaseModel::connection()->prepare(
            "SELECT a.id, a.platform_scope, a.action, a.entity_type, a.entity_id, a.details,
                    a.ip_address, a.created_at, u.full_name AS actor_name
             FROM audit_logs a LEFT JOIN user_type u ON u.id = a.actor_user_id
             $where ORDER BY a.created_at DESC, a.id DESC LIMIT 250"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
