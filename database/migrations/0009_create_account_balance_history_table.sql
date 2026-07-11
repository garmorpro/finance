CREATE TABLE account_balance_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NOT NULL,
    previous_balance DECIMAL(14,2) NOT NULL,
    new_balance DECIMAL(14,2) NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_balance_history_account (account_id),
    CONSTRAINT fk_balance_history_account FOREIGN KEY (account_id) REFERENCES accounts (id),
    CONSTRAINT fk_balance_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
