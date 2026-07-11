# Testing

## Running the suite

```
composer test
```

This runs PHPUnit against everything in `tests/`. Two kinds of tests live
there side by side:

- **Pure unit tests** — no database, always run. Cover validation
  (`MoneyInput`), formatting (`Money`), calculation services
  (`GoalService`, `DebtService`), CSV parsing, the router, CSRF, dashboard
  widget resolution logic, and financial rounding behavior (bcmath vs.
  binary float traps).
- **Integration tests** (`tests/Integration/`) — hit a real database.
  These **skip themselves** (not fail) if no test database is configured,
  so `composer test` still runs cleanly with just `git clone && composer
  install`.

## Setting up a test database

Integration tests are destructive by nature (they create and roll back
real rows), so they must never run against your production database.

1. Create a dedicated database, e.g. `finance_test`, with its own user —
   don't reuse your production `DB_USERNAME`/`DB_DATABASE`.
2. Copy the example config and fill in the test database's credentials:

   ```
   cp .env.testing.example .env.testing
   ```

3. `DB_DATABASE` in `.env.testing` **must contain the literal substring
   "test"** — `tests/DatabaseTestCase.php` checks for this and refuses to
   run otherwise. This is a deliberate safety net: if `.env.testing`
   ever gets misconfigured to point at production by mistake, the suite
   skips instead of running destructive operations against real data.
4. Apply migrations to the test database:

   ```
   APP_ENV=testing php bin/migrate.php
   ```

5. Run the suite:

   ```
   composer test
   ```

If `.env.testing` doesn't exist at all, the integration tests report as
**skipped**, not failed — check the PHPUnit output for a `Tests\Integration\...`
block ending in `S` (skipped) rather than `.` (passed) to tell the
difference between "no test DB configured" and "tests didn't run."

## How integration tests stay isolated

Every integration test runs inside a transaction (`DatabaseTestCase::setUp()`
opens one, `tearDown()` rolls it back), so nothing written during a test
ever persists — you can run the suite repeatedly against the same test
database without accumulating junk data or needing to reset anything
between runs.

**Scope note:** integration tests call repositories directly (e.g.
`TransactionRepository::create()`), not controllers. Several controllers
(`TransactionController`, `BudgetController`, `GoalController`,
`RecurringController`) open their own database transaction around a
multi-step write. Nesting that inside the test's own wrapping transaction
would throw ("There is already an active transaction"), since this
codebase doesn't use `SAVEPOINT`s. Testing at the repository layer — the
same sequence of calls a controller makes, in the same order — covers the
same logic without that conflict. `tests/Integration/TransferIntegrationTest.php`
is the clearest example: it replicates
`TransactionController::storeTransfer()`'s exact sequence of repository
calls in a private helper.

Full HTTP-level tests (simulating a real request through the router,
session, and CSRF token) aren't set up in this suite. That's a
reasonable next step if this app grows a public API, but for a
server-rendered app the highest-value logic — money math, household
isolation, financial rounding — lives in repositories and services, which
this suite does cover.

## What's covered vs. what's manual

Covered by `composer test`:

- Household data isolation (a record created under one household is
  invisible, and unmodifiable, through every repository when queried
  with a different household's ID — including the "guess a sequential
  ID" IDOR case).
- Account CRUD, archive/restore, net worth calculation.
- Transaction CRUD, soft delete, duplicate detection (`existsSimilar`).
- Transfer handling (linked pair creation, balance movement on both
  sides, balance reversal on delete).
- Budget calculations (`actualByCategory`, `monthSummary`, "copy last
  month" not overwriting existing lines).
- CSV parsing edge cases (empty file, header-only file, whitespace).
- Financial rounding (decimal-safe bcmath vs. binary float drift).

Not covered by automated tests — verify manually before shipping a
change that touches these:

- **Authentication flows** (login rate limiting, session fixation
  protection, password reset token expiry) — these depend on session
  state and `exit()` calls in `AuthMiddleware` that don't unit-test
  cleanly without a larger refactor. Exercise these by hand against a
  running instance.
- **Role-based permission boundaries at the HTTP layer** (a Member
  correctly getting a 403 on an Owner-only action) — the underlying
  household-scoping is covered (see above), but the HTTP-level gate
  (`AuthMiddleware::requireRole()`) calls `exit()` directly, which would
  terminate the PHPUnit process if called from a test.
- **File upload behavior** (CSV import's actual `move_uploaded_file()`
  path, MIME detection) — depends on `$_FILES` superglobal state that's
  awkward to fabricate in a unit test; `CsvParser` itself (the part that
  doesn't depend on `$_FILES`) is covered.
- **Mobile/responsive layout, dark mode, accessibility** — visual, not
  something PHPUnit checks.

For anything in the "not covered" list, use a manual acceptance-test
pass: log in as each role in a household, attempt the action, confirm
the expected allow/deny outcome.
