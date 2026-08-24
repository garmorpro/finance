CREATE TABLE budget_review_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    period_month DATE NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    single_use TINYINT(1) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_budget_review_links_token (token_hash),
    KEY idx_budget_review_links_household_month (household_id, period_month),
    KEY idx_budget_review_links_user (user_id),
    CONSTRAINT fk_budget_review_links_household FOREIGN KEY (household_id) REFERENCES households (id),
    CONSTRAINT fk_budget_review_links_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
