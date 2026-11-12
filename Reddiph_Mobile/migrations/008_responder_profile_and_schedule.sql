-- Migration 008: persist responder vehicle settings and weekly duty periods.
-- The shared database backup already contains these tables; this migration
-- brings fresh databases up to the same schema without replacing existing data.

CREATE TABLE IF NOT EXISTS `responder_profiles` (
  `user_id` int(10) unsigned NOT NULL,
  `driver_responder_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `vehicle_type` varchar(40) DEFAULT NULL,
  `call_sign` varchar(30) DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `duty_schedule_configured` tinyint(1) NOT NULL DEFAULT 0,
  `availability_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_responder_profiles_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `responder_duty_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_responder_duty_period` (`user_id`, `day_of_week`, `start_time`, `end_time`),
  KEY `idx_responder_duty_user_day` (`user_id`, `day_of_week`),
  CONSTRAINT `fk_responder_duty_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing installations may already have the schedule table without the
-- uniqueness guard. MariaDB skips this when the index is already present.
ALTER TABLE `responder_duty_schedules`
  ADD UNIQUE KEY IF NOT EXISTS `uq_responder_duty_period`
    (`user_id`, `day_of_week`, `start_time`, `end_time`);