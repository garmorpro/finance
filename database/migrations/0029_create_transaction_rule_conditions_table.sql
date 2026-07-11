CREATE TABLE transaction_rule_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id BIGINT UNSIGNED NOT NULL,
    field VARCHAR(30) NOT NULL,
    operator VARCHAR(20) NOT NULL,
    value VARCHAR(255) NOT NULL,
    KEY idx_transaction_rule_conditions_rule (rule_id),
    CONSTRAINT fk_transaction_rule_conditions_rule FOREIGN KEY (rule_id) REFERENCES transaction_rules (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
