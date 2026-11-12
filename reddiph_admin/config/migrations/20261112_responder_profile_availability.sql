CREATE TABLE IF NOT EXISTS `responder_profiles` (
  `user_id` int(10) UNSIGNED NOT NULL,
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
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_responder_duty_user_day` (`user_id`, `day_of_week`),
  CONSTRAINT `fk_responder_duty_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_responder_duty_day` CHECK (`day_of_week` BETWEEN 1 AND 7),
  CONSTRAINT `chk_responder_duty_times` CHECK (`start_time` <> `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback: DROP TABLE `responder_duty_schedules`; DROP TABLE `responder_profiles`;