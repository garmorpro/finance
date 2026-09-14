<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Same "is this HTTPS" check public/index.php's bootstrap already does
 * for session_set_cookie_params()/SecurityHeaders — off the configured
 * APP_URL, not $_SERVER['HTTPS'], since this app sits behind a reverse
 * proxy and that header isn't reliably set there (see the warning in
 * SecurityHeaders.php). Pulled out here because setting the Quick Add
 * key cookie (App\Controllers\TransactionController) needs the same
 * check from inside a controller, not just once at bootstrap time.
 */
final class Env
{
    public static function isHttps(): bool
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';

        return str_starts_with((string) $config['url'], 'https://');
    }
}
