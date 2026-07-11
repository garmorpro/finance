# Hearth

A self-hosted, manual-entry personal finance dashboard built for household use. Hearth tracks accounts, transactions, budgets, bills, goals, debt, and net worth — all entered manually, with no bank connections of any kind. See [CLAUDE.md](CLAUDE.md) for the full manual-only data policy and product requirements.

## Status

Phase 1 (Project Foundation) complete: environment config, database connection, migration runner, base routing, error handling, and logging. No user-facing features yet — auth is Phase 2. Following the phased roadmap in `CLAUDE.md`.

## Tech stack

- **Backend:** PHP 8.1+, Composer, PDO with prepared statements
- **Frontend:** Tailwind CSS, vanilla JavaScript, Chart.js
- **Database:** MySQL 8.0
- **Server:** Apache + mod_php, self-hosted on a home server

## Setup

1. Clone the repo.
2. Copy `.env.example` to `.env` and fill in real values (never commit `.env`).
3. Install PHP dependencies: `composer install`.
4. Create the database and a least-privilege app user (see `CLAUDE.md` for the recommended `GRANT` statements).
5. Run migrations: `composer run migrate` (or `php bin/migrate.php`).
6. Point Apache's document root at `public/`.
7. Verify with `curl https://your-domain/health` — should return `{"status":"ok","database":"ok",...}`.

## Testing

```
composer install
composer test
```

## Documentation

- [CLAUDE.md](CLAUDE.md) — full product requirements, architecture rules, and manual-only data policy
- `docs/` — architecture, database, API, security, deployment, and backup docs (added as each area is built)

## Security

This application handles sensitive household financial data. See the Security Requirements and Manual-Only Data Policy sections in [CLAUDE.md](CLAUDE.md) before contributing.
