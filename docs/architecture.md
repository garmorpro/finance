# Architecture

Plain PHP 8.1, no framework. A small number of hand-written pieces
(router, PDO wrapper, template renderer) stand in for what a framework
would otherwise provide, kept deliberately minimal so the whole request
lifecycle is easy to trace end to end.

## Layers

```
public/index.php     Entry point: bootstraps env/config, security headers,
                      session, registers every route, dispatches.
app/Controllers/      One class per resource (TransactionController,
                      BudgetController, ...). Reads the Request, calls
                      Repositories/Services, renders a View or redirects.
                      No SQL here.
app/Services/         Calculation/reporting logic that's more than a
                      single repository's CRUD: ReportingService,
                      GoalService, DebtService. Introduced in the Phase 8
                      pass to stop bcmath aggregation from spreading into
                      controllers as new report-like features were added.
app/Repositories/     One class per table (or closely related group of
                      tables). All SQL lives here, always via PDO
                      prepared statements, always household-scoped.
app/Http/             Request (reads $_GET/$_POST/$_FILES defensively —
                      nothing else in the app touches superglobals
                      directly), Response (JSON/HTML helpers), Router
                      (exact-match routes checked before {param} pattern
                      routes).
app/Middleware/       AuthMiddleware — session-based auth check and
                      role gating, called explicitly at the top of each
                      controller method (see docs/security.md's "Known
                      limitations" for the trade-off this implies).
app/Support/          Cross-cutting utilities: Csrf, View (template
                      rendering), Money/MoneyInput, CsvParser,
                      SecurityHeaders, RateLimiter, ErrorHandler,
                      DashboardWidgets (the dashboard tile registry).
app/Validation/       Input validation that's reused across controllers
                      (currently just MoneyInput).
app/Database/         Connection — a PDO singleton, real prepared
                      statements (ATTR_EMULATE_PREPARES => false).
resources/views/      Plain PHP templates, one directory per resource,
                      mirroring the Controllers directory. Shared partials
                      in resources/views/partials/ and
                      resources/views/dashboard/widgets/_header.php.
```

## Request lifecycle

1. `public/index.php` loads `.env`, applies security headers
   (`App\Support\SecurityHeaders`), configures and starts the session,
   registers every route as a closure calling into a Controller method.
2. `Router::dispatch()` checks exact-match routes first (a plain hash
   lookup), then `{param}` pattern routes in registration order. No
   route falls through to a 404 silently — `Response::notFound()` is
   explicit.
3. The matched controller method calls `AuthMiddleware::requireAuth()`
   (or `requireRole()`) as its first line, then reads input via
   `Request`, validates it, calls into one or more Repositories/Services,
   and either renders a view (`Response::html(View::render(...))`) or
   issues a redirect (`header('Location: ...')`).
4. Views are plain PHP (`App\Support\View::render()` does
   `extract() + require` inside an output buffer) — no template
   compilation step, no separate templating language to learn.

## Data-mutation pattern

Every write that touches money follows the same shape, established in
Phase 3 (accounts) and repeated for every feature since (transactions,
transfers, recurring "mark paid," goal contributions):

1. Controller opens a real PDO transaction (`Connection::get()->beginTransaction()`).
2. It calls whatever sequence of Repository methods the operation needs
   (e.g. for a transfer: two `TransactionRepository::create()` calls,
   two `linkTransferPair()` calls, two `AccountRepository::updateBalance()`
   calls).
3. On success, `commit()`. On any `Throwable`, `rollBack()` and show a
   generic "something went wrong" message — never a partial write.
4. A denormalized "current" value (`accounts.current_balance`,
   `financial_goals.current_amount`) is updated in the same transaction
   as the history row that explains *why* it changed
   (`account_balance_history`, `goal_contributions`) — the current value
   is always a fast read, and the history is always available for audit
   without re-deriving it from a full transaction scan.

This pattern is why `tests/DatabaseTestCase.php` tests at the Repository
layer instead of calling Controllers directly in the integration suite —
see `docs/testing.md` for the nested-transaction conflict that would
otherwise cause.

## Household scoping

There is no global "current household" object threaded through the
request. Every Repository method that touches household data takes
`$householdId` as an explicit parameter and includes it in the `WHERE`
clause of every query — not as an afterthought filter, but as part of
what makes a row findable at all. `AuthMiddleware::householdId()` reads
the authenticated user's household from session and every controller
passes that value in; a repository method is never called with a
client-supplied household ID. See `docs/security.md` and
`tests/Integration/HouseholdIsolationTest.php`.

## Frontend

Server-rendered HTML, Tailwind CSS compiled locally to a static
`public/assets/css/app.css` (no Node.js needed at runtime, only at build
time — see the README), a small amount of hand-written vanilla JS
(`public/assets/js/dashboard.js` for drag-to-reorder/resize/hide tiles,
`public/assets/js/charts.js` reading `<script type="application/json">`
data blobs to drive Chart.js). Chart.js itself is vendored as a static
file (`public/assets/js/vendor/chart.umd.min.js`) rather than loaded from
a CDN, consistent with this app's no-third-party-requests stance.

## Why plain PHP instead of a framework

This was a deliberate early decision, not an oversight: a self-hosted
single-app deployment on a home server doesn't need a framework's
routing/DI/ORM machinery, and keeping the stack to "PHP + PDO + a
50-line router" keeps the whole request path readable in one sitting.
The trade-off is that things a framework gives for free (route-level
auth middleware, CSRF middleware applied globally, a query builder) are
hand-rolled here and enforced by convention — see `docs/security.md`'s
"Known limitations" for where that trade-off currently shows.
