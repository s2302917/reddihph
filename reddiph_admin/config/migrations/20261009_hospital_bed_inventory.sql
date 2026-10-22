CREATE TABLE `hospital_beds` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `hospital_id` int(10) UNSIGNED NOT NULL,
  `bed_type` enum('er','pediatric') NOT NULL,
  `bed_number` smallint(5) UNSIGNED NOT NULL,
  `status` enum('ready','preparing','occupied','cleaning') NOT NULL DEFAULT 'occupied',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hospital_beds_location` (`hospital_id`,`bed_type`,`bed_number`),
  KEY `idx_hospital_beds_status` (`hospital_id`,`status`),
  CONSTRAINT `fk_hospital_beds_hospital`
    FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
