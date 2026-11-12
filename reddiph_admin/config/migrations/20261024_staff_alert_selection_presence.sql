CREATE TABLE IF NOT EXISTS `staff_presence` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `last_seen_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `idx_staff_presence_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `staff_alerts`
  ADD COLUMN `request_key` char(36) DEFAULT NULL,
  ADD UNIQUE KEY `uq_staff_alert_request_recipient` (`request_key`, `recipient_user_id`);
