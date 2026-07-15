CREATE TABLE budget_category_defaults (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    planned_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_budget_category_defaults_household_category (household_id, category_id),
    CONSTRAINT fk_budget_category_defaults_household FOREIGN KEY (household_id) REFERENCES households (id),
    CONSTRAINT fk_budget_category_defaults_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
