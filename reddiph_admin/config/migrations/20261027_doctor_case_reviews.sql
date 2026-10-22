CREATE TABLE IF NOT EXISTS `doctor_case_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) unsigned NOT NULL,
  `doctor_user_id` int(10) unsigned NOT NULL,
  `clinical_note` text DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doctor_case_reviews_incident_doctor` (`incident_id`,`doctor_user_id`),
  KEY `idx_doctor_case_reviews_doctor_confirmed` (`doctor_user_id`,`confirmed_at`),
  CONSTRAINT `fk_doctor_case_reviews_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_doctor_case_reviews_doctor`
    FOREIGN KEY (`doctor_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
