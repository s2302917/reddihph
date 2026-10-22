-- Run after the existing false-report and incident timeline migrations.
-- Existing accounts keep the default approved state; new responders and volunteers start pending.
ALTER TABLE `user_type`
  ADD COLUMN `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved'
    AFTER `banned_until`,
  ADD COLUMN `reviewed_at` datetime DEFAULT NULL AFTER `approval_status`,
  ADD COLUMN `reviewed_by_user_id` int(10) UNSIGNED DEFAULT NULL AFTER `reviewed_at`,
  ADD KEY `idx_user_type_barangay_approval` (`barangay_id`,`approval_status`,`role`),
  ADD CONSTRAINT `fk_user_type_reviewed_by`
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE `barangay_admin_signup_attempts` (
  `ip_hash` char(64) NOT NULL,
  `window_started_at` datetime NOT NULL,
  `attempt_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`ip_hash`),
  KEY `idx_signup_attempt_lock` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
