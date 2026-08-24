-- See 0049_add_encrypted_columns_to_accounts_balances.sql for the full
-- reasoning. account_balance_history is a permanent, ever-growing audit
-- trail of every balance ever set on every account — left alone, it
-- would keep every historical balance fully readable in plaintext even
-- after accounts.current_balance itself is encrypted, defeating the
-- point for any account that's ever had a balance change recorded.
ALTER TABLE account_balance_history
    MODIFY COLUMN previous_balance DECIMAL(14,2) NULL,
    MODIFY COLUMN new_balance DECIMAL(14,2) NULL,
    ADD COLUMN previous_balance_encrypted VARBINARY(200) NULL AFTER previous_balance,
    ADD COLUMN new_balance_encrypted VARBINARY(200) NULL AFTER new_balance;
