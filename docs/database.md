# Database

MySQL/MariaDB, `utf8mb4`. Every table uses `InnoDB` with explicit foreign
keys. Migrations are plain numbered `.sql` files in `database/migrations/`,
applied in order by `bin/migrate.php`, tracked in a `migrations` table it
creates itself. There is no down-migration mechanism — a bad migration is
fixed by adding a new one, not by editing history (except when the
original migration never successfully applied anywhere, e.g. it failed
outright due to a syntax error — see `0017` for a real example of that).

All monetary columns are `DECIMAL` (never `FLOAT`/`DOUBLE`), scale 2.
Timestamps are `DATETIME`, always UTC (`gmdate('Y-m-d H:i:s')` at write
time, never relying on MySQL's session timezone).

## Household and access model

- **`users`** — individual login accounts. `dashboard_layout` (JSON) holds
  each user's saved dashboard tile order/visibility/width (see
  `App\Support\DashboardWidgets`). `email_verified_at` has existed since
  the initial schema but was unused until public registration
  (`RegistrationController`) — `NULL` blocks login (`AuthController::login()`);
  set immediately at creation for `bin/create-owner.php` and accepted
  invitations, only via a clicked verification link for public
  registration. Migration `0043` backfilled every pre-existing account
  as verified.
- **`households`** — the top-level tenant boundary. Every piece of
  financial data belongs to exactly one household. Created either by
  `bin/create-owner.php` (server access) or public self-registration
  (`RegistrationController`) — both go through the identical
  create-user/create-household/add-as-owner/seed-categories sequence.
  `budget_reminder_*` columns are the per-household policy behind the
  budget planning reminder email (see "Budgets" below).
- **`household_members`** — join table between `users` and `households`,
  carrying `role` (`owner`/`administrator`/`member`/`viewer`).
- **`household_invitations`** — 7-day expiring invite tokens; how anyone
  joins an *existing* household (as opposed to registering their own).
- **`login_attempts`** — backs login rate limiting (`App\Support\RateLimiter`).
- **`registration_attempts`** — backs public-registration rate limiting
  (`RateLimiter::tooManyRegistrationAttempts()`) — a separate table from
  `login_attempts` since a registration attempt isn't a login attempt
  and the two shouldn't share meaning.
- **`password_reset_tokens`** — reset flow tokens (links are logged, not
  emailed — see the README).
- **`email_verification_tokens`** — public-registration email
  confirmation tokens, same shape and reasoning as
  `password_reset_tokens` (SHA-256 hash only, 24-hour expiry, `used_at`
  marks it spent).
- **`audit_logs`** — append-only record of security-relevant and
  financial actions across every module (`action` strings like
  `transaction.deleted`, `goal.contribution_added`, `budget.copied_previous`).

## Accounts

