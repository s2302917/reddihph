<?php

declare(strict_types=1);

require_once __DIR__ . '/AuditActionCatalog.php';

use Reddiph\Shared\AuditActionCatalog;

final class AuditLogService
{
    public static function record(
        PDO $db,
        ?int $actorId,
        string $actionCode,
        ?string $entityType = null,
        ?string $entityId = null,
        array $details = [],
        ?string $sourceEventKey = null
    ): void {
        $description = AuditActionCatalog::describe($actionCode);
        try {
            $encodedDetails = json_encode(
                $details,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode the audit event.', 0, $exception);
        }

        $statement = $db->prepare(
            'INSERT INTO audit_logs
                (actor_user_id, platform, platform_scope, action_code, action, source_event_key,
                 entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?, \'mobile\', \'mobile\', ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $actorId,
            $actionCode,
            $description,
            $sourceEventKey,
            $entityType,
            $entityId,
            $encodedDetails,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : null,
        ]);
    }
}
