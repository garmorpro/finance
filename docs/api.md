# API

This is a server-rendered application, not an API-first one. There is no
general `/api/*` REST surface — CLAUDE.md sketches one out
(`/api/accounts`, `/api/transactions`, etc.) as a possible future
direction if this app ever needs to support a mobile client or
automation, but building that out remains a distinct, not-yet-started
piece of work. The one exception is the single read-only reporting
endpoint under `/api/master-hq/v1/` documented below, built specifically
for one trusted external consumer, not as the start of a general API.

Everything else in the app is regular server-rendered HTML forms and
links, plus a handful of internal JSON endpoints the dashboard's own
JavaScript calls — see the Controllers under `app/Controllers/` for the
full route list (registered in `public/index.php`).

## Conventions for the internal (session-authenticated) endpoints

- Every JSON endpoint requires an authenticated session
  (`AuthMiddleware::requireAuth()`) — there is no API-key/token scheme
  for these; it's the same cookie-based session as the rest of the app.
- Every JSON endpoint is `POST` and requires `csrf_token` in the request
  body, verified the same way as every other state-changing form in the
  app.
- Errors return `{"error": "<message>"}` with a non-2xx status. `419` is
  used for an expired/invalid CSRF token (matching Laravel's convention
  for the same case, chosen for familiarity, not because this app uses
  Laravel). `422` for invalid input.
- Success returns `{"status": "ok"}`.

## Master HQ read-only reporting API

**MyCFO+ is the source of truth for financial calculations. Consumers
such as Master HQ should display these values rather than
recalculating financial metrics independently.**

