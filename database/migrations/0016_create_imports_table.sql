CREATE TABLE imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    imported_by_user_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    imported_count INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
    rejected_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_imports_household (household_id),
    CONSTRAINT fk_imports_household FOREIGN KEY (household_id) REFERENCES households (id),
    CONSTRAINT fk_imports_user FOREIGN KEY (imported_by_user_id) REFERENCES users (id),
    CONSTRAINT fk_imports_account FOREIGN KEY (account_id) REFERENCES accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
