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
- Two ways to get an account: public self-registration (creates a brand
  new household — see "Public registration" below) or accepting an
  invitation into an existing one (`household_invitations`, 7-day
  expiring tokens). `bin/create-owner.php` (CLI, direct server access)
  remains how *this* deployment's very first household got created and
  still works the same as before.
- Login is rate-limited: 5 attempts per 15 minutes, keyed by email *or*
  IP (`app/Support/RateLimiter.php`, backed by the `login_attempts`
  table) — enforced before credentials are even checked, not just
  logged after the fact.
- Session ID is regenerated on login (`session_regenerate_id(true)`) to
  prevent session fixation.
- Session cookies are `HttpOnly` always, `Secure` when `APP_URL` starts
  with `https://`, and `SameSite=Lax`.
- Password reset tokens are logged, not emailed. `App\Support\Mailer`
  (SMTP via PHPMailer) sends real email for budget reminders, household
  invitations, and registration email verification; password reset
  hasn't been switched over to it yet — see the README. Anyone with
  server log access can already reach the database directly, so this
  isn't a meaningfully larger trust boundary for a self-hosted
  single-server deployment.

## Public registration

`RegistrationController` (`/register`) lets anyone create a brand new
household without server access — the internet-facing counterpart to
`bin/create-owner.php`. This is a genuine trust-model change from
invite-only account *creation*; it does not change the isolation
boundary between households at all (see "Authorization" below and
`tests/Integration/HouseholdIsolationTest.php`'s
`test_registration_seeded_categories_are_isolated_between_households`).

- **Email verification is required before first login.**
  `users.email_verified_at` (present in the schema since the initial
  migration, unused until now) gates `AuthController::login()` —
  registering creates the account and household immediately but does
  *not* establish a session; only clicking the emailed link
  (`GET /verify-email?token=...`) does, via the same
  `AuthController::completeLogin()` every other login path uses.
  Migration `0043` backfilled every account that existed before this
  gate as verified, so no existing household was locked out when this
  shipped. Accounts created via `bin/create-owner.php` or an accepted
  invitation are marked verified immediately at creation — both already
  prove control of the email address a different way (direct server
  access; clicking a link mailed to that address).
- **Verification tokens**: `random_bytes(32)`, only the SHA-256 hash
  stored (`email_verification_tokens.token_hash`), 24-hour expiry, same
  pattern as password reset tokens. `/verify-email/resend` re-issues one
  and always shows the same neutral message regardless of whether the
  email exists or is already verified — same account-enumeration
  reasoning as password reset.
- **Registration is rate-limited by IP**: 3 attempts per hour
  (`RateLimiter::tooManyRegistrationAttempts()`, backed by its own
  `registration_attempts` table — deliberately separate from
  `login_attempts`, since account creation is a heavier, harder-to-undo
  action than a login attempt and shouldn't share that table's meaning
  or its looser allowance).
- **Cloudflare Turnstile** (`App\Support\Turnstile`) is optional bot
  protection on top of the IP rate limit — verified server-side against
  Cloudflare's `siteverify` API using a secret key that never leaves
  `.env`. Fails closed on any error (unconfigured, network failure,
  malformed response all count as "not verified"), but registration
  itself only *enforces* the check when `TURNSTILE_*` is actually
  configured — left blank, signups still work, just without this layer.
  The one third-party script this app loads: `challenges.cloudflare.com`,
  only on the registration page.
- **Password policy, email format, and password-confirmation checks**
  are identical to `bin/create-owner.php` and accepting an invitation —
  same `App\Validation\PasswordPolicy` (12-character minimum), same
  `hash_equals()` password-confirmation comparison.
- **Account creation is one transaction**: user row, household row,
  owner membership, and default category seeding either all succeed or
  all roll back together (`Connection::get()->beginTransaction()`,
  mirroring `bin/create-owner.php`'s own already-transactional
  approach) — no path leaves a user row with no household, or a
  household with no owner.

## Two-factor authentication

Optional per-user, TOTP (RFC 6238 — the algorithm behind Google
Authenticator, Authy, 1Password, etc.), enabled from Settings → Security.

- `App\Support\Totp` implements the algorithm directly (HMAC-SHA1,
  30-second period, 6 digits) rather than adding a Composer dependency
  for it — it's straightforward math, verified against the RFC 6238
  reference test vector during development.
