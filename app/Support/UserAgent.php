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
}
