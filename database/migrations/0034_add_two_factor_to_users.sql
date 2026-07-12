ALTER TABLE users
    ADD COLUMN two_factor_secret VARCHAR(64) NULL AFTER password_hash,
    ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_secret,
    ADD COLUMN two_factor_recovery_codes TEXT NULL AFTER two_factor_enabled_at;
