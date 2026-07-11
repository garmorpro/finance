ALTER TABLE transactions
    ADD COLUMN is_split TINYINT(1) NOT NULL DEFAULT 0 AFTER category_id;
