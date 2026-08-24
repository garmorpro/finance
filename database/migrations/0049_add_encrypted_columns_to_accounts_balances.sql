-- Phase 2 of encryption at rest (see docs/security.md's "Encryption at
-- rest" section) — account balance/limit/payment dollar fields.
-- Deliberately scoped to actual currency amounts, not interest_rate
-- (a percentage, not a balance) or payment_due_day (a scheduling
-- detail, not financial data).
--
-- Same staged approach as the accounts.name/institution_name/notes
-- columns (0046): new nullable *_encrypted columns alongside the
-- originals, which stay as a read-only fallback/rollback net rather
-- than being dropped. current_balance becomes NULLable here (it was
-- NOT NULL) since new rows only ever populate current_balance_encrypted
-- going forward; the other four were already nullable.
--
-- Sizing: a DECIMAL(14,2) string is at most 15 characters
-- ("999999999999.99"); nonce (24 bytes) + Poly1305 MAC (16 bytes) +
-- that is comfortably under 100 bytes, but VARBINARY(200) leaves
-- headroom without costing anything meaningful.
ALTER TABLE accounts
    MODIFY COLUMN current_balance DECIMAL(14,2) NULL,
    ADD COLUMN current_balance_encrypted VARBINARY(200) NULL AFTER current_balance,
    ADD COLUMN available_balance_encrypted VARBINARY(200) NULL AFTER available_balance,
    ADD COLUMN credit_limit_encrypted VARBINARY(200) NULL AFTER credit_limit,
    ADD COLUMN minimum_payment_encrypted VARBINARY(200) NULL AFTER minimum_payment,
    ADD COLUMN original_balance_encrypted VARBINARY(200) NULL AFTER original_balance;
