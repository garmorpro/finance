-- See 0051_add_encrypted_columns_to_transactions.sql for the full
-- reasoning. transaction_splits.amount is a second table holding the
-- same class of dollar amounts (a split transaction's per-category
-- portions) — left alone, it would keep every split transaction's real
-- amounts readable even after transactions.amount itself was encrypted.
ALTER TABLE transaction_splits
    MODIFY COLUMN amount DECIMAL(14,2) NULL,
    ADD COLUMN amount_encrypted VARBINARY(200) NULL AFTER amount;
