-- Adds multiple doctor/nurse assignments per hospital bed.
-- Existing single-staff assignments are copied without changing the source columns.
-- Rollback: DROP TABLE `hospital_bed_staff_assignments`;

CREATE TABLE IF NOT EXISTS `hospital_bed_staff_assignments` (
  `bed_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` enum('doctor','nurse') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bed_id`, `user_id`, `role`),
  KEY `idx_bed_staff_user_role` (`user_id`, `role`),
  CONSTRAINT `fk_bed_staff_assignment_bed`
    FOREIGN KEY (`bed_id`) REFERENCES `hospital_beds` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bed_staff_assignment_user`
    FOREIGN KEY (`user_id`) REFERENCES `user_type` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `hospital_bed_staff_assignments` (`bed_id`, `user_id`, `role`)
SELECT `id`, `assigned_doctor_user_id`, 'doctor'
FROM `hospital_beds`
WHERE `assigned_doctor_user_id` IS NOT NULL;

INSERT IGNORE INTO `hospital_bed_staff_assignments` (`bed_id`, `user_id`, `role`)
SELECT `id`, `assigned_nurse_user_id`, 'nurse'
FROM `hospital_beds`
WHERE `assigned_nurse_user_id` IS NOT NULL;
