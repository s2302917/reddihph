-- Standardize audit events across all Reddiph platforms.
-- Back up the shared database and verify the deployed schema before applying.

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT(10) UNSIGNED NULL,
    platform VARCHAR(32) NULL,
    platform_scope VARCHAR(32) NOT NULL DEFAULT 'superadmin',
    action_code VARCHAR(120) NULL,
    action VARCHAR(120) NOT NULL,
    source_event_key VARCHAR(190) NULL,
    entity_type VARCHAR(64) NULL,
    entity_id VARCHAR(100) NULL,
    details LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_audit_logs_source_event_key (source_event_key),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_actor_created (actor_user_id, created_at),
    KEY idx_audit_logs_platform_created (platform, created_at),
    KEY idx_audit_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS platform VARCHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS action_code VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS source_event_key VARCHAR(190) NULL,
    ADD COLUMN IF NOT EXISTS platform_scope VARCHAR(32) NOT NULL DEFAULT 'superadmin';

ALTER TABLE audit_logs
    ADD UNIQUE INDEX IF NOT EXISTS uq_audit_logs_source_event_key (source_event_key),
    ADD INDEX IF NOT EXISTS idx_audit_logs_platform_created (platform, created_at);

UPDATE audit_logs
SET platform = CASE
    WHEN platform IN ('website', 'mobile', 'superadmin', 'system') THEN platform
    WHEN platform_scope IN ('website', 'mobile', 'superadmin', 'system') THEN platform_scope
    ELSE 'system'
END;

UPDATE audit_logs
SET action_code = action
WHERE action_code IS NULL OR action_code = '';

UPDATE audit_logs
SET action = CASE action_code
    WHEN 'auth.login_success' THEN 'User Login Success'
    WHEN 'auth.login_failed' THEN 'User Login Failure'
    WHEN 'auth.logout' THEN 'User Logout Success'
    WHEN 'user.created' THEN 'Create User Account'
    WHEN 'user.updated' THEN 'Update User Details'
    WHEN 'user.account_archived' THEN 'Archive User Account'
    WHEN 'user.account_restored' THEN 'Restore User Account'
    WHEN 'user.login_disabled' THEN 'Disable User Login'
    WHEN 'user.login_enabled' THEN 'Enable User Login'
    WHEN 'user.password_reset' THEN 'Reset User Password'
    WHEN 'facility.hospital_created' THEN 'Create Hospital Record'
    WHEN 'facility.hospital_updated' THEN 'Update Hospital Details'
    WHEN 'facility.barangay_created' THEN 'Create Barangay Record'
    WHEN 'facility.barangay_updated' THEN 'Update Barangay Details'
    WHEN 'facility.archive_hospital' THEN 'Archive Hospital Record'
    WHEN 'facility.archive_barangay' THEN 'Archive Barangay Record'
    WHEN 'facility.restore_hospital' THEN 'Restore Hospital Record'
    WHEN 'facility.restore_barangay' THEN 'Restore Barangay Record'
    WHEN 'facility.barangay_shown' THEN 'Show Barangay Listing'
    WHEN 'facility.barangay_hidden' THEN 'Hide Barangay Listing'
    WHEN 'system.migration_completed' THEN 'Record Database Migration'
    WHEN 'system.operation_failed' THEN 'Record System Failure'
    WHEN 'incident.created' THEN 'Create Incident Report'
    WHEN 'incident.updated' THEN 'Update Incident Report'
    WHEN 'incident.status_updated' THEN 'Update Incident Status'
    WHEN 'incident.note_added' THEN 'Add Incident Note'
    WHEN 'patient.created' THEN 'Create Patient Record'
    WHEN 'patient.updated' THEN 'Update Patient Record'
    WHEN 'patient.deleted' THEN 'Delete Patient Record'
    WHEN 'false_report.submitted' THEN 'Submit False Report'
    WHEN 'false_report.reviewed' THEN 'Review False Report'
    WHEN 'signup.reviewed' THEN 'Review Account Signup'
    WHEN 'doctor.review_saved' THEN 'Save Clinical Review'
    WHEN 'schedule.created' THEN 'Create Staff Schedule'
    WHEN 'schedule.updated' THEN 'Update Staff Schedule'
    WHEN 'schedule.deleted' THEN 'Delete Staff Schedule'
    WHEN 'nurse.task_updated' THEN 'Update Preparation Task'
    WHEN 'nurse.note_saved' THEN 'Save Clinical Note'
    WHEN 'nurse.bed_assigned' THEN 'Assign Hospital Bed'
    WHEN 'nurse.arrival_confirmed' THEN 'Confirm Nurse Arrival'
    WHEN 'nurse.bed_updated' THEN 'Update Bed Status'
    WHEN 'nurse.handoff_recorded' THEN 'Record Patient Handoff'
    WHEN 'nurse.shift_swap_requested' THEN 'Request Shift Swap'
    WHEN 'nurse.settings_updated' THEN 'Update Nurse Settings'
    WHEN 'bed.created' THEN 'Create Hospital Bed'
    WHEN 'bed.updated' THEN 'Update Hospital Bed'
    WHEN 'bed.deleted' THEN 'Delete Hospital Bed'
    WHEN 'resource.updated' THEN 'Update Facility Resources'
    WHEN 'vehicle.created' THEN 'Create Ambulance Record'
    WHEN 'vehicle.updated' THEN 'Update Ambulance Record'
    WHEN 'vehicle.deleted' THEN 'Delete Ambulance Record'
    WHEN 'referral.created' THEN 'Create Hospital Referral'
    WHEN 'volunteer.requested' THEN 'Request Volunteer Assistance'
    WHEN 'volunteer.response_updated' THEN 'Update Volunteer Request'
    WHEN 'volunteer.review_saved' THEN 'Save Volunteer Review'
    WHEN 'volunteer.availability_updated' THEN 'Update Volunteer Availability'
    WHEN 'responder.arrival_recorded' THEN 'Record Responder Arrival'
    WHEN 'responder.alert_acknowledged' THEN 'Acknowledge Responder Alert'
    WHEN 'prep_task_updated' THEN 'Update Preparation Task'
    WHEN 'clinical_note_saved' THEN 'Save Clinical Note'
    WHEN 'bed_assigned' THEN 'Assign Hospital Bed'
    WHEN 'arrival_confirmed' THEN 'Confirm Nurse Arrival'
    WHEN 'handoff_recorded' THEN 'Record Patient Handoff'
    WHEN 'shift_swap_requested' THEN 'Request Shift Swap'
    WHEN 'settings_updated' THEN 'Update Nurse Settings'
    ELSE 'Record System Activity'
