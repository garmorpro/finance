ALTER TABLE households
    ADD COLUMN budget_reminder_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER owner_user_id,
    ADD COLUMN budget_reminder_days_before TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER budget_reminder_enabled,
    ADD COLUMN budget_reminder_link_single_use TINYINT(1) NOT NULL DEFAULT 1 AFTER budget_reminder_days_before,
    ADD COLUMN budget_reminder_link_expiry_days TINYINT UNSIGNED NOT NULL DEFAULT 7 AFTER budget_reminder_link_single_use;
