-- Store administrator-provided explanations for disabled user logins.
ALTER TABLE user_type
    ADD COLUMN IF NOT EXISTS login_disabled_reason VARCHAR(500) NULL
    AFTER banned_until;
