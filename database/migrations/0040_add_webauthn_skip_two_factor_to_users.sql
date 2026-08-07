ALTER TABLE users
    ADD COLUMN webauthn_skip_two_factor TINYINT(1) NOT NULL DEFAULT 1 AFTER two_factor_recovery_codes;
