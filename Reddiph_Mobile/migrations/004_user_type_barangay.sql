-- Migration 004: barangay on user_type, saved from the barangay picked at mobile signup (all roles).
-- Run once on the shared Hostinger database (phpMyAdmin > SQL), after migrations 001-003.
-- Uses IF NOT EXISTS (MariaDB), so it is safe to run twice.
-- Existing users keep NULL until they have a barangay. Deleting a barangay also clears it here.

ALTER TABLE `user_type`
  ADD COLUMN IF NOT EXISTS `barangay_id` int(10) UNSIGNED DEFAULT NULL AFTER `hospital_id`,
  ADD KEY IF NOT EXISTS `idx_user_type_barangay` (`barangay_id`),
  ADD CONSTRAINT `fk_user_type_barangay`
    FOREIGN KEY IF NOT EXISTS (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
