ALTER TABLE budget_category_defaults
    ADD COLUMN effective_from_month DATE NOT NULL DEFAULT '1970-01-01' AFTER planned_amount;
