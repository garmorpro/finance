-- Settings > Profile's "Quick Add key" — a per-user secret that unlocks
-- the narrow /quick-add page and its own POST endpoint without a full
-- login, for a home-screen-icon device that shouldn't need Face ID or a
-- password every single open. See docs/security.md's "Quick Add key"
-- section for the full threat model.
--
-- quick_add_key_hash is SHA-256 (CHAR(64) hex), not password_hash() —
-- deliberately, and for the same reason webauthn_credentials.
-- credential_id_hash is: unlocking has to look up *which* user a pasted
-- key belongs to by exact hash match, which only a fast, deterministic
-- hash makes possible (bcrypt's per-call salt can't be looked up this
-- way). Safe here specifically because the key itself is a long random
-- secret with real entropy, unlike a human password.
ALTER TABLE users
    ADD COLUMN quick_add_key_hash CHAR(64) NULL AFTER quick_add_default_account_id,
    ADD COLUMN quick_add_key_created_at DATETIME NULL AFTER quick_add_key_hash,
    ADD COLUMN quick_add_key_last_used_at DATETIME NULL AFTER quick_add_key_created_at,
    ADD UNIQUE KEY uq_users_quick_add_key_hash (quick_add_key_hash);
