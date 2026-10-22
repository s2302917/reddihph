-- Migration 005: distinguish incidents reported on scene from resident SOS reports.
-- Run once on the shared Hostinger database (phpMyAdmin > SQL).
-- Existing incidents are treated as resident SOS reports.

ALTER TABLE `incidents`
  ADD COLUMN `reported_by` varchar(32) NOT NULL DEFAULT 'resident_sos' AFTER `incident_type`;