- No QR code is generated (that would need an image-generation
  dependency this app doesn't otherwise need); the setup page shows the
  secret as text for manual entry, which every mainstream authenticator
  app supports.
- The secret is held only in the session during setup
  (`$_SESSION['pending_totp_secret']`) and never written to the database
  until the user proves their app is generating matching codes — nobody
  can end up "enrolled" in 2FA with a secret they never actually
  configured.
- Login becomes two steps once enabled: a correct password sets only a
  `pending_2fa_user_id` session marker (session ID regenerated, but
  `AuthMiddleware::check()` looks at `user_id` specifically, which isn't
  set yet) and redirects to `/login/verify`; only a correct code (or
  recovery code) completes the login and establishes the real session,
  with its own second `session_regenerate_id(true)`.
- The 2FA code step is rate-limited the same way the password step is
  (`RateLimiter`, keyed by email/IP, backed by the same `login_attempts`
  table) — a stolen password alone doesn't get unlimited guesses at the
  6-digit code.
- 8 single-use recovery codes are generated when 2FA is enabled, shown
  once (`$_SESSION['_flash_recovery_codes']`, cleared immediately after
  that one render), and stored hashed (`password_hash()`, same as the
  account password) — never in plaintext at rest.
- Turning 2FA off requires the current password, per CLAUDE.md's
  "critical account actions should require password confirmation" —
  a logged-in-but-unattended session can't silently disable it.
- The household-invitation acceptance flow (`HouseholdController`)
  establishes a session directly rather than going through
  `AuthController::login()`, which looks like a 2FA bypass at a glance —
  it isn't one, since that flow only ever creates a brand-new user
  account, which by definition has no 2FA configured yet.

## Passkeys (WebAuthn)

Optional per-user passkey sign-in (Face ID, Touch ID, Windows Hello, or
a platform's own screen lock), registered from Settings → Security and
offered as a "Sign in with a passkey" option on the login page.

- Built on `web-auth/webauthn-lib` rather than a hand-rolled
  implementation — unlike TOTP's straightforward HMAC math, WebAuthn's
  CBOR/COSE-encoded attestation and assertion objects are genuinely
  easy to get subtly wrong in ways that matter for security, and this
  is a mature, widely-used library for exactly that reason. All of the
  app's library usage is isolated in `App\Services\WebAuthnService`.
- Attestation is always requested as `none` — this app has no fleet
  policy that cares which authenticator model was used, only a privacy
  cost to verifying an attestation chain (it can fingerprint hardware).
- Registration requires a *resident/discoverable* credential
  (`residentKey: required`) and `userVerification: required`. Resident
  keys are what let the login page offer a passkey option without
  asking for an email address first — the browser's own passkey picker
  enumerates whichever credentials are registered for this site, and
  the assertion response's `userHandle` (this app's own user id) tells
  the server who just authenticated. `userVerification: required` means
  the platform must actually enforce Face ID/Touch ID/a PIN, not merely
  confirm the authenticator is present.
- The relying party ID is derived from `APP_URL`'s host — every
  registered passkey is permanently tied to that exact string, so it
  must never change once passkeys exist (would invalidate every one).
- A passkey satisfies two-factor authentication on its own by default
  (`users.webauthn_skip_two_factor`, defaults on) — combining "something
  you have" (the device) with "something you are" (biometric) or
  "something you know" (device PIN) is the standard justification for
  treating it as MFA-equivalent, and requiring a *second* TOTP prompt
  after Face ID would defeat the point of it existing. A user can flip
  that column off if they specifically want both.
- Registration and authentication challenges are single-use, stored
  server-side in `$_SESSION` between the options request and the
  verification request, and never accepted from anywhere else — the
  browser can't replay an old challenge or supply its own.
- `WebAuthnCredentialRepository::findOneByCredentialId()` looks up by a
  SHA-256 hash column rather than the raw (TEXT, variably-sized)
  credential ID, since MySQL can't put a plain `UNIQUE` index on TEXT
  without an awkward prefix length.
- Removing a passkey (Settings → Security) doesn't require password
  confirmation the way disabling 2FA does — it's not disabling a
  control, just removing one sign-in method while password (and TOTP,
  if enabled and not skipped) remain fully intact.

## Active session management

Settings → Security lists every device currently signed in and can log
any of them out individually or all at once.

- PHP's native file-based session storage has no concept of listing or
  revoking a session from outside the browser that holds it — the new
  `user_sessions` table tracks each one (a rough browser/OS label
  parsed from the user agent, IP, last-active time) so this is possible
  at all.
- `AuthMiddleware::check()` re-verifies the current request's session
  against this table on *every* authenticated page load. A session
  whose row is marked `revoked_at` is force-logged-out
  (`$_SESSION = []; session_destroy();`) the next time it loads
  anything — there's no way to keep using a revoked session by not
  refreshing, since every protected page re-checks.
- A session with **no** tracked row at all — every session that existed
  before this feature was deployed — is treated as valid and backfilled
  into the table on the spot, rather than force-logged-out. The
  alternative (requiring a row to exist) would silently sign out every
  already-logged-in household member the moment this migration runs.
  Once a row exists for a session, from then on an explicit revocation
  does take effect.
- `last_active_at` is only written roughly once a minute per session
  (checked in PHP against the previously-fetched row, not a separate
  query), not on every single request, to keep this cheap.
- Revoking a session only ever takes effect for *future* requests from
  that browser — there's no server-push mechanism to force an
  already-open tab to react instantly. This is the same limitation
  essentially every non-realtime web app has.

## Authorization

- Every controller method (except the intentionally public `AuthController`,
  `PasswordResetController`, and `RegistrationController` routes) calls
  `AuthMiddleware::requireAuth()`. This is enforced by convention,
  per-method, not by a router-level guard — see "Known limitations"
  below.
- **Public registration doesn't change this boundary.** Anyone can now
  create a household without server access (see "Public registration"
  below), but the household that creates still goes through the exact
  same `household_id`-scoped repositories as one created any other
  way — opening account *creation* to the public is a different thing
  from opening *data access* between households, and only the former
  changed.
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

## Encryption at rest

Household-scoping is the boundary against *another household's* data
(above). This is a different, narrower protection: against someone who
legitimately reaches the database itself — phpMyAdmin, a `mysql`
shell, a stolen DB-only backup — seeing what's *in* it. See "Known
limitations" below for exactly what this does and doesn't cover; this
section is about how the covered part works.

- **What's encrypted**: `accounts.name`/`institution_name`/`notes`,
  `financial_goals.name`/`description`, `recurring_items.name`/`notes`
  (phase 1). Chosen because none of them are searched via SQL `LIKE`
  anywhere in the app (confirmed by grep before this shipped, not
  assumed) — small per-household lists that were already loaded in full
  and filtered in PHP where needed. Phase 2:
  `accounts.current_balance`/`available_balance`/`credit_limit`/
  `minimum_payment`/`original_balance`, and
  `account_balance_history.previous_balance`/`new_balance` — real
  dollar amounts, not display text, but the same "never SQL-aggregated,
  always loaded into PHP first" property held here too (confirmed by
  grep, same as phase 1 — `AccountRepository::netWorthSummary()` and
  `DebtService`'s totals already summed with bcmath in PHP after a full
  row fetch, never `SUM()` in SQL). `interest_rate` and
  `payment_due_day` are deliberately excluded — a percentage and a
  scheduling detail, not currency amounts.
  `transactions.amount`/`payee`/`notes` are deliberately *not*
  encrypted — `amount` genuinely is `SUM()`'d in real SQL throughout
  this app (`TransactionRepository::sumsForHousehold()`,
  `ReportingService`), and `payee`/`notes` back the Transactions page's
  SQL `LIKE` search — see "Known limitations".
