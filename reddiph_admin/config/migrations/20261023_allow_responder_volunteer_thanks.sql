-- Complete responder hospital-arrival and volunteer-thanks schema.
-- Run against the shared Reddiph MariaDB database (the supplied backup is 11.8.9).
-- Includes the previously separate volunteer and hospital-timeline prerequisites.
-- Requires the base incidents and user_type tables and may be safely re-run.

CREATE TABLE IF NOT EXISTS `volunteer_profiles` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `vehicle_type` varchar(80) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_updated_at` datetime DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `idx_volunteer_profiles_available_location` (`is_available`,`location_updated_at`),
  CONSTRAINT `fk_volunteer_profiles_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_type` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `volunteer_profiles` (`user_id`)
SELECT `id`
FROM `user_type`
WHERE `role` = 'volunteer';

CREATE TABLE IF NOT EXISTS `volunteer_assistances` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `volunteer_user_id` int(10) UNSIGNED NOT NULL,
  `requested_by_user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('requested','accepted','declined','completed','cancelled')
    NOT NULL DEFAULT 'requested',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_volunteer_assistances_incident` (`incident_id`),
  KEY `idx_volunteer_assistances_volunteer_status` (`volunteer_user_id`,`status`),
  CONSTRAINT `fk_volunteer_assistances_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_volunteer_assistances_volunteer`
    FOREIGN KEY (`volunteer_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_volunteer_assistances_requester`
    FOREIGN KEY (`requested_by_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_assistance_reviews` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assistance_id` int(10) UNSIGNED NOT NULL,
  `reviewer_user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `review` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_volunteer_assistance_reviews_reviewer`
    (`assistance_id`,`reviewer_user_id`),
  KEY `idx_volunteer_assistance_reviews_reviewer` (`reviewer_user_id`),
  CONSTRAINT `chk_volunteer_assistance_reviews_rating`
    CHECK (`rating` BETWEEN 1 AND 5),
  CONSTRAINT `fk_volunteer_assistance_reviews_assistance`
    FOREIGN KEY (`assistance_id`) REFERENCES `volunteer_assistances` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_volunteer_assistance_reviews_reviewer`
    FOREIGN KEY (`reviewer_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `incidents`
  ADD COLUMN IF NOT EXISTS `responder_arrived_at` datetime DEFAULT NULL
    AFTER `resolved_at`;

ALTER TABLE `incidents`
  ADD COLUMN IF NOT EXISTS `hospital_sent_at` datetime DEFAULT NULL
    AFTER `responder_arrived_at`;

ALTER TABLE `incidents`
  ADD COLUMN IF NOT EXISTS `patient_picked_up_at` datetime DEFAULT NULL
    AFTER `hospital_sent_at`;

ALTER TABLE `incidents`
  ADD COLUMN IF NOT EXISTS `hospital_arrived_at` datetime DEFAULT NULL
    AFTER `patient_picked_up_at`;

ALTER TABLE `volunteer_assistance_reviews`
  DROP INDEX IF EXISTS `uq_volunteer_assistance_reviews_assistance`,
  ADD UNIQUE INDEX IF NOT EXISTS `uq_volunteer_assistance_reviews_reviewer`
    (`assistance_id`,`reviewer_user_id`);
