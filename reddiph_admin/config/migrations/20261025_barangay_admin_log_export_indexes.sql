-- Run after the incident hospital timeline and Barangay Admin portal migrations.
-- These indexes support date-bounded, barangay-scoped log exports.
ALTER TABLE `incidents`
  ADD KEY `idx_incidents_barangay_reported` (`barangay_id`,`reported_at`),
  ADD KEY `idx_incidents_barangay_hospital_sent` (`barangay_id`,`hospital_sent_at`),
  ADD KEY `idx_incidents_barangay_hospital_arrived` (`barangay_id`,`hospital_arrived_at`);

ALTER TABLE `user_type`
  ADD KEY `idx_user_type_barangay_role_created` (`barangay_id`,`role`,`created_at`);

ALTER TABLE `incident_false_reports`
  ADD KEY `idx_false_reports_created_incident` (`created_at`,`incident_id`);