- **`App\Support\FieldCipher`**: `sodium_crypto_secretbox`
  (XSalsa20-Poly1305, authenticated) — built into PHP's core libsodium
  extension, no Composer dependency. A fresh random nonce every call
  (stored immediately before the ciphertext in one binary blob, so two
  encryptions of the identical value never produce the same stored
  bytes), and `ENCRYPTION_KEY` (`.env`, base64-encoded, generated once)
  is the only thing that can decrypt it. A wrong key, corrupted bytes,
  or a missing key all make `decrypt()`/`encrypt()` throw — this never
  silently falls back to storing plaintext, or returns garbled bytes
  that could be mistaken for a real value.
- **Staged rollout, not an in-place column swap**: each affected table
  got new nullable `*_encrypted` columns (migrations `0046`-`0048` for
  phase 1, `0049`-`0050` for phase 2) alongside the original plaintext
  ones, which stay in the schema as a read-only fallback — every read
  prefers the encrypted column but falls back to the plaintext one for
  any row not yet backfilled (`bin/encrypt-existing-text-fields.php`
  for phase 1, `bin/encrypt-existing-balance-fields.php` for phase 2 —
  two separate scripts so the higher-stakes balance pass can be run,
  reviewed, and rolled back independently of the text one — both
  idempotent, dry-run by default), so deploying the code and running
  the backfill are safe in either order. Every write
  (`create()`/`update()`/`updateBalance()`/
  `AccountBalanceHistoryRepository::record()`) goes only to the
  encrypted column and explicitly clears the old plaintext one to
  `NULL` — plaintext stops accumulating immediately, without waiting
  for the backfill. The old columns aren't dropped in this pass; that's
  a deliberate later, separate migration once the new columns have run
  in production without issue.
