-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 18, 2026 at 11:27 AM
-- Server version: 11.8.9-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u672637579_Reddiph_backup`
--

-- --------------------------------------------------------

--
-- Table structure for table `ambulances`
--

CREATE TABLE `ambulances` (
  `id` int(10) UNSIGNED NOT NULL,
  `call_sign` varchar(30) NOT NULL,
  `hospital_id` int(10) UNSIGNED NOT NULL,
  `status` enum('en_route','available','offline','arrived') NOT NULL DEFAULT 'available',
  `assignment_note` varchar(160) DEFAULT NULL,
  `eta_minutes` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vehicle_type` varchar(40) NOT NULL DEFAULT 'Ambulance',
  `arrived_at` datetime DEFAULT NULL,
  `driver_name` varchar(160) DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_recorded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) DEFAULT NULL,
  `active_dispatch_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `hospital_id` int(10) UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `slug`, `active_dispatch_count`, `sort_order`, `hospital_id`, `latitude`, `longitude`) VALUES
(1, 'Batuan', 'batuan', 0, 1, 1, NULL, NULL),
(2, 'Barangay III (Poblacion)', 'barangay-iii-poblacion', 0, 2, 1, NULL, NULL),
(3, 'Barangay 01', 'barangay-01', 0, 3, 1, NULL, NULL),
(4, 'Barangay 02', 'barangay-02', 0, 4, 1, NULL, NULL),
(5, 'Barangay 03', 'barangay-03', 0, 5, 1, NULL, NULL),
(6, 'Barangay 04', 'barangay-04', 0, 6, 1, NULL, NULL),
(7, 'Barangay 05', 'barangay-05', 0, 7, 1, NULL, NULL),
(8, 'Barangay 06', 'barangay-06', 0, 8, 1, NULL, NULL),
(9, 'Barangay 07', 'barangay-07', 0, 9, 1, NULL, NULL),
(10, 'Barangay 08', 'barangay-08', 0, 10, 1, NULL, NULL),
(11, 'Barangay 09', 'barangay-09', 0, 11, 1, NULL, NULL),
(12, 'Barangay 10', 'barangay-10', 0, 12, 1, NULL, NULL),
(13, 'Barangay 12', 'barangay-12', 0, 13, 1, NULL, NULL),
(14, 'Barangay 01', 'barangay-01', 0, 1, 2, NULL, NULL),
(15, 'Barangay 02', 'barangay-02', 0, 2, 2, NULL, NULL),
(16, 'Barangay 03', 'barangay-03', 0, 3, 2, NULL, NULL),
(17, 'Barangay 04', 'barangay-04', 0, 4, 2, NULL, NULL),
(18, 'Barangay 05', 'barangay-05', 0, 5, 2, NULL, NULL),
(19, 'Barangay 06', 'barangay-06', 0, 6, 2, NULL, NULL),
(20, 'Barangay 07', 'barangay-07', 0, 7, 2, NULL, NULL),
(21, 'Barangay 08', 'barangay-08', 0, 8, 2, NULL, NULL),
(22, 'Barangay 09', 'barangay-09', 0, 9, 2, NULL, NULL),
(23, 'Barangay 10', 'barangay-10', 0, 10, 2, NULL, NULL),
(24, 'Barangay 01', 'barangay-01', 0, 1, 3, NULL, NULL),
(25, 'Barangay 02', 'barangay-02', 0, 2, 3, NULL, NULL),
(26, 'Barangay 03', 'barangay-03', 0, 3, 3, NULL, NULL),
(27, 'Barangay 04', 'barangay-04', 0, 4, 3, NULL, NULL),
(28, 'Barangay 05', 'barangay-05', 0, 5, 3, NULL, NULL),
(29, 'Barangay 06', 'barangay-06', 0, 6, 3, NULL, NULL),
(30, 'Barangay 07', 'barangay-07', 0, 7, 3, NULL, NULL),
(31, 'Barangay 08', 'barangay-08', 0, 8, 3, NULL, NULL),
(32, 'Barangay 09', 'barangay-09', 0, 9, 3, NULL, NULL),
(33, 'Barangay 10', 'barangay-10', 0, 10, 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `facility_status`
--

CREATE TABLE `facility_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `hospital_id` int(10) UNSIGNED DEFAULT NULL,
  `metric_key` varchar(60) NOT NULL,
  `current_value` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_value` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status_label` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facility_status`
--

INSERT INTO `facility_status` (`id`, `hospital_id`, `metric_key`, `current_value`, `max_value`, `status_label`, `created_at`, `updated_at`) VALUES
(1, 1, 'bed_capacity', 0, 0, NULL, '2026-10-17 04:26:03', '2026-10-17 04:26:03'),
(2, 1, 'ambulance_bay', 0, 0, 'No vehicles configured', '2026-10-17 04:26:03', '2026-10-18 09:40:06'),
(3, 1, 'ptv', 0, 0, 'No vehicles configured', '2026-10-17 04:26:03', '2026-10-18 09:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `available_beds` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_beds` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `specialties` varchar(255) NOT NULL DEFAULT '',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`id`, `name`, `available_beds`, `total_beds`, `specialties`, `latitude`, `longitude`, `address`, `created_at`) VALUES
(1, 'Don Salvador Benedicto Memorial District Hospital', 0, 0, '', 10.42283976, 122.91382296, 'La Carlota City', '2026-10-17 03:40:47'),
(2, 'Hospital 2', 0, 0, '', 10.67832895, 122.96034002, 'Bacolod City', '2026-10-17 03:40:47'),
(3, 'Hospital 3', 0, 0, '', 10.68309773, 122.95803174, 'Bacolod City', '2026-10-17 03:40:47');

