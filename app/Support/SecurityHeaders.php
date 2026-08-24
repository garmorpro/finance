<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Applies a baseline set of security response headers to every request.
 * See docs/security.md for the reasoning behind each one and the CSP
 * trade-off this app currently makes (script-src/style-src allow
 * 'unsafe-inline' because onsubmit="" confirm() dialogs and dynamic
 * inline `style="width: X%"` progress bars are used throughout the
 * views — a stricter CSP would need those moved to external JS/CSS
 * first, which is a larger follow-up refactor, not part of this pass).
 *
 * Does NOT redirect HTTP to HTTPS. This app is deployed behind a
 * Cloudflare Tunnel, so the origin server only ever sees plain HTTP from
 * the tunnel regardless of what the visitor's browser used — redirecting
 * based on $_SERVER['HTTPS'] here would either do nothing (if Cloudflare
 * already enforces HTTPS at the edge, which it should) or, if that
 * detection is ever wrong, create a redirect loop. HTTPS enforcement for
 * this deployment belongs in the Cloudflare dashboard's "Always Use
 * HTTPS" setting, not in application code.
 */
final class SecurityHeaders
{
    /**
     * $allowTurnstile loosens CSP just enough for Cloudflare Turnstile
     * (script, its challenge iframe, and its background requests) —
     * public/index.php passes this only for the registration page,
     * the one place Turnstile's widget can appear
     * (resources/views/auth/register.php), rather than widening every
     * page's CSP for a script only one page ever loads.
     */
    public static function apply(bool $isHttps, bool $allowTurnstile = false): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: " . self::contentSecurityPolicy($allowTurnstile));

        // Only asserted when the request is actually confirmed HTTPS —
        // an HSTS header sent over a plain HTTP response is meaningless
        // and, if HTTPS were ever misconfigured, could make the app
        // unreachable until the header expires from browsers' caches.
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private static function contentSecurityPolicy(bool $allowTurnstile): string
    {
        $turnstileOrigin = 'https://challenges.cloudflare.com';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'" . ($allowTurnstile ? " {$turnstileOrigin}" : ''),
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'" . ($allowTurnstile ? " {$turnstileOrigin}" : ''),
            "frame-src" . ($allowTurnstile ? " {$turnstileOrigin}" : " 'none'"),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
