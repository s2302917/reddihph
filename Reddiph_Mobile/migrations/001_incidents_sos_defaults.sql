-- Migration 001: defaults for SOS incidents created by the Reddiph mobile app.
-- Run once on the shared Hostinger database (phpMyAdmin > SQL).
-- Existing rows are not changed; only the column defaults are.

ALTER TABLE `incidents`
  MODIFY `incident_type` varchar(120) NOT NULL DEFAULT 'SOS emergency alert',
  MODIFY `location` varchar(160) NOT NULL DEFAULT 'Location unavailable';
