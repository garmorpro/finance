CREATE TABLE transaction_splits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    amount DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_transaction_splits_transaction (transaction_id),
    CONSTRAINT fk_transaction_splits_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id),
    CONSTRAINT fk_transaction_splits_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