-- --------------------------------------------------------

--
-- Table structure for table `hospital_beds`
--

CREATE TABLE `hospital_beds` (
  `id` int(10) UNSIGNED NOT NULL,
  `hospital_id` int(10) UNSIGNED NOT NULL,
  `bed_type` enum('er','pediatric') NOT NULL,
  `bed_number` smallint(5) UNSIGNED NOT NULL,
  `status` enum('ready','preparing','occupied','cleaning') NOT NULL DEFAULT 'occupied',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `incident_id` int(10) UNSIGNED NOT NULL,
  `incident_type` varchar(120) NOT NULL DEFAULT 'SOS emergency alert',
  `reported_by` varchar(32) NOT NULL DEFAULT 'resident_sos',
  `reporter_user_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('critical','dispatched','urgent','stable','resolved') NOT NULL DEFAULT 'critical',
  `location` varchar(160) NOT NULL DEFAULT 'Location unavailable',
  `barangay_id` int(10) UNSIGNED DEFAULT NULL,
  `hospital_id` int(10) UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `patient_gender` enum('male','female','other','unknown') DEFAULT 'unknown',
  `patient_age` int(11) DEFAULT NULL,
  `eta_minutes` int(11) DEFAULT NULL,
  `responder_name` varchar(120) DEFAULT NULL,
  `hospital_name` varchar(160) NOT NULL DEFAULT 'La Carlota District Hospital',
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `transport_name` varchar(80) DEFAULT NULL,
  `transport_type` varchar(40) DEFAULT NULL,
  `patient_name` varchar(160) DEFAULT NULL,
  `driver_name` varchar(160) DEFAULT NULL,
  `nurse_heart_rate` smallint(5) UNSIGNED DEFAULT NULL,
  `nurse_blood_pressure` varchar(15) DEFAULT NULL,
  `nurse_spo2` tinyint(3) UNSIGNED DEFAULT NULL,
  `nurse_gcs` tinyint(3) UNSIGNED DEFAULT NULL,
  `nurse_notes` text DEFAULT NULL,
  `nurse_prep_completed` tinyint(1) NOT NULL DEFAULT 0,
  `nurse_prep_completed_by` int(10) UNSIGNED DEFAULT NULL,
  `nurse_prep_completed_at` datetime DEFAULT NULL,
  `nurse_bed_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`incident_id`, `incident_type`, `reported_by`, `reporter_user_id`, `status`, `location`, `barangay_id`, `hospital_id`, `latitude`, `longitude`, `patient_gender`, `patient_age`, `eta_minutes`, `responder_name`, `hospital_name`, `reported_at`, `resolved_at`, `notes`, `created_at`, `updated_at`, `transport_name`, `transport_type`, `patient_name`, `driver_name`, `nurse_heart_rate`, `nurse_blood_pressure`, `nurse_spo2`, `nurse_gcs`, `nurse_notes`, `nurse_prep_completed`, `nurse_prep_completed_by`, `nurse_prep_completed_at`, `nurse_bed_id`) VALUES
