ALTER TABLE transactions
    ADD COLUMN recurring_item_id BIGINT UNSIGNED NULL AFTER transfer_pair_id,
    ADD CONSTRAINT fk_transactions_recurring_item FOREIGN KEY (recurring_item_id) REFERENCES recurring_items (id);
