CREATE TABLE household_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner', 'administrator', 'member', 'viewer') NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_household_user (household_id, user_id),
    KEY idx_household_members_user (user_id),
    CONSTRAINT fk_household_members_household FOREIGN KEY (household_id) REFERENCES households (id),
    CONSTRAINT fk_household_members_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
