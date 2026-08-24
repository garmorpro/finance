-- See 0046_add_encrypted_columns_to_accounts.sql for the full reasoning
-- (new nullable *_encrypted columns, old plaintext columns kept as a
-- read-only fallback/rollback net, name becomes NULLable since new rows
-- only ever populate name_encrypted going forward).
ALTER TABLE financial_goals
    MODIFY COLUMN name VARCHAR(150) NULL,
    ADD COLUMN name_encrypted VARBINARY(700) NULL AFTER name,
    ADD COLUMN description_encrypted BLOB NULL AFTER description;
