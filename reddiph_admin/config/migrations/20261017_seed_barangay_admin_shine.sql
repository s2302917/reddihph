-- Run 20261017_user_type_barangay_admin_role.sql first.
-- Creates a Barangay Admin account scoped to barangay ID 1.
-- The initial password is stored as a PHP password_hash() hash and expires in 24 hours.
INSERT INTO `user_type` (
  `role`,
  `full_name`,
  `hospital_name`,
  `hospital_id`,
  `barangay_id`,
  `work_email`,
  `password_hash`,
  `must_change_password`,
  `temporary_password_expires_at`
)
SELECT
  'barangay_admin',
  'Shine',
  h.`name`,
  b.`hospital_id`,
  b.`id`,
  'shine@gmail.com',
  '$2y$10$o0erPjkrHk3b1WA/0JkHROr0BujteDvg7JmTcDSKKeBysWsb6litu',
  1,
  DATE_ADD(NOW(), INTERVAL 1 DAY)
FROM `barangays` AS b
JOIN `hospitals` AS h ON h.`id` = b.`hospital_id`
WHERE b.`id` = 1;
