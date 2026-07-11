CREATE TABLE financial_goals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    responsible_user_id BIGINT UNSIGNED NULL,
    linked_account_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    goal_type ENUM(
        'emergency_fund', 'vacation', 'home_project', 'vehicle', 'baby_expenses',
        'debt_payoff', 'investment_target', 'mortgage_payoff', 'general_savings'
    ) NOT NULL DEFAULT 'general_savings',
    target_amount DECIMAL(14,2) NOT NULL,
    current_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    target_date DATE NULL,
    planned_monthly_contribution DECIMAL(14,2) NULL,
    status ENUM('active', 'completed', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_financial_goals_household (household_id),
    CONSTRAINT fk_financial_goals_household FOREIGN KEY (household_id) REFERENCES households (id),
    CONSTRAINT fk_financial_goals_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id),
    CONSTRAINT fk_financial_goals_responsible FOREIGN KEY (responsible_user_id) REFERENCES users (id),
    CONSTRAINT fk_financial_goals_account FOREIGN KEY (linked_account_id) REFERENCES accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
