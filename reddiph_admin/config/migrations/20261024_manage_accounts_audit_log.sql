-- Ensure the shared audit schema required by account creation is available to Hospital Admin.
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `platform` varchar(32) DEFAULT NULL,
  `platform_scope` varchar(32) NOT NULL DEFAULT 'superadmin',
  `action_code` varchar(120) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `source_event_key` varchar(190) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audit_logs_source_event_key` (`source_event_key`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_audit_logs_platform_created` (`platform`,`created_at`),
  KEY `idx_audit_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `audit_logs`
  ADD COLUMN IF NOT EXISTS `platform` varchar(32) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `action_code` varchar(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `source_event_key` varchar(190) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `platform_scope` varchar(32) NOT NULL DEFAULT 'superadmin';

ALTER TABLE `audit_logs`
  ADD UNIQUE INDEX IF NOT EXISTS `uq_audit_logs_source_event_key` (`source_event_key`),
  ADD INDEX IF NOT EXISTS `idx_audit_logs_platform_created` (`platform`,`created_at`);
