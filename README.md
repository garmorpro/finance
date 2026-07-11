# Hearth

A self-hosted, manual-entry personal finance dashboard built for household use. Hearth tracks accounts, transactions, budgets, bills, goals, debt, and net worth — all entered manually, with no bank connections of any kind. See [CLAUDE.md](CLAUDE.md) for the full manual-only data policy and product requirements.

## Status

Early scaffold — no functional features yet. Following the phased roadmap in `CLAUDE.md`.

## Tech stack

- **Backend:** PHP 8.1+, Composer, PDO with prepared statements
- **Frontend:** Tailwind CSS, vanilla JavaScript, Chart.js
- **Database:** MySQL 8.0
- **Server:** Apache + mod_php, self-hosted on a home server

## Local / server setup

1. Clone the repo.
2. Copy `.env.example` to `.env` and fill in real values (never commit `.env`).
3. Install PHP dependencies: `composer install`.
4. Create the database and run migrations (migration tooling TBD — see `database/migrations/`).
5. Point Apache's document root at `public/`.

## Documentation

- [CLAUDE.md](CLAUDE.md) — full product requirements, architecture rules, and manual-only data policy
- `docs/` — architecture, database, API, security, deployment, and backup docs (added as each area is built)

## Security

This application handles sensitive household financial data. See the Security Requirements and Manual-Only Data Policy sections in [CLAUDE.md](CLAUDE.md) before contributing.
