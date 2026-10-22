-- Migration 002: age and gender on user_type, collected at mobile responder signup.
-- Run once on the shared Hostinger database (phpMyAdmin > SQL).
-- Uses ADD COLUMN IF NOT EXISTS (MariaDB), so it is safe to run twice.
-- Existing users keep NULL until they have these details.
-- The gender values match incidents.patient_gender (without 'unknown').

ALTER TABLE `user_type`
  ADD COLUMN IF NOT EXISTS `age` tinyint(3) UNSIGNED DEFAULT NULL AFTER `phone_number`,
  ADD COLUMN IF NOT EXISTS `gender` enum('male','female','other') DEFAULT NULL AFTER `age`;
