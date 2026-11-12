-- Add the support schema required by Super Admin audit and directory actions.
-- Run this once against the shared MariaDB database after taking a backup.

ALTER TABLE hospitals
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL DEFAULT NULL;

ALTER TABLE barangays
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT(10) UNSIGNED NULL,
    platform_scope VARCHAR(32) NOT NULL DEFAULT 'superadmin',
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(64) NULL,
    entity_id VARCHAR(100) NULL,
    details LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_actor_created (actor_user_id, created_at),
    KEY idx_audit_logs_scope_created (platform_scope, created_at),
    KEY idx_audit_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
