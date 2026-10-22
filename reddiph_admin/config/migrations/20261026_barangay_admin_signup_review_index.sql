-- Run after 20261024_barangay_admin_portal.sql.
-- Speeds up status-filtered volunteer and responder approval lists.
ALTER TABLE `user_type`
  ADD KEY `idx_user_type_barangay_approval_created`
    (`barangay_id`,`approval_status`,`created_at`);
