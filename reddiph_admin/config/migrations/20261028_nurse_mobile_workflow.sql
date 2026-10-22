CREATE TABLE IF NOT EXISTS `nurse_prep_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hospital_id` int(10) unsigned NOT NULL,
  `task_name` varchar(120) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nurse_prep_template_name` (`hospital_id`,`task_name`),
  CONSTRAINT `fk_nurse_prep_template_hospital`
    FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nurse_case_prep_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) unsigned NOT NULL,
  `task_name` varchar(120) NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_by` int(10) unsigned DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nurse_case_prep_task` (`incident_id`,`task_name`),
  KEY `idx_nurse_case_prep_done` (`incident_id`,`is_done`),
  CONSTRAINT `fk_nurse_case_prep_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nurse_case_prep_user`
    FOREIGN KEY (`done_by`) REFERENCES `user_type` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nurse_case_lifecycle` (
  `incident_id` int(10) unsigned NOT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `arrived_by` int(10) unsigned DEFAULT NULL,
  `handed_off_at` datetime DEFAULT NULL,
  `handed_off_by` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`incident_id`),
  CONSTRAINT `fk_nurse_case_lifecycle_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nurse_case_lifecycle_arrived_user`
    FOREIGN KEY (`arrived_by`) REFERENCES `user_type` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_nurse_case_lifecycle_handoff_user`
    FOREIGN KEY (`handed_off_by`) REFERENCES `user_type` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nurse_patient_handoffs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) unsigned NOT NULL,
  `nurse_id` int(10) unsigned NOT NULL,
  `bed_id` int(10) unsigned DEFAULT NULL,
  `from_responder` varchar(120) DEFAULT NULL,
  `pulse` varchar(12) DEFAULT NULL,
  `spo2` varchar(12) DEFAULT NULL,
  `blood_pressure` varchar(15) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nurse_handoffs_incident` (`incident_id`,`created_at`),
  KEY `idx_nurse_handoffs_nurse` (`nurse_id`,`created_at`),
  KEY `idx_nurse_handoffs_bed` (`bed_id`),
  CONSTRAINT `fk_nurse_handoff_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nurse_handoff_user`
    FOREIGN KEY (`nurse_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nurse_handoff_bed`
    FOREIGN KEY (`bed_id`) REFERENCES `hospital_beds` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nurse_mobile_preferences` (
  `nurse_id` int(10) unsigned NOT NULL,
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `language` enum('English','Filipino','Hiligaynon') NOT NULL DEFAULT 'English',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`nurse_id`),
  CONSTRAINT `fk_nurse_mobile_preferences_user`
    FOREIGN KEY (`nurse_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nurse_shift_swap_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nurse_id` int(10) unsigned NOT NULL,
  `shift_id` int(10) unsigned DEFAULT NULL,
  `requested_date` date NOT NULL,
  `message` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nurse_swap_requests` (`nurse_id`,`status`,`requested_date`),
  CONSTRAINT `fk_nurse_swap_user`
    FOREIGN KEY (`nurse_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nurse_swap_shift`
    FOREIGN KEY (`shift_id`) REFERENCES `staff_shifts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nurse_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nurse_id` int(10) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
    CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nurse_audit_actor_time` (`nurse_id`,`created_at`),
  KEY `idx_nurse_audit_target` (`target_type`,`target_id`,`created_at`),
  CONSTRAINT `fk_nurse_audit_user`
    FOREIGN KEY (`nurse_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `nurse_prep_templates` (`hospital_id`,`task_name`,`sort_order`)
SELECT `id`, 'ER bay reserved', 1 FROM `hospitals`;
INSERT IGNORE INTO `nurse_prep_templates` (`hospital_id`,`task_name`,`sort_order`)
SELECT `id`, 'Blood type on file', 2 FROM `hospitals`;
INSERT IGNORE INTO `nurse_prep_templates` (`hospital_id`,`task_name`,`sort_order`)
SELECT `id`, 'Doctor on standby', 3 FROM `hospitals`;
INSERT IGNORE INTO `nurse_prep_templates` (`hospital_id`,`task_name`,`sort_order`)
SELECT `id`, 'Oxygen ready', 4 FROM `hospitals`;
INSERT IGNORE INTO `nurse_prep_templates` (`hospital_id`,`task_name`,`sort_order`)
SELECT `id`, 'Trauma team notified', 5 FROM `hospitals`;
