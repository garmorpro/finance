# Deployment

Target environment: Ubuntu Server, Apache + `mod_php`, MySQL, reached
through a Cloudflare Tunnel (not direct port-forwarding, not a reverse
proxy terminating TLS on the origin server itself). See
`docs/security.md` for why that specific topology matters for how HTTPS
is enforced.

## First-time server setup

### 1. Database

Create a dedicated database and a least-privilege user — never point the
app at a MySQL root account:

```sql
CREATE DATABASE finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'finance_app'@'localhost' IDENTIFIED BY '<a long random password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON finance.* TO 'finance_app'@'localhost';
FLUSH PRIVILEGES;
```

Deliberately **not** granted: `CREATE`/`ALTER`/`DROP`/`INDEX`. Migrations
need those, so run `php bin/migrate.php` as a more privileged user (or
temporarily grant `CREATE, ALTER, INDEX` for the migration run, then
revoke) rather than giving the app's normal runtime credentials
schema-modification rights it doesn't need day to day.

### 2. Application files

```
git clone <repo> /var/www/finance/public_html/finance
cd /var/www/finance/public_html/finance
cp .env.example .env
# fill in real DB credentials, APP_URL, BACKUP_ENCRYPTION_PASSPHRASE
composer install --no-dev --optimize-autoloader
php bin/migrate.php
php bin/create-owner.php
```

### 3. File ownership and permissions

The app needs to run as a normal deploy user for `git pull`/`composer`,
but Apache (typically `www-data`) needs write access to `storage/` at
runtime (logs, CSV import staging, attachments). One-time fix if these
ever diverge:

```
sudo chown -R <deploy-user>:www-data /var/www/finance/public_html/finance
sudo chmod -R g+w /var/www/finance/public_html/finance/storage
sudo find /var/www/finance/public_html/finance/storage -type d -exec chmod g+s {} \;
```

The `g+s` (setgid) on `storage/`'s directories keeps new files Apache
creates there owned by the `www-data` group, so this doesn't need
re-running after every deploy.

### 4. Apache

Point the virtual host's document root at `public/`, not the project
root — nothing outside `public/` should be web-accessible (`.env`,
`app/`, `database/migrations/`, `storage/` all need to stay unreachable
by URL). `public/.htaccess` handles routing everything through
`index.php`.

### 5. Cloudflare Tunnel

Route the tunnel to the Apache vhost's port. Confirm in the Cloudflare
dashboard for this domain: SSL/TLS mode is "Full" or "Full (strict)",
and "Always Use HTTPS" is on — this is where HTTP→HTTPS enforcement
happens for this deployment, not in application code (see
`docs/security.md`).

### 6. Lock down phpMyAdmin

CLAUDE.md's own requirement: **never expose phpMyAdmin publicly without
strong access controls.** If it's reachable at a path under the app's
own domain (e.g. `mycfoplus.com/phpmyadmin` — the default Debian/Ubuntu
`phpmyadmin` package install pattern), that path serves every
household's raw financial data in plaintext to anyone who reaches it
and has a MySQL login — application-level household scoping
(`docs/security.md`'s "Authorization" section) only protects access
*through the app's own UI*, not direct database access.

Recommended: **Cloudflare Access** (Zero Trust → Access → Applications →
Add an application → Self-hosted), scoped to that exact path, with a
policy requiring authentication (Email OTP to an explicit allowlist of
addresses is the simplest) before Cloudflare forwards the request to
the origin at all — an unauthenticated request never reaches Apache or
phpMyAdmin. No application or Apache config needed for this layer.

Optional second layer, defense in depth: HTTP Basic Auth at the Apache
level too, in case Access is ever misconfigured or disabled by mistake.
Find wherever phpMyAdmin's `Alias`/`<Directory>` block lives (commonly
`/etc/apache2/conf-enabled/phpmyadmin.conf` on a package install), add:

