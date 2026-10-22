ALTER TABLE `incidents`
    ADD COLUMN `assigned_responder_user_id` int(10) UNSIGNED DEFAULT NULL
        AFTER `responder_name`,
    ADD COLUMN `assigned_ambulance_id` int(10) UNSIGNED DEFAULT NULL
        AFTER `assigned_responder_user_id`,
    ADD KEY `idx_incidents_assigned_responder_status`
        (`assigned_responder_user_id`, `status`),
    ADD KEY `idx_incidents_assigned_ambulance_status`
        (`assigned_ambulance_id`, `status`),
    ADD CONSTRAINT `fk_incidents_assigned_responder`
        FOREIGN KEY (`assigned_responder_user_id`) REFERENCES `user_type` (`id`)
        ON DELETE SET NULL,
    ADD CONSTRAINT `fk_incidents_assigned_ambulance`
        FOREIGN KEY (`assigned_ambulance_id`) REFERENCES `ambulances` (`id`)
        ON DELETE SET NULL;

CREATE TABLE `responder_presence` (
    `user_id` int(10) UNSIGNED NOT NULL,
    `latitude` decimal(10,8) DEFAULT NULL,
    `longitude` decimal(11,8) DEFAULT NULL,
    `last_seen_at` datetime NOT NULL,
    PRIMARY KEY (`user_id`),
    KEY `idx_responder_presence_last_seen` (`last_seen_at`),
    CONSTRAINT `fk_responder_presence_user`
        FOREIGN KEY (`user_id`) REFERENCES `user_type` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `responder_alerts` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `incident_id` int(10) UNSIGNED NOT NULL,
    `responder_user_id` int(10) UNSIGNED NOT NULL,
    `status` enum('pending', 'acknowledged') NOT NULL DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `acknowledged_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_responder_alert_incident_user` (`incident_id`, `responder_user_id`),
    KEY `idx_responder_alert_pending` (`responder_user_id`, `status`, `created_at`),
    CONSTRAINT `fk_responder_alert_incident`
        FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_responder_alert_user`
        FOREIGN KEY (`responder_user_id`) REFERENCES `user_type` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
