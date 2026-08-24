<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-side verification for Cloudflare Turnstile (the widget on the
 * public registration form — see resources/views/auth/register.php).
 * TURNSTILE_SECRET_KEY never leaves the server; only TURNSTILE_SITE_KEY
 * (public by design, same as any CAPTCHA site key) is embedded in the
 * page. Fails closed: unconfigured, unreachable, or malformed responses
 * from Cloudflare are all treated as "not verified" rather than silently
 * letting a submission through.
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function isConfigured(): bool
    {
        return !empty($_ENV['TURNSTILE_SITE_KEY']) && !empty($_ENV['TURNSTILE_SECRET_KEY']);
    }

    public static function siteKey(): string
    {
        return (string) ($_ENV['TURNSTILE_SITE_KEY'] ?? '');
    }

    public static function verify(string $responseToken, string $remoteIp): bool
    {
        if (!self::isConfigured()) {
            Logger::error('Turnstile::verify called but TURNSTILE_* is not configured — failing closed.');
            return false;
        }

        if ($responseToken === '') {
            return false;
        }

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => (string) $_ENV['TURNSTILE_SECRET_KEY'],
                'response' => $responseToken,
                'remoteip' => $remoteIp,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            Logger::error('Turnstile::verify request failed', ['error' => $curlError]);
            return false;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            Logger::error('Turnstile::verify got an unparseable response', ['body' => $body]);
            return false;
        }

        return ($decoded['success'] ?? false) === true;
    }
}
