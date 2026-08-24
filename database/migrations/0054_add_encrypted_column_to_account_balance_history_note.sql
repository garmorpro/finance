-- Found while auditing transactions.payee for phase 3 of encryption at
-- rest (see 0051): AccountBalanceHistoryRepository::record()'s $note
-- parameter carries the transaction payee ("Transaction: Whole Foods"),
-- the recurring item name ("Recurring: Netflix"), the other side's
-- account name on a transfer ("Transfer to Chase Checking"), or the
-- household's own free-text note on a manual balance adjustment — every
-- one of those is exactly the class of data phases 1-3 encrypt elsewhere,
-- but account_balance_history.note itself was never touched. Same staged
-- approach as every prior phase.
-- Sizing: VARBINARY(1100), the same headroom already used for every other
-- VARCHAR(255) column encrypted this session (accounts.name,
-- transactions.payee) — see 0051 for the reasoning.
ALTER TABLE account_balance_history
    MODIFY COLUMN note VARCHAR(255) NULL,
    ADD COLUMN note_encrypted VARBINARY(1100) NULL AFTER note;
