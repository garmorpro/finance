ALTER TABLE users
    ADD COLUMN dashboard_layout JSON NULL AFTER last_login_at;
