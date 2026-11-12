-- Speeds up hospital staff lookups used by paginated Resource staff search.
-- Rollback: ALTER TABLE `staff` DROP INDEX `idx_staff_user_hospital_id`;

ALTER TABLE `staff`
  ADD INDEX IF NOT EXISTS `idx_staff_user_hospital_id` (`user_id`, `hospital_id`, `id`);
