ALTER TABLE `staff_shifts`
  ADD COLUMN `shift_type` enum('morning','evening','oncall','leave') DEFAULT NULL AFTER `shift_date`;
