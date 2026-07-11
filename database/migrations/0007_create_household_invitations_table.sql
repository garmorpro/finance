CREATE TABLE household_invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    invited_by_user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('administrator', 'member', 'viewer') NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_invitation_token (token_hash),
    KEY idx_invitations_household (household_id),
    KEY idx_invitations_email (email),
    CONSTRAINT fk_invitations_household FOREIGN KEY (household_id) REFERENCES households (id),
    CONSTRAINT fk_invitations_invited_by FOREIGN KEY (invited_by_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
