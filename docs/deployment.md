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

### 6. Verify

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

## Backups

See `docs/backup-and-recovery.md` — not automatic until `bin/backup.sh`
is put on a cron schedule.
