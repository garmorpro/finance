CREATE TABLE transaction_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_transaction_rules_household (household_id),
    CONSTRAINT fk_transaction_rules_household FOREIGN KEY (household_id) REFERENCES households (id),
    CONSTRAINT fk_transaction_rules_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
