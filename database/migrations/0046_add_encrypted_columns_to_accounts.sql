-- Phase 1 of encrypting "identifying/descriptive text" columns at rest
-- (see docs/security.md's "Encryption at rest" section) — accounts,
-- financial_goals (0047), and recurring_items (0048).
-- transactions.payee/notes are a deliberately separate, later phase
-- (search-behavior tradeoffs — see the same doc section).
--
-- New nullable *_encrypted columns sit alongside the existing plaintext
-- ones rather than replacing them in place — bin/encrypt-existing-text-fields.php
-- backfills them, and the plaintext columns stay untouched (read-only
-- fallback for any row not yet backfilled, and a rollback safety net)
-- until a later, separate migration drops them once the new columns
-- have been running in production without issue.
--
-- Sizing: nonce (24 bytes) + Poly1305 MAC (16 bytes) + plaintext length
-- of overhead per value. VARBINARY for the short name/institution_name
-- fields (originally VARCHAR), BLOB for notes (originally TEXT,
-- potentially much longer).
--
-- name becomes NULLable here: going forward the application writes only
-- to name_encrypted (see AccountRepository), so a brand new row has
-- nothing legitimate to put in the old plaintext name column anymore.
ALTER TABLE accounts
    MODIFY COLUMN name VARCHAR(255) NULL,
    ADD COLUMN name_encrypted VARBINARY(1100) NULL AFTER name,
    ADD COLUMN institution_name_encrypted VARBINARY(1100) NULL AFTER institution_name,
    ADD COLUMN notes_encrypted BLOB NULL AFTER notes;