- **Every SQL `JOIN` that used to read `accounts.name` directly for
  display** (transaction lists, CSV export, imports history, goals'
  linked-account name, recurring items, the Reports page's "group by
  account") now also selects `name_encrypted` alongside it and decrypts
  in PHP after the fetch — found by grepping for every such JOIN before
  writing any encryption code, not discovered by something breaking
  afterward. No repository or service reads `accounts`' balance columns
  through a `JOIN` the same way — confirmed by the same grep sweep —
  every one of them already goes through `AccountRepository`, so
  extending its own decryption covered every caller with no other files
  needing changes.
- **`account_balance_history` is encrypted too, not just
  `accounts.current_balance` itself.** It's a permanent, ever-growing
  audit trail of every balance ever set on every account — left alone,
  it would have kept every historical balance fully readable even after
  the current one was encrypted, the same "hidden duplicate copy"
  problem as `import_rows.raw_data` would have been for phase 1's text
  fields if that table held these values (it doesn't).
- **Audit log metadata never carries these fields.** `account.created`/
  `goal.created`/`recurring.created` used to log the new record's name
  into `audit_logs.metadata` as plain JSON, and `account.balance_adjusted`
  logged the exact before/after balance — encrypting the column and
  then logging its own plaintext value elsewhere would have defeated
  the point. Those log calls now omit the value entirely; `entity_id`
  already identifies exactly which record it was, and
  `account_balance_history` (also encrypted) already keeps the real
  before/after trail for balance changes specifically.
- **Deployment order matters here in a way it doesn't for this app's
  other optional features.** `ENCRYPTION_KEY` is not gracefully
  optional the way `MAIL_*`/`TURNSTILE_*` are — `AccountRepository`,
  `AccountBalanceHistoryRepository`, `GoalRepository`, and
  `RecurringItemRepository` encrypt unconditionally and throw without
  it, so creating or editing any account, goal, or recurring item, or
  adjusting any balance, fails outright, not just "isn't encrypted
  yet," if this ships before the key is set. See `docs/deployment.md`'s
  "Deploying encryption at rest" section for the exact required order.
- **Losing `ENCRYPTION_KEY` is unrecoverable** — every encrypted value
  becomes permanently unreadable, with no reset path like a forgotten
  password. Back it up somewhere other than alongside database backups,
  same reasoning as `BACKUP_ENCRYPTION_PASSPHRASE`, but higher stakes.

## Budget review magic links

The budget planning reminder email (`bin/send-budget-reminders.php`,
Settings → Household) carries a link that signs someone in without a
password — the one deliberate exception to this app's normal
session-cookie authentication, so it gets its own write-up:

- **Token**: `random_bytes(32)` (256 bits), only the SHA-256 hash is
  stored (`budget_review_links.token_hash`) — same pattern as password
  reset and invitation tokens. The plaintext token only ever exists in
  the generated URL and the email itself (real, via `App\Support\Mailer`,
  when `MAIL_*` is configured; otherwise logged instead — see
  "Authentication" above). Either way it's never persisted anywhere but
  that one outbound message and the recipient's inbox.
- **Scope, not a general sign-in**: opening a valid link
  (`BudgetReviewLinkController::open()`) never sets
  `$_SESSION['user_id']`/`'household_id'`/`'role'` — the fields
  `AuthMiddleware::check()` looks for. It sets distinct
  `$_SESSION['review_link_*']` keys instead, which only
  `BudgetController::resolveReviewAccess()` understands, and only for
  the review/save/copy-previous actions. Every other page in the app
  still sees "not signed in" for that session. Access is hard-locked to
  the exact household, user, and month the link was issued for — the
  period month can't be swapped via `?month=` or a tampered form field.
- **Consumption is atomic**: `BudgetReviewLinkRepository::consume()` is
  a single `UPDATE ... WHERE expires_at > NOW() AND (single_use = 0 OR
  used_count = 0)` — the same statement that re-validates also spends
  the link, so two near-simultaneous requests for one single-use link
  can't both succeed.
- **Re-verified, not just trusted, on every request**: both when a link
  is opened and on every subsequent request during a scoped session,
  the recipient's household role is re-checked against
  `household_members` live. A link issued while someone was an
  Administrator stops working immediately if they're later demoted or
  removed — it doesn't keep working until it expires.
- **Wrong-user protection**: if a link is opened on a device already
  signed in as a *different* household member, it's refused outright
  (`review-link-invalid` view, HTTP 409) rather than silently swapping
  that browser's session to the link's identity — and the link is left
  unspent, since this wasn't a legitimate attempt to use it. Opened
  while already signed in as its own intended recipient, the link is
  spent and just deep-links into the right month using that person's
  real session and real permissions.
- **Household-configurable, not hardcoded**: whether the reminder is on,
  how many days before the month starts it fires, whether a link is
  single-use or reusable until expiry, and how long it lives (default 7
  days) are all per-household settings
  (`households.budget_reminder_*`), editable only by Owner/Administrator.
- **Mail transport**: `App\Support\Mailer` connects over SMTP with
  `SMTPSecure = ENCRYPTION_STARTTLS` (never plaintext). Credentials come
  only from `.env` (`MAIL_*`), never committed, and `.env.example`
  documents using an app-specific password (e.g. Gmail's 2-Step
  Verification App Passwords), not a real account password. A send
  failure never surfaces to the person who triggered it (there isn't
  one — this is a cron job) or blocks the link from working; it's only
  logged, and the link itself was already issued and already usable
  before the send was attempted.

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
  default, with `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`,
  and (new) an explicit `frame-src 'none'` — this app embeds nothing in
  an iframe anywhere, so there's no reason to fall back to `default-src`
  for that directive either.
- `challenges.cloudflare.com` (Cloudflare Turnstile — see "Public
  registration" above) is allowed in `script-src`/`connect-src`/`frame-src`,
  but *only* when the request path is `/register`
  (`SecurityHeaders::apply()`'s `$allowTurnstile` param, set in
  `public/index.php` before the router even runs) — every other page's
  CSP is unaffected, rather than widening every page's policy for a
  script only one page ever loads.

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
- **No automated dependency vulnerability scanning** (e.g. `composer audit`
  in CI) — this app has very few dependencies (`vlucas/phpdotenv`,
  `phpunit/phpunit` dev-only), which limits exposure, but nothing
  currently re-checks that automatically as new CVEs are disclosed.
- **No security-focused automated tests for the HTTP/session layer**
  (see `docs/testing.md`'s "not covered" section) — `AuthMiddleware::requireRole()`
  calls `exit()` directly, which isn't unit-testable without a refactor
  that was out of scope for this pass.
- **Dashboard tile drag-to-reorder has no keyboard equivalent.** Every
  other dashboard control (resize, show/hide, the customize dialog) is
  fully keyboard-operable; reordering tiles is mouse/touch-only. Found
  during the accessibility pass, not fixed in it — a real fix needs
  keyboard-driven "move up"/"move down" controls per tile, not just an
  ARIA annotation on the existing drag handle.
- **`transactions.amount`/`payee`/`notes` are not encrypted at rest.**
  Everything on `accounts` and `account_balance_history` now is (see
  "Encryption at rest" below), plus `financial_goals`/`recurring_items`'
  name/description/notes — but `transactions.amount` genuinely is
  `SUM()`'d in real SQL throughout the app
  (`TransactionRepository::sumsForHousehold()`, budget/report rollups),
  which encryption would break outright, not just slow down.
  `payee`/`notes` back the Transactions page's SQL `LIKE` search, and
  encrypting them means redesigning that search first
  (fetch-and-filter-in-PHP after every other SQL filter narrows the
  set), a deliberate separate follow-up, not done here. Someone with
  database-only access (phpMyAdmin, a `mysql` shell, a raw backup) can
  still see every transaction's amount and payee, and which household
  each belongs to, even though account balances themselves are now
  encrypted — encryption narrows what they see, it doesn't close the
  boundary. *Who* can reach the database at all (`docs/deployment.md`'s
  "Lock down phpMyAdmin" step, least-privilege DB credentials above) is
  still the load-bearing control for everything not yet encrypted.
