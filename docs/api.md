# API

This is a server-rendered application, not an API-first one. There is no
`/api/*` REST surface yet — CLAUDE.md sketches one out
(`/api/accounts`, `/api/transactions`, etc.) as a possible future
direction if this app ever needs to support a mobile client or
automation, but building that out is a distinct, not-yet-started piece
of work, not something partially implemented today. This document covers
what actually exists: a handful of internal JSON endpoints the
dashboard's own JavaScript calls, plus the health check.

Everything else in the app is regular server-rendered HTML forms and
links — see the Controllers under `app/Controllers/` for the full route
list (registered in `public/index.php`).

## Conventions for the endpoints that do exist

- Every JSON endpoint requires an authenticated session
  (`AuthMiddleware::requireAuth()`) — there is no separate API
  authentication scheme (no API keys/tokens); it's the same
  cookie-based session as the rest of the app.
- Every JSON endpoint is `POST` and requires `csrf_token` in the request
  body, verified the same way as every other state-changing form in the
  app.
- Errors return `{"error": "<message>"}` with a non-2xx status. `419` is
  used for an expired/invalid CSRF token (matching Laravel's convention
  for the same case, chosen for familiarity, not because this app uses
  Laravel). `422` for invalid input.
- Success returns `{"status": "ok"}`.

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
