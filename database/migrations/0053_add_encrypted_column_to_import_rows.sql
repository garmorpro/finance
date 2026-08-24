-- See 0051_add_encrypted_columns_to_transactions.sql for the full
-- reasoning. import_rows.raw_data stores the original CSV line text for
-- every imported row (shown back via ImportRowRepository::listRejectedForImport())
-- — left alone, it would keep every imported transaction's real payee/
-- amount/date readable in plaintext even after transactions.amount/payee
-- were encrypted, since it's a full copy of the source data, not derived
-- from the transactions table.
ALTER TABLE import_rows
    MODIFY COLUMN raw_data TEXT NULL,
    ADD COLUMN raw_data_encrypted BLOB NULL AFTER raw_data;
