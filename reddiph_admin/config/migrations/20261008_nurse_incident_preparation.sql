ALTER TABLE `incidents`
  ADD COLUMN `nurse_heart_rate` smallint(5) UNSIGNED DEFAULT NULL AFTER `driver_name`,
  ADD COLUMN `nurse_blood_pressure` varchar(15) DEFAULT NULL AFTER `nurse_heart_rate`,
  ADD COLUMN `nurse_spo2` tinyint(3) UNSIGNED DEFAULT NULL AFTER `nurse_blood_pressure`,
  ADD COLUMN `nurse_gcs` tinyint(3) UNSIGNED DEFAULT NULL AFTER `nurse_spo2`,
  ADD COLUMN `nurse_notes` text DEFAULT NULL AFTER `nurse_gcs`,
  ADD COLUMN `nurse_prep_completed` tinyint(1) NOT NULL DEFAULT 0 AFTER `nurse_notes`,
  ADD COLUMN `nurse_prep_completed_by` int(10) UNSIGNED DEFAULT NULL AFTER `nurse_prep_completed`,
  ADD COLUMN `nurse_prep_completed_at` datetime DEFAULT NULL AFTER `nurse_prep_completed_by`,
  ADD KEY `idx_incidents_nurse_prep` (`hospital_id`, `nurse_prep_completed`, `status`);
