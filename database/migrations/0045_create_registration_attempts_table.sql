-- Backs IP-based rate limiting on public registration (App\Support\RateLimiter),
-- kept separate from login_attempts since a registration attempt isn't a
-- login attempt and mixing the two would muddy both tables' meaning.
CREATE TABLE registration_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_registration_attempts_ip (ip_address),
    KEY idx_registration_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