(5, 'Vehicular', 'on_scene', NULL, 'critical', 'GPS 37.421998, -122.084000', 1, 1, 37.42199830, -122.08400000, 'male', 34, 25455, 'Isiah James Nonato Semiller', 'Don Salvador Benedicto Memorial District Hospital', '2026-10-18 17:33:03', NULL, 'Filed by responder from the mobile app.', '2026-10-18 09:33:03', '2026-10-18 09:33:03', NULL, NULL, 'sean', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(6, 'Medical', 'on_scene', NULL, 'critical', 'GPS 37.421998, -122.084000', 1, 1, 37.42199830, -122.08400000, 'male', 34, 25455, 'Isiah James Nonato Semiller', 'Don Salvador Benedicto Memorial District Hospital', '2026-10-18 17:52:53', NULL, 'Filed by responder from the mobile app.', '2026-10-18 09:52:53', '2026-10-18 09:52:53', NULL, NULL, 'hay', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(7, 'Fall', 'on_scene', NULL, 'critical', 'GPS 37.421998, -122.084000', 1, 1, 37.42199830, -122.08400000, 'female', 34, 25455, 'Isiah James Nonato Semiller', 'Don Salvador Benedicto Memorial District Hospital', '2026-10-18 19:17:41', NULL, 'Filed by responder from the mobile app.', '2026-10-18 11:17:41', '2026-10-18 11:17:41', NULL, NULL, 'shine', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `incident_false_reports`
--

CREATE TABLE `incident_false_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `reporter_user_id` int(10) UNSIGNED NOT NULL,
  `responder_user_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` enum('prank_or_test','wrong_location','already_resolved','cannot_find_anyone') NOT NULL,
  `notes` text DEFAULT NULL,
  `review_status` enum('pending','confirmed','dismissed') NOT NULL DEFAULT 'pending',
  `warning_number` smallint(5) UNSIGNED DEFAULT NULL,
  `reviewed_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `ban_applied_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_patients`
--

CREATE TABLE `incident_patients` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `patient_name` varchar(160) NOT NULL,
  `patient_age` tinyint(3) UNSIGNED NOT NULL,
  `patient_gender` enum('male','female','other','unknown') NOT NULL DEFAULT 'unknown',
  `heart_rate` smallint(5) UNSIGNED DEFAULT NULL,
  `spo2` tinyint(3) UNSIGNED DEFAULT NULL,
  `nurse_blood_pressure` varchar(15) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo_filename` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_patients`
--

INSERT INTO `incident_patients` (`id`, `incident_id`, `patient_name`, `patient_age`, `patient_gender`, `heart_rate`, `spo2`, `nurse_blood_pressure`, `notes`, `photo_filename`, `created_at`) VALUES
(5, 6, 'hay', 34, 'male', NULL, NULL, NULL, NULL, NULL, '2026-10-18 09:52:53'),
(6, 7, 'shine', 34, 'female', NULL, NULL, NULL, NULL, NULL, '2026-10-18 11:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `incident_patient_conditions`
--

CREATE TABLE `incident_patient_conditions` (
  `patient_id` int(10) UNSIGNED NOT NULL,
  `condition_code` enum('conscious','unconscious','bleeding','breathing_difficulty') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_patient_conditions`
--

INSERT INTO `incident_patient_conditions` (`patient_id`, `condition_code`, `created_at`) VALUES
(5, 'conscious', '2026-10-18 09:52:53'),
(6, 'unconscious', '2026-10-18 11:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `incident_vehicle_requests`
--

CREATE TABLE `incident_vehicle_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `responder_user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','fulfilled','declined') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED NOT NULL,
  `receiving_hospital_id` int(10) UNSIGNED NOT NULL,
  `referral_reason` varchar(255) NOT NULL,
  `status` enum('pending','accepted','declined','completed') NOT NULL DEFAULT 'pending',
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(10) UNSIGNED NOT NULL,
  `hospital_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `department` varchar(120) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_alerts`
--

CREATE TABLE `staff_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `incident_id` int(10) UNSIGNED DEFAULT NULL,
  `transport_name` varchar(80) DEFAULT NULL,
  `recipient_user_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_role` enum('doctor','nurse') NOT NULL,
  `specialization` varchar(120) DEFAULT NULL,
  `message` text NOT NULL,
  `channel` enum('admin','mobile') NOT NULL DEFAULT 'admin',
  `status` enum('queued','sent','read') NOT NULL DEFAULT 'queued',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_shifts`
--

CREATE TABLE `staff_shifts` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `shift_date` date NOT NULL,
  `shift_type` enum('morning','evening','oncall','leave') DEFAULT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `shift_status` enum('on_duty','starts_later','off_duty') NOT NULL DEFAULT 'on_duty',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_type`
--

CREATE TABLE `user_type` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` enum('hospital_admin','barangay_admin','doctor','nurse','resident','responder','volunteer') NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `hospital_name` varchar(150) NOT NULL,
  `hospital_id` int(10) UNSIGNED DEFAULT NULL,
  `barangay_id` int(10) UNSIGNED DEFAULT NULL,
  `work_email` varchar(150) NOT NULL,
  `phone_number` varchar(32) DEFAULT NULL,
  `age` tinyint(3) UNSIGNED DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `temporary_password_expires_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `reset_requested_at` datetime DEFAULT NULL,
  `banned_until` datetime DEFAULT NULL,
  `duty_start` time DEFAULT NULL,
  `duty_end` time DEFAULT NULL,
  `duty_timezone` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_type`
--

INSERT INTO `user_type` (`id`, `role`, `full_name`, `hospital_name`, `hospital_id`, `barangay_id`, `work_email`, `phone_number`, `age`, `gender`, `license_number`, `password_hash`, `profile_image`, `last_login_at`, `created_at`, `must_change_password`, `temporary_password_expires_at`, `password_changed_at`, `reset_requested_at`, `banned_until`, `duty_start`, `duty_end`, `duty_timezone`) VALUES
(1, 'hospital_admin', 'shizuro kun', 'Don Salvador Benedicto Memorial District Hospital', 1, NULL, 'shizurokun@gmail.com', NULL, NULL, NULL, NULL, '$2y$12$kJ6QAdoqaYi.iSPlPRWPOeJxO13ENNVnPOpfYzhKgdA8Ca4vA78pq', NULL, '2026-10-18 17:48:55', '2026-10-17 04:25:51', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'barangay_admin', 'Shine', 'Don Salvador Benedicto Memorial District Hospital', 1, 1, 'shine@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$o0erPjkrHk3b1WA/0JkHROr0BujteDvg7JmTcDSKKeBysWsb6litu', NULL, '2026-10-17 16:17:51', '2026-10-17 07:56:05', 1, '2026-10-18 07:56:05', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'responder', 'Isiah James Nonato Semiller', 'Don Salvador Benedicto Memorial District Hospital', 1, 1, 'isiahnonato@gmail.com', '09930791525', 23, 'male', NULL, '$2y$12$TJ1wToMgjgrP7cPOePmXpOfM/KV0vhtcLI9hKujDLqbmE2hHVHpla', NULL, '2026-10-17 19:44:14', '2026-10-17 08:26:46', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ambulances`
--
ALTER TABLE `ambulances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ambulances_hospital_call_sign` (`hospital_id`,`call_sign`),
  ADD UNIQUE KEY `uq_ambulances_hospital_plate` (`hospital_id`,`plate_number`),
  ADD KEY `idx_ambulances_status` (`status`),
  ADD KEY `fk_ambulances_hospital` (`hospital_id`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_barangays_hospital_slug` (`hospital_id`,`slug`),
  ADD UNIQUE KEY `uq_barangays_hospital_name` (`hospital_id`,`name`),
  ADD KEY `idx_barangays_name` (`name`),
  ADD KEY `idx_barangays_hospital` (`hospital_id`),
  ADD KEY `idx_barangays_hospital_sort` (`hospital_id`,`sort_order`);

--
-- Indexes for table `facility_status`
--
ALTER TABLE `facility_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_facility_status_hospital_metric` (`hospital_id`,`metric_key`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hospitals_name` (`name`),
  ADD KEY `idx_hospitals_name` (`name`);

--
-- Indexes for table `hospital_beds`
--
ALTER TABLE `hospital_beds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hospital_beds_location` (`hospital_id`,`bed_type`,`bed_number`),
  ADD KEY `idx_hospital_beds_status` (`hospital_id`,`status`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`incident_id`),
  ADD KEY `idx_incidents_status` (`status`),
  ADD KEY `idx_incidents_location` (`location`),
  ADD KEY `idx_incidents_reported_at` (`reported_at`),
  ADD KEY `idx_incidents_hospital` (`hospital_name`),
  ADD KEY `idx_incidents_nurse_prep` (`hospital_id`,`nurse_prep_completed`,`status`),
  ADD KEY `idx_incidents_nurse_bed` (`nurse_bed_id`),
  ADD KEY `idx_incidents_reporter_user` (`reporter_user_id`);

--
-- Indexes for table `incident_false_reports`
--
ALTER TABLE `incident_false_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_incident_false_reports_incident` (`incident_id`),
  ADD KEY `idx_false_reports_status_created` (`review_status`,`created_at`),
  ADD KEY `idx_false_reports_reporter_status` (`reporter_user_id`,`review_status`),
  ADD KEY `fk_false_reports_responder` (`responder_user_id`),
  ADD KEY `fk_false_reports_reviewer` (`reviewed_by_user_id`);

--
-- Indexes for table `incident_patients`
--
ALTER TABLE `incident_patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_patients_incident` (`incident_id`);

--
-- Indexes for table `incident_patient_conditions`
--
ALTER TABLE `incident_patient_conditions`
  ADD PRIMARY KEY (`patient_id`,`condition_code`);

--
-- Indexes for table `incident_vehicle_requests`
--
ALTER TABLE `incident_vehicle_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_vehicle_requests_incident_status` (`incident_id`,`status`),
  ADD KEY `fk_incident_vehicle_requests_responder` (`responder_user_id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_referrals_status` (`status`),
  ADD KEY `fk_referrals_incident` (`incident_id`),
  ADD KEY `fk_referrals_hospital` (`receiving_hospital_id`),
  ADD KEY `fk_referrals_user` (`created_by_user_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_alerts`
--
ALTER TABLE `staff_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_alerts_recipient` (`recipient_user_id`,`status`),
  ADD KEY `idx_staff_alerts_incident` (`incident_id`);

--
-- Indexes for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_shift_date` (`staff_id`,`shift_date`),
  ADD KEY `idx_staff_shifts_date_status` (`shift_date`,`shift_status`);

--
-- Indexes for table `user_type`
--
ALTER TABLE `user_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_type_work_email` (`work_email`),
  ADD UNIQUE KEY `uq_user_type_license_number` (`license_number`),
  ADD KEY `idx_user_type_role` (`role`),
  ADD KEY `idx_user_type_hospital` (`hospital_id`),
  ADD KEY `idx_user_type_hospital_name_role` (`hospital_id`,`full_name`,`role`),
  ADD KEY `idx_user_type_barangay` (`barangay_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ambulances`
--
ALTER TABLE `ambulances`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `facility_status`
--
ALTER TABLE `facility_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hospital_beds`
--
ALTER TABLE `hospital_beds`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `incident_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `incident_false_reports`
--
ALTER TABLE `incident_false_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_patients`
--
ALTER TABLE `incident_patients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `incident_vehicle_requests`
--
ALTER TABLE `incident_vehicle_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_alerts`
--
ALTER TABLE `staff_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_type`
--
ALTER TABLE `user_type`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ambulances`
--
ALTER TABLE `ambulances`
  ADD CONSTRAINT `fk_ambulances_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `barangays`
--
ALTER TABLE `barangays`
  ADD CONSTRAINT `fk_barangays_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `hospital_beds`
--
ALTER TABLE `hospital_beds`
  ADD CONSTRAINT `fk_hospital_beds_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `fk_incidents_nurse_bed` FOREIGN KEY (`nurse_bed_id`) REFERENCES `hospital_beds` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incidents_reporter_user` FOREIGN KEY (`reporter_user_id`) REFERENCES `user_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `incident_false_reports`
--
ALTER TABLE `incident_false_reports`
  ADD CONSTRAINT `fk_false_reports_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_false_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `user_type` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_false_reports_responder` FOREIGN KEY (`responder_user_id`) REFERENCES `user_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_false_reports_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `user_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `incident_patients`
--
ALTER TABLE `incident_patients`
  ADD CONSTRAINT `fk_incident_patients_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `incident_patient_conditions`
--
ALTER TABLE `incident_patient_conditions`
  ADD CONSTRAINT `fk_incident_patient_conditions_patient` FOREIGN KEY (`patient_id`) REFERENCES `incident_patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `incident_vehicle_requests`
--
ALTER TABLE `incident_vehicle_requests`
  ADD CONSTRAINT `fk_incident_vehicle_requests_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_incident_vehicle_requests_responder` FOREIGN KEY (`responder_user_id`) REFERENCES `user_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_referrals_hospital` FOREIGN KEY (`receiving_hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `fk_referrals_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`),
  ADD CONSTRAINT `fk_referrals_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `user_type` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  ADD CONSTRAINT `fk_staff_shifts_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_type`
--
ALTER TABLE `user_type`
  ADD CONSTRAINT `fk_user_type_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_type_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
