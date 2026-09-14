<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates a user-influenced "send me back here" path before it's ever
 * put in a `Location:` header — a `redirect_to`/`intended_url` value is
 * attacker-controlled input (it rides in on a request), so accepting it
 * unchecked would be an open-redirect vulnerability: an absolute URL, a
 * protocol-relative "//host/..." (browsers treat that as a different
 * host), or a `javascript:`/`data:` scheme could send a logged-in user
 * somewhere other than this app right after authenticating.
 *
 * Originally TransactionController::safeRedirectPath() only, extracted
 * here once AuthMiddleware::requireAuth()/AuthController::completeLogin()
 * needed the identical check for "return to the page you were trying to
 * reach" after a login redirect.
 */
final class SafeRedirect
{
    public static function path(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('#^/(?!/)[A-Za-z0-9\-_/?=&.]*$#', $path) !== 1) {
            return null;
        }

        return $path;
    }
}
