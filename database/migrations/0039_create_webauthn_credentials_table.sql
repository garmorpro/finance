CREATE TABLE webauthn_credentials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    credential_id TEXT NOT NULL,
    credential_id_hash CHAR(64) NOT NULL,
    public_key TEXT NOT NULL,
    aaguid VARCHAR(36) NOT NULL,
    transports VARCHAR(255) NOT NULL DEFAULT '[]',
    sign_count INT UNSIGNED NOT NULL DEFAULT 0,
    device_name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    UNIQUE KEY uq_webauthn_credentials_hash (credential_id_hash),
    KEY idx_webauthn_credentials_user (user_id),
    CONSTRAINT fk_webauthn_credentials_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
