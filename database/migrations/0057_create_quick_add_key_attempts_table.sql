-- Backs IP-based rate limiting on POST /quick-add/unlock
-- (App\Support\RateLimiter) — /quick-add/unlock is reachable by anyone
-- with no login at all (that's the whole point of the key), so it needs
-- its own throttle rather than borrowing login_attempts (a wrong guess
-- here isn't a login attempt against any particular email) or
-- registration_attempts (same reasoning as that table's own separation
-- from login_attempts — a distinct kind of attempt deserves its own
-- table rather than muddying another one's meaning).
CREATE TABLE quick_add_key_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_quick_add_key_attempts_ip (ip_address),
    KEY idx_quick_add_key_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
