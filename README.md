# Finance

A self-hosted, manual-entry personal finance dashboard built for household use. Finance tracks accounts, transactions, budgets, bills, goals, debt, and net worth — all entered manually, with no bank connections of any kind. See [CLAUDE.md](CLAUDE.md) for the full manual-only data policy and product requirements.

## Status

Phase 2 (Authentication and Household Access) and Phase 3 (Financial Accounts) complete. Auth: login/logout, secure sessions, CSRF protection, login rate limiting, audit logging, password reset (email stubbed — see below), user profile management, and household invitations with role-based access (Owner/Administrator/Member/Viewer). Registration is invite-only — there is no public sign-up route. Accounts: manual account CRUD across all standard types, archive/restore, and balance adjustments with full history (balances are never silently overwritten). A real Tailwind CSS design system replaces the earlier inline-styled pages. Following the phased roadmap in `CLAUDE.md`.

Password reset and household invitation emails are **stubbed**: instead of sending real email, the link is written to `storage/logs/app-*.log`. Real SMTP is a deliberate follow-up, not done yet.

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
4. Create the database and a least-privilege app user (see `CLAUDE.md` for the recommended `GRANT` statements).
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

## Documentation

- [CLAUDE.md](CLAUDE.md) — full product requirements, architecture rules, and manual-only data policy
- `docs/` — architecture, database, API, security, deployment, and backup docs (added as each area is built)

## Security

This application handles sensitive household financial data. See the Security Requirements and Manual-Only Data Policy sections in [CLAUDE.md](CLAUDE.md) before contributing.
