# Security

This app handles real household financial data, so security decisions
are documented here rather than left implicit in the code. This is a
description of what's actually implemented, not a checklist of
intentions — if something below stops being true, fix the code or fix
this doc, not just one of them.

## Authentication

- Passwords are hashed with `password_hash(..., PASSWORD_DEFAULT)`
  (bcrypt) and checked with `password_verify()` — never compared or
  stored in plaintext.
- No public registration route. The first Owner and household are
  created via `bin/create-owner.php`; everyone after that joins by
  invitation (`household_invitations`, 7-day expiring tokens).
- Login is rate-limited: 5 attempts per 15 minutes, keyed by email *or*
  IP (`app/Support/RateLimiter.php`, backed by the `login_attempts`
  table) — enforced before credentials are even checked, not just
  logged after the fact.
- Session ID is regenerated on login (`session_regenerate_id(true)`) to
  prevent session fixation.
- Session cookies are `HttpOnly` always, `Secure` when `APP_URL` starts
  with `https://`, and `SameSite=Lax`.
- Password reset tokens are logged, not emailed (this app has no SMTP
  integration by design — see the README). Anyone with server log access
  can already reach the database directly, so this isn't a meaningfully
  larger trust boundary for a self-hosted single-server deployment.
- Two-factor authentication is not implemented. Noted in CLAUDE.md as a
  possible later phase, not done yet.

## Authorization

- Every controller method (except the intentionally public `AuthController`
  and `PasswordResetController` routes) calls `AuthMiddleware::requireAuth()`.
  This is enforced by convention, per-method, not by a router-level
  guard — see "Known limitations" below.
- **Every record-level lookup is household-scoped.** Repository methods
  that read or write app data take `$householdId` explicitly and filter
  by it (e.g. `GoalRepository::findById(int $goalId, int $householdId)`).
  This is the actual authorization boundary for "can this user see this
  record" — not a check performed in the controller after the fact, but
  baked into the query itself. `tests/Integration/HouseholdIsolationTest.php`
  verifies this directly, including the IDOR case of guessing a valid
  sequential ID that belongs to a different household.
- Role-gated actions (e.g. only Owner/Administrator can edit a budget)
  use `AuthMiddleware::requireRole([...])`, checked against the role
  cached in session at login.

## CSRF

`app/Support/Csrf.php` — a `random_bytes(32)` token stored in session,
compared with `hash_equals()` (constant-time, avoids timing attacks).
Verified on every state-changing (`POST`) route. GET-only routes (reports,
cash flow, debt overview) correctly have no CSRF check, since they don't
mutate anything.

## SQL injection

Every query goes through PDO prepared statements
(`PDO::ATTR_EMULATE_PREPARES => false` — real server-side prepared
statements, not client-side string interpolation). The one recurring
gotcha this produced: the same named placeholder can't be bound twice in
one query under real prepares, which surfaced as a bug twice during
development (duplicate `:now`, duplicate `:search`) before becoming a
known pattern to watch for in review.

Dynamic `IN (...)` clauses (used in the Reports filters) build a
distinctly-named placeholder per value rather than interpolating a list
directly — see `ReportingService::buildInClause()`.

## XSS

Output escaping goes through `View::e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`)
at every point user-supplied data is rendered into HTML. Chart data
embedded as JSON inside `<script type="application/json">` tags uses
`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` specifically
so a category or payee name containing `</script>` can't break out of the
tag.

## Security headers

`app/Support/SecurityHeaders.php`, applied to every response:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY` and `Content-Security-Policy: frame-ancestors 'none'`
  (this app has no legitimate reason to ever be iframed)
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` — only sent when the request is confirmed
  HTTPS (via `APP_URL`), never on plain HTTP
- A `Content-Security-Policy` restricting everything to `'self'` by
  default, with `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`

**Known trade-off:** the CSP's `script-src` and `style-src` both include
`'unsafe-inline'`. This app uses `onsubmit="return confirm(...)"` inline
event handlers throughout (delete/archive/cancel confirmations) and
dynamic `style="width: X%"` attributes for progress bars, both of which a
strict CSP would silently break. Removing `'unsafe-inline'` would require
moving every inline confirm dialog to an external script with
`addEventListener` (the dashboard's per-tile resize toggle in
`public/assets/js/dashboard.js` is the pattern already used for new code)
and every dynamic inline style to a CSS custom property. That's a real,
bounded refactor — worth doing, not done in this pass. Until then, the
CSP still blocks the thing it matters most for: an XSS payload that tries
to inject a `<script src="https://attacker.example/x.js">` tag or load
resources from an external origin.