A single read-only endpoint for one trusted external consumer ("Master
HQ") to build a high-level financial dashboard from, without ever
querying this app's database directly. Everything it returns is a
summary or aggregate — never a raw transaction, never a bank/card
number (this app never stores those regardless, per its manual-only
data policy), never an internal table name or primary key exposed
as-is.

### Authentication

Server-to-server bearer token, not the session-cookie scheme every
other endpoint in this app uses (Master HQ is another server, not a
signed-in browser). Send:

```
Authorization: Bearer <MASTER_HQ_API_TOKEN>
```

The token is a single static secret, configured via the
`MASTER_HQ_API_TOKEN` environment variable (see `.env.example` — never
committed with a real value; generate one with e.g. `openssl rand -hex
32`). Compared with `hash_equals()` (`App\Support\MasterHqAuth`), never
`===`, to avoid a timing side-channel. **An unconfigured token fails
closed** — every request is rejected with `401` until
`MASTER_HQ_API_TOKEN` is actually set, never treated as "auth
disabled."

This app supports multiple households, but a bearer token has no
concept of "which household is signed in" the way a browser session
does. `MASTER_HQ_HOUSEHOLD_ID` (also in `.env.example`) explicitly
configures which household this endpoint reports on — it's not guessed
(e.g. "the first household created"). If it's unset or doesn't match a
real household, the endpoint returns `500` (a server misconfiguration,
not something the caller did wrong) and logs the detail server-side
rather than in the response.

Wrong/missing token and wrong/missing household ID are never
distinguished in the response body — both are a generic message with
the appropriate status code, so a failed request can't be used to probe
toward a valid credential or a valid household id.

### `GET /api/master-hq/v1/summary`

No query parameters, no request body — the response always covers the
current calendar month (month-to-date) plus derived previous-month and
year-to-date figures, computed as of the moment the request is
received.

**Status codes:**
- `200` — success.
- `401` — missing, malformed, or incorrect bearer token.
- `500` — server misconfiguration (`MASTER_HQ_HOUSEHOLD_ID` unset or
  invalid) or an unexpected internal failure. Never includes a stack
  trace, SQL, or any other implementation detail in the response body;
  the real cause is logged server-side only.
- `400` doesn't currently apply to this endpoint — it takes no
  parameters to validate. Reserved for a future endpoint under this
  namespace that does.

**Date/time semantics:** the whole app runs in UTC (`config/app.php`'s
`timezone`, applied globally at boot) — there is no per-household or
per-request timezone. "Month to date" = `[first day of the current UTC
month, now]`. "Previous month" = the full prior calendar month.
"Year to date" = `[January 1 of the current UTC year, now]`, summed
across each month's own totals — computed correctly across a
December → January boundary (verified by
`tests/Services/MasterHqSummaryServiceTest.php`'s date-boundary test,
which derives its expected dates from `gmdate()` at run time rather
than a hardcoded date, so it's correct regardless of which month it
actually runs in).

**Calculation ownership:** every figure is produced by
`App\Services\ReportingService` — the same service class the dashboard,
Cash Flow page, and Reports page already use — via
`App\Services\MasterHqSummaryService`, which only reshapes that output
into this endpoint's JSON contract. No financial arithmetic is
duplicated or reimplemented for this endpoint. The one genuinely new
calculation is `ReportingService::dailyIncomeExpense()` (daily
granularity trend data — every other trend series in this app is
monthly); it follows the exact same conventions
(`exclude_from_reports` honored, transfers excluded, bcmath throughout)
as the monthly series it sits alongside.

**Response schema:**

| Field | Type | Notes |
|---|---|---|
| `period` | string | Current month, `YYYY-MM`. |
| `currency` | string | Always `"USD"` — this app has no per-household currency setting; every amount is an implicitly-USD `DECIMAL(14,2)`. |
| `income.monthToDate` / `.previousMonth` | string | Decimal-string amounts (never floats — see `docs/database.md`'s currency notes). |
| `income.changePercent` | number \| null | `(monthToDate - previousMonth) / previousMonth * 100`. `null` when `previousMonth` is `0` — an undefined/infinite percent is more misleading than an explicit "not available." |
| `expenses.*` | same shape as `income.*` | Always a positive magnitude, not the transaction table's stored negative sign. |
| `net.monthToDate` / `.yearToDate` | string | `income - expenses` for the respective period. |
| `net.changePercent` | number \| null | Month-to-date net vs. previous month's net, same null-on-zero rule. |
| `netWorth.current` | string | Total assets minus total liabilities, right now — the exact figure the dashboard's own "Net Worth" stat shows (`AccountRepository::netWorthSummary()`), only accounts with `include_in_net_worth = 1`. |
| `netWorth.previousMonth` | string \| null | Reconstructed net worth as of the end of the previous calendar month (`ReportingService::netWorthTrend()` — the same calculation the Net Worth Trend chart draws from). `null` only when the household has no net-worth-eligible accounts at all; see "Net worth calculation notes" below for two smaller known simplifications this inherits. |
| `netWorth.changePercent` | number \| null | `current` vs. `previousMonth`, same null-on-zero-or-missing rule as every other `changePercent` field. |
| `savingsRate` | number \| null | Percent of month-to-date income logged as `goal_contributions` this month (exactly what the dashboard's "Savings Rate" stat already means — see `ReportingService::savingsRate()`). `null` when month-to-date income is `0`. |
| `trend` | array | One entry per calendar day from the 1st of the current month through today, zero-filled for days with no activity. Deliberately daily rather than a raw transaction feed. |
| `trend[].date` | string | `YYYY-MM-DD`. |
| `trend[].income` / `.expenses` | string | That day's totals. |
| `incomeSources` | array | See "Known unsupported metrics" below — this app has no distinct income-source entity, so this is derived from income-type categories. |
| `incomeSources[].id` | string | `"cat_" + the category's own id` — namespaced rather than a bare integer so it doesn't read as some other kind of internal record. Stable for as long as the category exists. |
| `incomeSources[].type` | string | Always `"other"` (see below). |
| `incomeSources[].expenses` / `.hours` / `.effectiveHourlyRate` | null | Always `null` — genuinely unsupported, never fabricated. |
| `updatedAt` | string | ISO-8601 UTC timestamp (`YYYY-MM-DDTHH:mm:ssZ`) of when this response was generated — not a cached/stale value. |

### Known unsupported metrics

This app is a manual-entry **household** finance tracker (per
`CLAUDE.md`'s product scope), not a freelance/gig-income tracker — it
has no concept of a distinct income source (a job, a client, a side
hustle) with its own hours-worked or hourly-rate data. The closest real
thing is a plain income-type category. `incomeSources` is built from
those:

- `type` is always `"other"` — there's no job/side\_hustle/freelance/
  business distinction to draw from.
- `expenses` is always `null` — an income category has no
  attributable-expense concept in this schema (expense transactions
  belong to expense-type categories, not income ones).
- `net` equals `income` (since `expenses` is unavailable).
- `hours` and `effectiveHourlyRate` are always `null` — not tracked
  anywhere in this app.

If Master HQ needs real per-source hours/rate/profit tracking, that
requires a new entity in MyCFO+ itself (a dedicated income-source table
with its own fields) — out of scope for this endpoint, which only
reshapes data that already exists.

### Net worth calculation notes

`netWorth.current` is exact — it's the same live, direct calculation
(`AccountRepository::netWorthSummary()`) the dashboard's own "Net
Worth" stat shows: sum of `current_balance` across every account with
`include_in_net_worth = 1`, liabilities subtracted from assets.

`netWorth.previousMonth`, unlike `incomeSources`' limitations above,
**is genuinely supported** — this app reconstructs historical net
worth from `account_balance_history` (`ReportingService::
netWorthTrend()`, the same method behind the Net Worth Trend chart),
so this field returns a real value, not a guess. It inherits that
method's two existing, documented simplifications:

- An account **archived** between last month and now drops out of the
  reconstructed *past* figure too, not only future ones — the
  historical comparison always reflects the household's *current* set
  of net-worth-eligible accounts, not whichever accounts actually
  existed at that point in time.
- An account with **no recorded balance-history entries** (its balance
  has never been explicitly adjusted since creation) falls back to its
  *current* balance for any historical cutoff, since there's nothing
  else to reconstruct from — so a never-adjusted account contributes
  the same number to both `current` and `previousMonth`.

`previousMonth` is `null` only when the household has no net-worth-
eligible accounts at all (nothing for the underlying calculation to
produce a series from). It can legitimately be `"0.00"` — e.g. every
current account was created after last month's cutoff — which is a
real reconstructed value, not a stand-in for "unavailable"; only the
`null` case means "not available."

### Example request

```bash
curl -s \
  -H "Authorization: Bearer $MASTER_HQ_API_TOKEN" \
  https://finance.morganserver.com/api/master-hq/v1/summary
```

### Example response

```json
{
  "period": "2026-08",
  "currency": "USD",
  "income": {
    "monthToDate": "4808.90",
    "previousMonth": "4600.00",
    "changePercent": 4.5
  },
  "expenses": {
    "monthToDate": "3771.98",
    "previousMonth": "3900.00",
    "changePercent": -3.3
  },
  "net": {
    "monthToDate": "1036.92",
    "yearToDate": "7940.15",
    "changePercent": 47.9
  },
  "netWorth": {
    "current": "84250.18",
    "previousMonth": "81890.44",
    "changePercent": 2.9
  },
  "savingsRate": 12.5,
  "trend": [
    { "date": "2026-08-01", "income": "0.00", "expenses": "45.20" },
    { "date": "2026-08-02", "income": "1500.00", "expenses": "0.00" }
  ],
  "incomeSources": [
    {
      "id": "cat_7",
      "name": "Salary",
      "type": "other",
      "income": "4200.00",
      "expenses": null,
      "net": "4200.00",
      "hours": null,
      "effectiveHourlyRate": null
    },
    {
      "id": "cat_12",
      "name": "Freelance Design",
      "type": "other",
      "income": "608.90",
      "expenses": null,
      "net": "608.90",
      "hours": null,
      "effectiveHourlyRate": null
    }
  ],
  "updatedAt": "2026-08-19T14:32:07Z"
}
```

## Endpoints

### `POST /dashboard/layout`

Saves the dragged tile order for the current user's dashboard.

**Body:** `csrf_token`, `order` — a JSON-encoded array of widget key
strings (e.g. `["net_worth","accounts","budget"]`). Every key is
validated against `App\Support\DashboardWidgets`' known widget list
server-side; unknown keys are rejected, not silently dropped.

### `POST /dashboard/widgets`

Saves which dashboard tiles are hidden.

**Body:** `csrf_token`, `hidden` — a JSON-encoded array of widget keys
currently hidden. Same validation as above.

### `POST /dashboard/width`

Saves which dashboard tiles are set to full width vs. column width.

**Body:** `csrf_token`, `wide` — a JSON-encoded array of widget keys
currently full-width. An explicitly empty array (`[]`) is meaningfully
different from omitting this call entirely — see `docs/database.md`'s
notes on `users.dashboard_layout` and `DashboardWidgets::resolveWide()`.

### `GET /health`

No authentication required. Used for uptime monitoring / deployment
health checks (see `docs/deployment.md`).

```json
{
  "status": "ok",
  "database": "ok",
  "timestamp": "2026-07-11T00:00:00Z"
}
```

`status`/`database` become `"degraded"`/`"unreachable"` if the database
connection fails, but the endpoint itself still returns `200` — a
monitoring check should look at the JSON body, not just the HTTP status
code.

### `GET /*` (unmatched route)

Any request that doesn't match a registered route returns:

```json
{"error": "Not found"}
```

with a `404` status.
