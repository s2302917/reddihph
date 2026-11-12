ALTER TABLE `responder_alerts`
    ADD COLUMN `assignment_role` enum('responder', 'driver') NOT NULL DEFAULT 'responder' AFTER `responder_user_id`,
    ADD UNIQUE KEY `uq_responder_alert_incident_role` (`incident_id`, `assignment_role`),
    ADD KEY `idx_responder_alert_user_role_status` (`responder_user_id`, `assignment_role`, `status`);