```apache
<Directory /usr/share/phpmyadmin>
    AuthType Basic
    AuthName "Restricted"
    AuthUserFile /etc/apache2/.htpasswd-phpmyadmin
    Require valid-user
</Directory>
```

then `sudo htpasswd -c /etc/apache2/.htpasswd-phpmyadmin <username>` to
create the password file (drop `-c` for a second user afterward), and
`sudo apachectl configtest && sudo systemctl reload apache2`.

### 7. Verify

```
curl https://your-domain/health
```

Should return `{"status":"ok","database":"ok",...}`.

## Production PHP settings

Set in `php.ini` (or an Apache-scoped override) for the production vhost:

| Setting | Recommended | Why |
|---|---|---|
| `display_errors` | `Off` | Never show stack traces/DB errors to visitors. `App\Support\ErrorHandler` only reveals detail when `APP_DEBUG=true`, which itself must be `false` in production `.env`. |
| `log_errors` | `On` | Errors go to the PHP error log, not the browser. |
| `expose_php` | `Off` | Don't advertise the PHP version in response headers. |
| `upload_max_filesize` / `post_max_size` | `11M` / `13M` | Slightly above the app's own largest upload cap — 10MB for transaction attachments (`AttachmentController::MAX_FILE_SIZE`), 5MB for CSV import (`ImportController::MAX_FILE_SIZE`). The app's own check is the real limit; these just need to not reject a valid upload before the app ever sees it. |
| `session.cookie_httponly` | already enforced in code | `public/index.php` sets this explicitly regardless of `php.ini`, but setting it here too is harmless defense-in-depth. |

Confirm `APP_DEBUG=false` and `APP_ENV=production` in the production
`.env` — `config/app.php` defaults `debug` to `false` even if that's
missing, but don't rely on the fallback.

## Deploying an update

```
cd /var/www/finance/public_html/finance
git pull
composer install --no-dev --optimize-autoloader
php bin/migrate.php
```

`composer install` is a no-op if `composer.lock` didn't change.
`bin/migrate.php` only applies migrations not already recorded in its
`migrations` table — safe to run on every deploy even when there's
nothing new.

If the deploy changed `resources/css/app.css`'s source or added new
Tailwind classes to a view, the compiled CSS must be rebuilt *before*
committing (see the README) — there's no build step on the server
itself.

### Deploying encryption at rest (accounts/goals/recurring items)

This one deploy needs its steps done **in order**, not just
`git pull` + `migrate.php` — unlike every other feature in this app,
`ENCRYPTION_KEY` is not gracefully optional (see `.env.example`):
`AccountRepository`/`AccountBalanceHistoryRepository`/`GoalRepository`/
`RecurringItemRepository` encrypt on every write unconditionally and
throw without it, so skipping step 1 breaks adding/editing any account
(including a balance adjustment), goal, or recurring item outright, not
just leaves data unencrypted.

1. Generate a key and set `ENCRYPTION_KEY` in `.env`:
   ```
   php -r "echo base64_encode(sodium_crypto_secretbox_keygen()), PHP_EOL;"
   ```
   Back it up somewhere other than alongside your database backups —
   losing it makes every encrypted value permanently unreadable, with
   no reset like a password.
2. `git pull` and `php bin/migrate.php` as usual (migrations `0046`-`0048`
   add the name/notes/description columns, `0049`-`0050` add the
   balance/limit/payment columns).
3. Confirm the `sodium` PHP extension is enabled — it's bundled with PHP
   8.1+ by default, but a minimal/custom PHP build could have it
   disabled: `php -r "var_dump(extension_loaded('sodium'));"` should
   print `bool(true)`.
4. Backfill every existing row — two separate scripts, each dry run
   first then `--apply` (see each script's own docblock for exactly
   what it does), deliberately separate so the higher-stakes balance
   pass can be run and reviewed on its own:
   ```
   php bin/encrypt-existing-text-fields.php
   php bin/encrypt-existing-text-fields.php --apply

   php bin/encrypt-existing-balance-fields.php
   php bin/encrypt-existing-balance-fields.php --apply
   ```
   Both are safe to re-run — already-encrypted rows are skipped, so
   either can run again later to pick up anything a previous run missed.
