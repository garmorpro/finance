<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A rough "which browser/device is this" label for the active-sessions
 * list — a handful of keyword checks, not a full user-agent-parsing
 * library. Good enough to tell your phone apart from your laptop; not
 * meant to be authoritative.
 */
final class UserAgent
{
    public static function describe(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'Unknown device';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') && !str_contains($userAgent, 'Chromium') => 'Chrome',
            str_contains($userAgent, 'Safari/') && !str_contains($userAgent, 'Chrome') => 'Safari',
            default => 'Browser',
        };

        $device = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') && str_contains($userAgent, 'Mobile') => 'Android phone',
            str_contains($userAgent, 'Android') => 'Android tablet',
            str_contains($userAgent, 'Mac OS X') => 'Mac',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $device !== null ? "{$browser} on {$device}" : $browser;
    }

    /**
     * A passkey's device_name is always derived from describe() above —
     * the browser/OS user agent — which can't actually tell a hardware
     * security key apart from that same device's own Face ID/Touch ID:
     * both register from the same browser, so both would otherwise show
     * up identically (e.g. "Safari on Mac"). The one thing that *does*
     * differ is which transports the authenticator itself reported
     * during registration (WebAuthnCredentialRepository::create()) —
     * 'usb'/'nfc'/'ble' means a separate physical key was used, 'internal'
     * means the device's own built-in authenticator. Returns null for a
     * platform authenticator or when transports isn't reported at all
     * (older browsers, or a resident key that legitimately answered with
     * an empty list) — describe()'s existing label already covers that
     * case well enough on its own.
     *
     * @param list<string> $transports
     */
    public static function authenticatorType(array $transports): ?string
    {
        if (in_array('internal', $transports, true)) {
            return null;
        }

        $roaming = ['usb', 'nfc', 'ble'];
        if (array_intersect($roaming, $transports) !== []) {
            return 'Security key';
        }

        return null;
    }
}
