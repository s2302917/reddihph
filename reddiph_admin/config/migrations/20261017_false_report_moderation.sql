-- Run after 20261017_user_type_barangay_admin_role.sql.
-- Links signed-in SOS creators to cases and adds reviewed false-report moderation.
-- Run once against the shared Reddiph database.

ALTER TABLE `incidents`
  ADD COLUMN `reporter_user_id` int(10) UNSIGNED DEFAULT NULL AFTER `reported_by`,
  ADD KEY `idx_incidents_reporter_user` (`reporter_user_id`),
  ADD CONSTRAINT `fk_incidents_reporter_user`
    FOREIGN KEY (`reporter_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `user_type`
  ADD COLUMN `banned_until` datetime DEFAULT NULL AFTER `reset_requested_at`;

CREATE TABLE `incident_false_reports` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `reporter_user_id` int(10) UNSIGNED NOT NULL,
  `responder_user_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` enum(
    'prank_or_test',
    'wrong_location',
    'already_resolved',
    'cannot_find_anyone'
  ) NOT NULL,
  `notes` text DEFAULT NULL,
  `review_status` enum('pending','confirmed','dismissed') NOT NULL DEFAULT 'pending',
  `warning_number` smallint(5) UNSIGNED DEFAULT NULL,
  `reviewed_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `ban_applied_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_incident_false_reports_incident` (`incident_id`),
  KEY `idx_false_reports_status_created` (`review_status`,`created_at`),
  KEY `idx_false_reports_reporter_status` (`reporter_user_id`,`review_status`),
  CONSTRAINT `fk_false_reports_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_false_reports_reporter`
    FOREIGN KEY (`reporter_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_false_reports_responder`
    FOREIGN KEY (`responder_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_false_reports_reviewer`
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
