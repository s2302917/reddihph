-- Migration 006: patient records, vital signs, and vehicle requests for responder reports.
-- Safe to rerun: existing patient and vehicle-request tables are left unchanged.
-- Apply any missing columns or indexes separately if an existing table is incomplete.
-- Photo files are stored in Reddiph_mobile_api/private_uploads/incident_patients/.

ALTER TABLE `incidents`
  MODIFY `notes` text DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `incident_patients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) unsigned NOT NULL,
  `patient_name` varchar(160) NOT NULL,
  `patient_age` tinyint(3) unsigned NOT NULL,
  `patient_gender` enum('male','female','other','unknown') NOT NULL DEFAULT 'unknown',
  `heart_rate` smallint(5) unsigned DEFAULT NULL,
  `spo2` tinyint(3) unsigned DEFAULT NULL,
  `nurse_blood_pressure` varchar(15) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo_filename` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_incident_patients_incident` (`incident_id`),
  CONSTRAINT `fk_incident_patients_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `incident_vehicle_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) unsigned NOT NULL,
  `responder_user_id` int(10) unsigned NOT NULL,
  `status` enum('pending','fulfilled','declined') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_incident_vehicle_requests_incident_status` (`incident_id`,`status`),
  CONSTRAINT `fk_incident_vehicle_requests_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_incident_vehicle_requests_responder`
    FOREIGN KEY (`responder_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
