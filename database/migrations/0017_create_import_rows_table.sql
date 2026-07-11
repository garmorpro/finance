CREATE TABLE import_rows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NULL,
    row_number INT UNSIGNED NOT NULL,
    raw_data TEXT NOT NULL,
    status ENUM('imported', 'skipped', 'rejected') NOT NULL,
    message VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_import_rows_import (import_id),
    CONSTRAINT fk_import_rows_import FOREIGN KEY (import_id) REFERENCES imports (id),
    CONSTRAINT fk_import_rows_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