END;

ALTER TABLE audit_logs
    MODIFY COLUMN platform VARCHAR(32) NOT NULL DEFAULT 'superadmin';

ALTER TABLE user_type
    MODIFY COLUMN role ENUM(
        'super_admin',
        'hospital_admin',
        'barangay_admin',
        'doctor',
        'nurse',
        'resident',
        'responder',
        'volunteer'
    ) NOT NULL;

SET @nurse_audit_backfill_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nurse_audit_log'
    ),
    'INSERT INTO audit_logs
        (actor_user_id, platform, platform_scope, action_code, action, source_event_key,
         entity_type, entity_id, details, created_at)
     SELECT n.nurse_id, ''mobile'', ''mobile'', n.action,
        CASE n.action
            WHEN ''prep_task_updated'' THEN ''Update Preparation Task''
            WHEN ''clinical_note_saved'' THEN ''Save Clinical Note''
            WHEN ''bed_assigned'' THEN ''Assign Hospital Bed''
            WHEN ''arrival_confirmed'' THEN ''Confirm Nurse Arrival''
            WHEN ''handoff_recorded'' THEN ''Record Patient Handoff''
            WHEN ''shift_swap_requested'' THEN ''Request Shift Swap''
            WHEN ''settings_updated'' THEN ''Update Nurse Settings''
            WHEN ''bed_ready'' THEN ''Update Bed Status''
            WHEN ''bed_preparing'' THEN ''Update Bed Status''
            WHEN ''bed_occupied'' THEN ''Update Bed Status''
            WHEN ''bed_cleaning'' THEN ''Update Bed Status''
            ELSE ''Record Nurse Activity''
        END,
        CONCAT(''legacy:nurse_audit_log:'', n.id), n.target_type,
        CAST(n.target_id AS CHAR), n.details, n.created_at
     FROM nurse_audit_log n
     ON DUPLICATE KEY UPDATE source_event_key = VALUES(source_event_key)',
    'SELECT 1'
);
PREPARE nurse_audit_backfill FROM @nurse_audit_backfill_sql;
EXECUTE nurse_audit_backfill;
DEALLOCATE PREPARE nurse_audit_backfill;

INSERT INTO audit_logs
    (actor_user_id, platform, platform_scope, action_code, action, source_event_key,
     entity_type, entity_id, details)
SELECT NULL, 'system', 'system', 'system.migration_completed',
       'Record Database Migration', 'migration:20261029_shared_platform_audit',
       'migration', '20261029_shared_platform_audit',
       JSON_OBJECT(
           'migration', '20261029_shared_platform_audit',
           'affected_objects', 'audit_logs,user_type.role',
           'outcome', 'completed',
           'initiated_by', 'unknown'
       )
WHERE NOT EXISTS (
    SELECT 1
    FROM audit_logs
    WHERE source_event_key = 'migration:20261029_shared_platform_audit'
);
