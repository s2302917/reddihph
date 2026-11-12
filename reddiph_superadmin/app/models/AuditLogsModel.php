<?php
namespace App\Models;

require_once __DIR__ . '/AuditActionCatalog.php';

use Reddiph\Shared\AuditActionCatalog;

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
        $description = AuditActionCatalog::describe($action);
        if (!in_array($scope, ['website', 'mobile', 'superadmin', 'system'], true)) {
            throw new \InvalidArgumentException('Choose a valid audit platform.');
        }
        DatabaseModel::requireColumns('audit_logs', [
            'actor_user_id', 'platform', 'platform_scope', 'action_code', 'action',
            'source_event_key', 'entity_type', 'entity_id',
            'details', 'ip_address', 'user_agent',
        ]);
        $stmt = DatabaseModel::connection()->prepare(
            'INSERT INTO audit_logs
                (actor_user_id, platform, platform_scope, action_code, action, source_event_key,
                 entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)'
        );
        try {
            $encoded = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Could not encode the audit event.');
        }
        $stmt->execute([
            $actorId,
            $scope,
            $scope,
            $action,
            $description,
            $entityType,
            $entityId,
            $encoded,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : null,
        ]);
    }

    public static function count(array $filters): int
    {
        DatabaseModel::requireColumns('audit_logs', [
            'actor_user_id', 'platform', 'action_code', 'action', 'entity_type', 'entity_id',
            'details', 'ip_address', 'created_at',
        ]);
        [$where, $params] = self::conditions($filters);
        $stmt = DatabaseModel::connection()->prepare(
            "SELECT COUNT(*)
             FROM audit_logs a LEFT JOIN user_type u ON u.id = a.actor_user_id
             $where"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function search(array $filters, int $limit = 15, int $offset = 0): array
    {
        DatabaseModel::requireColumns('audit_logs', [
            'actor_user_id', 'platform', 'action_code', 'action', 'entity_type', 'entity_id',
            'details', 'ip_address', 'created_at',
        ]);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new \InvalidArgumentException('Choose a valid audit log page.');
        }
        [$where, $params] = self::conditions($filters);
        $params[] = $limit;
        $params[] = $offset;
        $stmt = DatabaseModel::connection()->prepare(
            "SELECT a.id, a.platform, a.action_code, a.action, a.entity_type, a.entity_id, a.details,
                    a.ip_address, a.created_at, u.full_name AS actor_name
             FROM audit_logs a LEFT JOIN user_type u ON u.id = a.actor_user_id
             $where ORDER BY a.created_at DESC, a.id DESC LIMIT ? OFFSET ?"
        );
        foreach ($params as $index => $value) {
            $stmt->bindValue(
                $index + 1,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function conditions(array $filters): array
    {
        $conditions = [];
        $params = [];
        $platform = $filters['platform'] ?? $filters['scope'] ?? '';
        if ($platform !== '') {
            $conditions[] = "COALESCE(NULLIF(a.platform, ''), a.platform_scope) = ?";
            $params[] = $platform;
        }
        if ($filters['action'] !== '') {
            $conditions[] = '(a.action LIKE ? OR a.action_code LIKE ?)';
            $term = '%' . $filters['action'] . '%';
            array_push($params, $term, $term);
        }
        if ($filters['query'] !== '') {
            $conditions[] = '(a.action LIKE ? OR a.action_code LIKE ? OR a.entity_type LIKE ? OR a.entity_id LIKE ? OR a.details LIKE ? OR u.full_name LIKE ?)';
            $term = '%' . $filters['query'] . '%';
            array_push($params, $term, $term, $term, $term, $term, $term);
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
        return [$where, $params];
    }
}
