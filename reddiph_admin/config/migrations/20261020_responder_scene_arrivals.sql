ALTER TABLE `incidents`
    ADD COLUMN `responder_arrived_at` datetime DEFAULT NULL
        AFTER `resolved_at`;

CREATE TABLE `incident_scene_arrival_alerts` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `incident_id` int(10) UNSIGNED NOT NULL,
    `responder_user_id` int(10) UNSIGNED NOT NULL,
    `barangay_admin_user_id` int(10) UNSIGNED NOT NULL,
    `message` varchar(500) NOT NULL,
    `status` enum('queued', 'read') NOT NULL DEFAULT 'queued',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `read_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scene_arrival_admin` (`incident_id`, `barangay_admin_user_id`),
    KEY `idx_scene_arrival_pending` (`barangay_admin_user_id`, `status`, `created_at`),
    CONSTRAINT `fk_scene_arrival_incident`
        FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_scene_arrival_responder`
        FOREIGN KEY (`responder_user_id`) REFERENCES `user_type` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_scene_arrival_admin`
        FOREIGN KEY (`barangay_admin_user_id`) REFERENCES `user_type` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
