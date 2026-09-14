-- Settings > Profile lets a user pick which account the Quick Add page
-- (the home-screen icon's launch target) preselects, so the common case
-- needs no account tap at all — still just a default, the dropdown is
-- never removed, and the household's other members are unaffected since
-- this is a per-user column, not a household setting. NULL means "no
-- default" (the field starts on the plain "Select an account..."
-- placeholder, same as today). No ON DELETE clause: accounts are never
-- hard-deleted in this app, only archived (see AccountController::
-- archive()), so the FK can never actually be violated by a real delete.
ALTER TABLE users
    ADD COLUMN quick_add_default_account_id BIGINT UNSIGNED NULL AFTER webauthn_skip_two_factor,
    ADD CONSTRAINT fk_users_quick_add_default_account FOREIGN KEY (quick_add_default_account_id) REFERENCES accounts (id);
