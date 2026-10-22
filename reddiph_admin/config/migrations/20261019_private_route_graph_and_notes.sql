CREATE TABLE `road_route_nodes` (
  `node_id` bigint(20) unsigned NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  PRIMARY KEY (`node_id`),
  KEY `idx_road_route_nodes_lat_lng` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `road_route_edges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_node_id` bigint(20) unsigned NOT NULL,
  `to_node_id` bigint(20) unsigned NOT NULL,
  `distance_m` decimal(10,2) NOT NULL,
  `road_name` varchar(255) NOT NULL DEFAULT '',
  `road_class` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_road_route_edges_segment`
    (`from_node_id`,`to_node_id`,`road_class`,`road_name`),
  KEY `idx_road_route_edges_from` (`from_node_id`),
  KEY `idx_road_route_edges_to` (`to_node_id`),
  CONSTRAINT `fk_road_route_edges_from`
    FOREIGN KEY (`from_node_id`) REFERENCES `road_route_nodes` (`node_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_road_route_edges_to`
    FOREIGN KEY (`to_node_id`) REFERENCES `road_route_nodes` (`node_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `incident_note_updates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) unsigned NOT NULL,
  `author_user_id` int(10) unsigned NOT NULL,
  `note` varchar(2000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_incident_note_updates_incident_created`
    (`incident_id`,`created_at`),
  CONSTRAINT `fk_incident_note_updates_incident`
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_incident_note_updates_author`
    FOREIGN KEY (`author_user_id`) REFERENCES `user_type` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
