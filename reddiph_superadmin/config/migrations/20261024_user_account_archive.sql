-- Archive accounts without deleting their related incident or audit history.
ALTER TABLE user_type
    ADD COLUMN IF NOT EXISTS account_archived_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS account_archived_reason VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS account_archived_previous_banned_until DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS account_archived_previous_login_reason VARCHAR(500) NULL DEFAULT NULL,
    ADD INDEX IF NOT EXISTS idx_user_type_account_archived_at (account_archived_at);
