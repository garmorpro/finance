# Finance

A self-hosted, manual-entry personal finance dashboard built for household use. Finance tracks accounts, transactions, budgets, bills, goals, debt, and net worth — all entered manually, with no bank connections of any kind. See [CLAUDE.md](CLAUDE.md) for the full manual-only data policy and product requirements.

## Status

Every core feature phase from `CLAUDE.md`'s roadmap is built and then some: authentication and household access (invite-only, role-based — Owner/Administrator/Member/Viewer — with optional TOTP two-factor authentication and active session management), manual accounts with full balance history, transactions (income/expense/transfers, split transactions, receipt/PDF attachments, tags, bulk editing, search/filter, CSV import/export, a daily review queue, a global search across transactions/accounts/categories/tags), monthly budgets with user-defined sections, a full transaction rules engine (auto-categorize/tag/rename on creation, with a preview-and-confirm retroactive apply), recurring bills and subscriptions, in-app notifications, a dashboard with real charts (net worth trend, income vs. spending, spending by category) and a responsive mobile/tablet nav, savings goals, a debt overview, a cash flow page, and a configurable reports page with CSV export. Phase 8 (production hardening — security headers, automated tests, backups, this documentation) is also in place; see `docs/security.md`'s "Known limitations" for what's intentionally deferred.

Deliberately **not** built, per `CLAUDE.md`'s Manual-Only Data Policy: any bank connection, account-aggregation service, or automatic transaction sync. This is a fully manual finance tracker and will stay that way.

Not yet built: real SMTP (password reset and invitation links are logged instead of emailed — see below) and debt payoff-order comparisons (snowball vs. avalanche).

## First-time setup

After running migrations, create the first Owner account and household from the command line (there is no public registration form):

```
php bin/create-owner.php
```

## Tech stack

- **Backend:** PHP 8.1+, Composer, PDO with prepared statements
- **Frontend:** Tailwind CSS, vanilla JavaScript, Chart.js
- **Database:** MySQL 8.0
- **Server:** Apache + mod_php, self-hosted on a home server

## Setup

1. Clone the repo.
2. Copy `.env.example` to `.env` and fill in real values (never commit `.env`).
3. Install PHP dependencies: `composer install`.
4. Create the database and a least-privilege app user (see `docs/deployment.md` for the recommended `GRANT` statements).
5. Run migrations: `composer run migrate` (or `php bin/migrate.php`).
6. Point Apache's document root at `public/`.
7. Verify with `curl https://your-domain/health` — should return `{"status":"ok","database":"ok",...}`.

## Frontend build (CSS)

Styling is Tailwind CSS, compiled to a static file (`public/assets/css/app.css`) that's committed to the repo — **the production server does not need Node.js installed**, only whichever machine is used to run the build. Only rebuild after changing `resources/css/app.css`, `tailwind.config.js`, or adding new Tailwind classes to a view:

```
npm install
npm run build:css
```

## Testing

```
composer install
composer test
```

Runs the full pure-unit-test suite (validation, calculations, rounding —
no database needed) plus a database-backed integration suite that
skips itself if no test database is configured. See
[docs/testing.md](docs/testing.md) for setting one up (required to
actually exercise household isolation, transfers, and budget/net-worth
calculations, not just skip past them).

## Backups

Not automatic — `bin/backup.sh` needs to be put on a cron schedule. See
[docs/backup-and-recovery.md](docs/backup-and-recovery.md).

## Documentation

- [CLAUDE.md](CLAUDE.md) — full product requirements, architecture rules, and manual-only data policy
- [docs/architecture.md](docs/architecture.md) — layers, request lifecycle, the atomic-write pattern used everywhere money is mutated
- [docs/database.md](docs/database.md) — every table and how they relate
- [docs/api.md](docs/api.md) — the handful of internal JSON endpoints (this is not an API-first app)
- [docs/security.md](docs/security.md) — what's implemented, and known limitations
- [docs/deployment.md](docs/deployment.md) — first-time server setup, production PHP settings, update/rollback procedure
- [docs/backup-and-recovery.md](docs/backup-and-recovery.md) — backup setup and restore steps
- [docs/testing.md](docs/testing.md) — running and extending the test suite

## Security

This application handles sensitive household financial data. See [docs/security.md](docs/security.md) for what's actually implemented, and the Security Requirements and Manual-Only Data Policy sections in [CLAUDE.md](CLAUDE.md) for the governing rules.
