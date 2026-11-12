-- Idempotent MariaDB migration for optional bed room and staff assignments.
-- Safe to run against the updated Reddiph_backup.sql: existing columns,
-- indexes, and foreign keys are detected and left unchanged.
--
-- Run with the intended database selected. Existing bed rows are preserved;
-- newly added columns are nullable. No assignments are removed or overwritten.
--
-- Rollback is intentionally separate and destructive: dropping these columns
-- permanently removes any room/staff assignments saved in them.
-- ALTER TABLE `hospital_beds`
--   DROP FOREIGN KEY `fk_hospital_beds_assigned_doctor`,
--   DROP FOREIGN KEY `fk_hospital_beds_assigned_nurse`,
--   DROP KEY `idx_hospital_beds_room`,
--   DROP KEY `idx_hospital_beds_assigned_doctor`,
--   DROP KEY `idx_hospital_beds_assigned_nurse`,
--   DROP COLUMN `assigned_nurse_user_id`,
--   DROP COLUMN `assigned_doctor_user_id`,
--   DROP COLUMN `room_number`;

DROP PROCEDURE IF EXISTS `migrate_hospital_bed_rooms_and_staff`;

DELIMITER $$

CREATE PROCEDURE `migrate_hospital_bed_rooms_and_staff`()
BEGIN
  DECLARE item_exists INT DEFAULT 0;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND COLUMN_NAME = 'room_number';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD COLUMN `room_number` varchar(30) DEFAULT NULL AFTER `bed_number`;
  END IF;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND COLUMN_NAME = 'assigned_doctor_user_id';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD COLUMN `assigned_doctor_user_id` int(10) UNSIGNED DEFAULT NULL AFTER `room_number`;
  END IF;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND COLUMN_NAME = 'assigned_nurse_user_id';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD COLUMN `assigned_nurse_user_id` int(10) UNSIGNED DEFAULT NULL AFTER `assigned_doctor_user_id`;
  END IF;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND INDEX_NAME = 'idx_hospital_beds_room';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD KEY `idx_hospital_beds_room` (`hospital_id`, `bed_type`, `room_number`, `bed_number`);
  END IF;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND INDEX_NAME = 'idx_hospital_beds_assigned_doctor';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD KEY `idx_hospital_beds_assigned_doctor` (`assigned_doctor_user_id`);
  END IF;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND INDEX_NAME = 'idx_hospital_beds_assigned_nurse';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD KEY `idx_hospital_beds_assigned_nurse` (`assigned_nurse_user_id`);
  END IF;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND CONSTRAINT_NAME = 'fk_hospital_beds_assigned_doctor'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD CONSTRAINT `fk_hospital_beds_assigned_doctor`
      FOREIGN KEY (`assigned_doctor_user_id`) REFERENCES `user_type` (`id`)
      ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;

  SELECT COUNT(*) INTO item_exists
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'hospital_beds'
    AND CONSTRAINT_NAME = 'fk_hospital_beds_assigned_nurse'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY';
  IF item_exists = 0 THEN
    ALTER TABLE `hospital_beds`
      ADD CONSTRAINT `fk_hospital_beds_assigned_nurse`
      FOREIGN KEY (`assigned_nurse_user_id`) REFERENCES `user_type` (`id`)
      ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END$$

DELIMITER ;

CALL `migrate_hospital_bed_rooms_and_staff`();
DROP PROCEDURE `migrate_hospital_bed_rooms_and_staff`;
