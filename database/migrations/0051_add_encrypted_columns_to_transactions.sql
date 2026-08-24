-- Phase 3 of encryption at rest (see docs/security.md's "Encryption at
-- rest" section) — transactions.amount/payee/notes. The hard case: unlike
-- accounts/goals/recurring_items, these are genuinely SQL-aggregated
-- (SUM()), SQL-filtered (amount range, WHERE amount > 0), SQL-sorted, and
-- SQL-searched (LIKE) in several places — TransactionRepository and
-- BudgetRepository/ReportingService are rewritten alongside this
-- migration to do that work in PHP after decrypting instead.
--
-- Same staged approach as every prior phase: new nullable *_encrypted
-- columns alongside the originals, which stay as a read-only fallback —
-- nothing dropped in this pass. amount/payee become NULLable (notes
-- already was) since new rows only ever populate the *_encrypted columns
-- going forward.
--
-- Sizing: payee (originally VARCHAR(255)) gets the same VARBINARY(1100)
-- headroom as accounts.name; notes (TEXT) gets BLOB; amount (DECIMAL(14,2)
-- string, max ~15 characters) gets the same VARBINARY(200) as the
-- account balance columns.
ALTER TABLE transactions
    MODIFY COLUMN amount DECIMAL(14,2) NULL,
    MODIFY COLUMN payee VARCHAR(255) NULL,
    ADD COLUMN amount_encrypted VARBINARY(200) NULL AFTER amount,
    ADD COLUMN payee_encrypted VARBINARY(1100) NULL AFTER payee,
    ADD COLUMN notes_encrypted BLOB NULL AFTER notes;