5. Spot-check in the app itself (not phpMyAdmin, which will now show
   ciphertext for these columns by design) that account names,
   institution names, notes, balances, goal names/descriptions, and
   recurring item names/notes all still display correctly — including
   an account's balance history (its edit page) and the Debt overview
   (reads `credit_limit`/`interest_rate`/`minimum_payment`).

## Rollback procedure

There's no down-migration mechanism (see `docs/database.md`). To roll
back a bad deploy:

1. `git checkout <previous-good-commit>` (or `git revert` the bad commit
   and push, then pull that).
2. `composer install --no-dev --optimize-autoloader` to match the
   rolled-back `composer.lock`.
3. If the bad deploy included a migration that already ran and needs
   undoing, write and apply a new migration that reverses it — don't
   edit migration history that's already been applied anywhere.
4. Restore from backup (`docs/backup-and-recovery.md`) only if data was
   actually corrupted, not just for a code-only rollback.

## Health checks and monitoring

`GET /health` (no auth required) checks the app can reach the database.
Point an uptime monitor at it. It intentionally still returns HTTP `200`
even when degraded — check the JSON body's `status` field, not just
reachability (see `docs/api.md`).

## Log rotation

`storage/logs/app-*.log` (one file per day, from `App\Support\Logger`)
grows unbounded without rotation. Add a `logrotate` config, e.g.
`/etc/logrotate.d/finance`:

```
/var/www/finance/public_html/finance/storage/logs/app-*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
}
```

## Scheduled jobs

`bin/send-budget-reminders.php` issues each household's budget planning
reminder (Settings → Household → "Budget planning reminders") once its
own days-before window opens. Safe to run daily — already-issued links
for a given household/user/month are never re-issued, so a missed day
just catches up. Add it to cron alongside the backup job:

```
0 8 * * * php /var/www/finance/public_html/finance/bin/send-budget-reminders.php >> /var/log/finance-reminders.log 2>&1
```

Actually emails the link when `MAIL_*` is set in `.env` (see
`.env.example` for Gmail/Workspace SMTP setup — App Password, and the
From-address restrictions Gmail enforces). Without that configured,
the link is written to `storage/logs/app-*.log` instead — the same
convention this app still uses for password resets and household
invitations (see `docs/security.md`), which haven't been switched over
to real sending yet.

**Testing it by hand**, without waiting for a real trigger date or cron:

1. Run migrations if you haven't (`php bin/migrate.php`), then
   `composer update phpmailer/phpmailer` to install the new dependency
   (declared in `composer.json`, not installed until this runs).
2. Fill in `MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/`MAIL_PASSWORD`/
   `MAIL_FROM_ADDRESS` in `.env`.
3. Settings → Household → turn on "Email a review link before each
   month starts". Set "Send reminder" to whichever option makes today
   fall inside the window — e.g. if there are 10 or fewer days left in
   the current month, "10 days before month starts" already covers
   today; otherwise this won't trigger until closer to month-end (the
   script deliberately won't fire early just because you asked it to
   check).
4. `php bin/send-budget-reminders.php` — prints what it did per
   recipient (`Emailed ...` or `Logged (not emailed) ...`), so a bad
   SMTP config is visible immediately rather than silently swallowed.
5. Check the inbox on file for that Owner/Administrator. If it logged
   instead, the link is in the newest `storage/logs/app-*.log` line
   containing `review_url`.

Running it again the same day is safe — a household/user/month that
already has a link is skipped, not re-sent (see the repeated-run
behavior described above), so testing a second time means changing
the household's own reminder setting again, or manually deleting the
row from `budget_review_links` for that combination first.

## Backups

See `docs/backup-and-recovery.md` — not automatic until `bin/backup.sh`
is put on a cron schedule.