- **`accounts`** — manual financial accounts (checking, savings, credit
  card, mortgage, loans, investment, property, vehicle, etc. — see
  `AccountRepository::TYPES`). Carries `current_balance` plus optional
  debt-specific fields (`credit_limit`, `interest_rate`, `minimum_payment`,
  `payment_due_day`, `original_balance`) used by the Debt overview
  without needing a separate table. `include_in_net_worth` and
  `include_in_budget` are independent flags. `name`/`institution_name`/
  `notes`/`current_balance`/`available_balance`/`credit_limit`/
  `minimum_payment`/`original_balance` are legacy plaintext columns,
  NULLable and no longer written to — the real values live encrypted in
  the matching `*_encrypted` column (`App\Support\FieldCipher`; see
  `docs/security.md`'s "Encryption at rest"). `interest_rate` and
  `payment_due_day` are the two exceptions, still plain — a percentage
  and a scheduling detail, not currency. The plaintext columns are a
  read-only fallback for any row not yet run through
  `bin/encrypt-existing-text-fields.php` (name/institution_name/notes)
  or `bin/encrypt-existing-balance-fields.php` (the balance/limit/payment
  columns), kept around as a rollback safety net rather than dropped
  immediately.
- **Liability account balances are a positive "amount owed"**, not a
  negative number — `AccountRepository::LIABILITY_TYPES` (credit card,
  mortgage, auto/student/personal loan, other liability). Net worth
  (`ReportingService`) and the Debt overview both subtract this figure
  from assets rather than adding a negative. Because of this, a
  liability account's `current_balance` moves in the *opposite*
  direction from an asset account's for the same transaction: a charge
  increases what's owed, a payment/credit decreases it. Every place
  that applies a signed amount to a balance (creating, editing,
  deleting, or transferring a transaction; CSV import; recurring items)
  goes through `AccountRepository::applyDelta()` rather than a plain
  `bcadd()`, specifically so liability accounts get this inverted.
  Manually setting a balance directly (`AccountController`'s balance
  update) is unaffected — the user types the real target number, so
  there's no delta to invert.
- **`account_balance_history`** — every balance change, with
  `previous_balance`/`new_balance`/`note`. Balances are never silently
  overwritten; this is also what `ReportingService::netWorthTrend()`
  reconstructs historical net worth from, rather than deriving it from
  transactions. `previous_balance`/`new_balance` are legacy plaintext,
  NULLable and no longer written — encrypted the same way as
  `accounts`' own balance columns above, for the same reason: left
  alone, this table would keep every historical balance readable even
  after the current one was encrypted.

## Categories

- **`categories`** — household-scoped, typed `income`/`expense`,
  optional `group_id` (see below), optional `parent_category_id`
  (subcategories — schema supports it; no UI currently nests them).
- **`category_groups`** — user-defined sections ("Fixed Expenses",
  "Savings") shown on the Budgets page. `categories.group_id` has
  `ON DELETE SET NULL`, so deleting a section un-groups its categories
  instead of orphaning them.
- **`tags`** / **`transaction_tags`** — exist in the schema, **not wired
  up to any feature**. No repository, no UI to create a tag or attach one
  to a transaction. Reports' filter list skips tags for exactly this
  reason. Building this out is a legitimate small follow-up feature.

## Transactions

- **`transactions`** — the core ledger. `transaction_type` is
  `income`/`expense`/`transfer`. `amount` is signed (negative = expense);
  the server derives the sign from `transaction_type`, never trusting a
  client-supplied sign. `transfer_pair_id` self-references another row in
  this same table for the other side of a transfer. `recurring_item_id`
  links a transaction back to the recurring item that generated it (via
  "mark paid"). `exclude_from_budget` and `exclude_from_reports` are
  independent flags — a transfer sets both; a manually-excluded
  transaction might set only one. Soft-deleted via `deleted_at`.
- **`imports`** / **`import_rows`** — one row per CSV import attempt and
  one row per source CSV line respectively, recording accepted/skipped/rejected
  outcomes for audit purposes.

## Budgets

- **`budgets`** — one row per household per calendar month
  (`period_month`), created lazily the first time a category gets a
  planned amount for that month — visiting an empty month never writes
  a row.
- **`budget_items`** — planned amount per category per budget, written
  only when someone explicitly sets an amount for that specific month.
  `category_id` is not type-restricted at the DB level; both income and
  expense categories can have a budget line (income budgeting uses the
  same table as expense budgeting).
- **`budget_category_defaults`** — one row per household per category,
  holding the "apply to all future months" standing planned amount plus
  `effective_from_month`. Never written into `budget_items` directly;
  `BudgetController::index()` layers a household's defaults (filtered to
  `effective_from_month <= the month being viewed`) over whatever
  explicit `budget_items` exist purely for display, so it reaches every
  future month regardless of whether that month's `budgets` row already
  existed. An explicit `budget_items` line always wins over a default for
  that specific month, and a default never applies to a month before it
  took effect.
- **`budget_review_links`** — the magic-link tokens behind the budget
  planning reminder email (`bin/send-budget-reminders.php`); only
  `token_hash` is stored, never the plaintext token. `single_use` and
  `expires_at` are captured at issue time from that household's
  settings (below), not re-read live, so changing the settings never
  retroactively changes an already-sent link's behavior. See
  `docs/security.md`'s "Budget review magic links" for how this is kept
  scoped to exactly one household/user/month.
- **`households.budget_reminder_enabled` / `_days_before` /
  `_link_single_use` / `_link_expiry_days`** — the per-household policy
  those links are issued under (off by default). Editable only by
  Owner/Administrator, from Settings → Household.

## Recurring items

- **`recurring_items`** — bills, subscriptions, and recurring income.
  `next_due_date` advances from the date that *was* due when marked
  paid, not from "today" — see `RecurringItemRepository::advanceDueDate()` —
  so a late confirmation doesn't compound drift into future due dates.
  `name`/`notes` are legacy plaintext, no longer written — see
  `accounts` above for the `*_encrypted` pattern, identical here.

## Goals

- **`financial_goals`** — target amount, optional target date, optional
  `linked_account_id` (reference only — not auto-synced; contributions
  are always logged manually) and optional `responsible_user_id`.
  `current_amount` is a denormalized running total, the same
  pattern as `accounts.current_balance` + `account_balance_history`.
  `name`/`description` are legacy plaintext, no longer written — see
  `accounts` above for the `*_encrypted` pattern, identical here.
- **`goal_contributions`** — the history backing that running total.
  Adding or deleting a contribution atomically adjusts
  `financial_goals.current_amount` in the same operation
  (`GoalRepository::addContribution()`/`deleteContribution()`).

## Entity relationship notes

- Every table that holds financial data has a `household_id` foreign key,
  and every repository method that reads or writes it takes that ID
  explicitly and filters by it — this is the load-bearing authorization
  boundary (see `docs/security.md`).
- Nothing cascades on delete except `categories.group_id` (`ON DELETE SET NULL`
  when a category group is deleted). Everything else that needs "soft
  removal" uses an explicit status column (`accounts.status`,
  `transactions.deleted_at`, `recurring_items.status`,
  `financial_goals.status`, `categories.archived_at`) instead of a
  cascading hard delete, so audit history and related rows survive.
