<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Deliberately does NOT fall back to .env: a missing .env.testing means
// "no test database configured," which DatabaseTestCase treats as a
// reason to skip its tests, not a reason to silently point them at
// production. Pure unit tests (no DB) don't need this file at all.
if (file_exists(dirname(__DIR__) . '/.env.testing')) {
    Dotenv::createImmutable(dirname(__DIR__), '.env.testing')->safeLoad();
}
