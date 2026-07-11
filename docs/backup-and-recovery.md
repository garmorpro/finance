# Backup and Recovery

This app is self-hosted, so you are the backup strategy — nothing is
backed up automatically unless you set up `bin/backup.sh` on a schedule.

## What gets backed up

`bin/backup.sh`:

1. Dumps the MySQL database with `mysqldump --single-transaction` (a
   consistent snapshot without locking tables, safe to run while the app
   is live).
2. Archives `storage/attachments` and `storage/imports`.
3. Combines both into one `.tar`, encrypts it with AES-256 via `openssl`,
   and writes it to `storage/backups/`.
4. Deletes backups older than `BACKUP_RETENTION_DAYS` (default 30).

`storage/logs` is intentionally excluded — it's operational output, not
data you'd need to recover from.

## One-time setup

1. Add a strong, unique passphrase to `.env`:

   ```
   BACKUP_ENCRYPTION_PASSPHRASE=<a long random passphrase, not reused anywhere else>
   ```

   The script refuses to run without this set — backups are never
   written unencrypted. Store the passphrase somewhere other than the
   server itself (a password manager). If you lose it, your backups are
   unrecoverable; that's the trade-off of encryption actually working.

2. Make sure `mysqldump` and `openssl` are available (both ship with
   standard MySQL/MariaDB and OpenSSL installs — nothing extra to
   install on a typical Ubuntu server).

3. Test it manually first:

   ```
   cd /var/www/finance/public_html/finance
   ./bin/backup.sh
   ```

   Confirm a file appears in `storage/backups/` and that decrypting it
   (see below) actually produces a working archive — an untested backup
   is not a backup.

## Scheduling with cron

Nightly at 2am, as the user that owns the app directory:

```
0 2 * * * cd /var/www/finance/public_html/finance && ./bin/backup.sh >> storage/logs/backup.log 2>&1
```

## Off-server copies

`storage/backups/` still lives on the same server as the database it's
backing up — if that disk fails, both are gone. Periodically copy the
encrypted `.tar.enc` files somewhere else (another machine, an external
drive, cloud object storage). Because they're already encrypted, it's
safe to store them somewhere you don't otherwise fully trust — the
passphrase is what actually protects the data, not where the file sits.

## Restoring

Restoring overwrites live data, so this is deliberately a manual,
step-by-step process rather than a single script — take a moment to
confirm you're restoring the right file to the right place before
running the destructive step.

1. **Decrypt the backup:**

   ```
   openssl enc -aes-256-cbc -pbkdf2 -d \
       -pass "pass:<the passphrase>" \
       -in finance-backup-YYYYMMDD-HHMMSS.tar.enc \
       -out restore.tar
   ```

2. **Extract it:**

   ```
   tar -xf restore.tar
   # produces database.sql and storage.tar.gz
   ```

3. **Restore the database.** This overwrites the current database —
   double-check `DB_DATABASE` before running this:

   ```
   mysql --host="$DB_HOST" --user="$DB_USERNAME" -p "$DB_DATABASE" < database.sql
   ```

   If you're restoring onto a fresh server instead of recovering a
   damaged one, create the database and a scoped user first (see
   `docs/deployment.md`).

4. **Restore attachments/imports:**

   ```
   tar -xzf storage.tar.gz -C /var/www/finance/public_html/finance/
   ```

5. **Fix ownership/permissions** if the restore ran as a different user
   than the app normally does — see `docs/deployment.md` for the
   expected ownership.

6. **Sanity-check the app**: log in, confirm account balances and a few
   recent transactions look right, check `/health`.

## What this doesn't cover

- **Point-in-time recovery.** Backups are periodic snapshots (whatever
  your cron schedule is), not continuous — anything written between the
  last backup and an incident is gone. Binary-log-based point-in-time
  recovery is a possible future improvement, not implemented here.
- **Automated restore testing.** Periodically doing a real restore onto
  a throwaway database (e.g. your `.env.testing` database — see
  `docs/testing.md`) is the only way to know your backups actually work.
  Nothing in this app does that for you automatically.
