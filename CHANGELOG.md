# Changelog

## [Unreleased]

- Phase 2b/2c (Password reset, profiles, invitations, roles): forgot/reset password flow (email stubbed — reset links are logged, not sent), user profile page (edit name/email, change password with current-password confirmation), household invitations (owner/administrator only, invite-by-email with role selection, 7-day expiring tokens), a working `/household` members page, and `AuthMiddleware::requireRole()` for role-gated actions. Adds an authenticated dashboard nav (Household, Profile, Log out). `password_reset_tokens` and `household_invitations` tables added.
- Phase 2a (Authentication core): login/logout with secure sessions (HttpOnly, SameSite=Lax, Secure over HTTPS, session ID regenerated on login), CSRF-protected login form, per-email/IP login rate limiting (5 attempts / 15 min), audit logging for auth events. Invite-only: no public registration route — first Owner + household are created via `bin/create-owner.php`. `audit_logs` and `login_attempts` tables added.
- Phase 1 (Project Foundation): PDO database connection, migration runner, base routing (`Request`/`Response`/`Router`), global error handling and file logging, `/health` check, PHPUnit setup.
- Core schema migrations: `users`, `households`, `household_members`.
- Initial repository scaffold: folder structure, `.gitignore`, `.env.example`, `composer.json`, README.