**HTTPS enforcement**: no HTTP→HTTPS redirect is implemented in
application code. This app is deployed behind a Cloudflare Tunnel — the
origin server only ever sees plain HTTP from the tunnel regardless of
what the browser used, so a redirect based on `$_SERVER['HTTPS']` here
would either be a no-op (if Cloudflare already enforces HTTPS at the
edge, which it should) or, if that assumption is ever wrong, an infinite
redirect loop that takes the app down. Enforce HTTPS in the Cloudflare
dashboard ("Always Use HTTPS" for the domain), not here. A deployment
that terminates TLS directly on the origin server instead would need
this reconsidered.

## File uploads (CSV import)

- The client-supplied filename is never trusted for storage — files are
  written under a `bin2hex(random_bytes(16))` token name, which also
  rules out path traversal via a crafted filename.
- Storage (`storage/imports/`) is outside the web root (`public/`), so
  even a file that somehow contained executable content couldn't be
  requested directly by URL.
- Size is capped at 5MB, enforced server-side independent of
  `upload_max_filesize`/`post_max_size`.
- Both the file extension (`.csv`) and the actual detected MIME type
  (`mime_content_type()`, against a permissive whitelist since CSV has
  no reliable magic-byte signature) are checked before the file is
  parsed — the extension alone is trivially spoofed.
- Files are deleted after a successful import, and on most failure
  paths. **Known gap**: a user who uploads a file and abandons the
  import flow before confirming leaves an orphaned temp file with no
  automatic cleanup. A periodic `find storage/imports -mtime +1 -delete`
  cron (or equivalent TTL sweep) would close this; not implemented yet.

## File uploads (transaction attachments)

- Same random-token storage-path pattern as CSV import (`storage/attachments/`,
  outside the web root), but a tighter whitelist since a receipt is only
  ever an image or a PDF: `image/jpeg`, `image/png`, `image/webp`,
  `application/pdf` — checked via `mime_content_type()`, not the
  client-supplied extension.
- The stored file's extension is chosen by the server from that
  whitelist (`AttachmentController::ALLOWED_MIME_TYPES`), never taken
  from the client's filename — an upload named `receipt.jpg.php`
  detected as `image/jpeg` is stored as `<random>.jpg`, full stop.
- Size is capped at 10MB, enforced server-side independent of
  `upload_max_filesize`/`post_max_size`.
- Every read (`download`, `destroy`) re-verifies the parent transaction
  belongs to the authenticated user's household before touching the
  attachment row or the file on disk — the attachments table has no
  `household_id` of its own (it's a pure child of a household-scoped
  transaction, like `transaction_splits`), so this check is the actual
  authorization boundary, not a nice-to-have.
- Downloads are served through `AttachmentController::download()` with
  `Content-Disposition: inline` and the *original* (user-supplied,
  HTML-escaped in the header via `rawurlencode()`) filename — the file
  itself is never reachable by a guessable or direct URL, since
  `storage/attachments/` sits outside `public/`.

## Currency and financial calculations

All monetary columns are `DECIMAL`, never `FLOAT`/`DOUBLE`. All
arithmetic on money goes through `bcmath` (`bcadd`/`bcsub`/`bcmul`/`bcdiv`/`bccomp`)
— binary floating point is never used for a value that represents or
derives from currency. `tests/RoundingTest.php` demonstrates the specific
failure mode this avoids (`0.1 + 0.2 !== 0.3` in float arithmetic, and
the drift that compounds across many transactions).

## Known limitations

- **Auth is enforced per-controller-method, not at the router level.**
  Every current route correctly calls `AuthMiddleware::requireAuth()`,
  but nothing stops a new route from being added without it — it's a
  convention, not a structural guarantee. A router-level default-deny
  guard (explicitly allow-listing the few public routes) would close
  this and is a reasonable follow-up.
- **No two-factor authentication.**
- **No automated dependency vulnerability scanning** (e.g. `composer audit`
  in CI) — this app has very few dependencies (`vlucas/phpdotenv`,
  `phpunit/phpunit` dev-only), which limits exposure, but nothing
  currently re-checks that automatically as new CVEs are disclosed.
- **No security-focused automated tests for the HTTP/session layer**
  (see `docs/testing.md`'s "not covered" section) — `AuthMiddleware::requireRole()`
  calls `exit()` directly, which isn't unit-testable without a refactor
  that was out of scope for this pass.
