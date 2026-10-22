-- Patient scene conditions are multi-select values, so store each selected condition
-- as a normalized row linked to its incident patient.
-- Safe to rerun: an existing table is left unchanged.
CREATE TABLE IF NOT EXISTS `incident_patient_conditions` (
  `patient_id` int(10) unsigned NOT NULL,
  `condition_code` enum('conscious','unconscious','bleeding','breathing_difficulty') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`patient_id`, `condition_code`),
  CONSTRAINT `fk_incident_patient_conditions_patient`
    FOREIGN KEY (`patient_id`) REFERENCES `incident_patients` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
