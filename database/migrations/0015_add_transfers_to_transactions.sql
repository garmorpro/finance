ALTER TABLE transactions
    MODIFY COLUMN transaction_type ENUM('income', 'expense', 'transfer') NOT NULL,
    ADD COLUMN transfer_pair_id BIGINT UNSIGNED NULL AFTER category_id,
    ADD CONSTRAINT fk_transactions_transfer_pair FOREIGN KEY (transfer_pair_id) REFERENCES transactions (id);
