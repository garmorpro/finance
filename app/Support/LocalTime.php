<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every full timestamp in this app (created_at, last_used_at,
 * last_active_at, ...) is stored as plain UTC — "Y-m-d H:i:s", no
 * offset, via gmdate() throughout the repositories — which is right for
 * storage but not what a household actually wants to read: this app is
 * self-hosted and reached from wherever its members happen to be, so
 * "when did this happen" should read in the viewer's own local time,
 * not a fixed server time zone.
 *
 * html() emits a <time> element carrying the real UTC instant in its
 * `datetime` attribute (machine-readable, and a legible UTC-labeled
 * fallback for the first paint / no-JS case) — public/assets/js/
 * local-time.js then rewrites the visible text to the browser's own
 * current time zone on every page load. The same login reads in CDT
 * from Texas and MDT from Utah with nothing to configure, because it's
 * reading the device's own clock each time, not a stored preference.
 *
 * Deliberately NOT for transaction_date, or any other plain calendar
 * date (bill due dates, budget months, goal target dates) — those
 * represent a day, not an instant, and have no time-of-day to convert.
 * Running one through this method would be a correctness bug, not a
 * feature: a UTC-midnight date shifted into a time zone west of UTC can
 * land on the previous calendar day.
 */
final class LocalTime
{
    public static function html(?string $utcDatetime): string
    {
        if ($utcDatetime === null || $utcDatetime === '') {
            return '—';
        }

        $timestamp = strtotime($utcDatetime . ' UTC');
        if ($timestamp === false) {
            return View::e($utcDatetime);
        }

        $iso = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
        $fallback = gmdate('M j, Y, g:i A', $timestamp) . ' UTC';

        return '<time datetime="' . View::e($iso) . '">' . View::e($fallback) . '</time>';
    }
}
