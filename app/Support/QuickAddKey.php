<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Quick Add key (Settings > Profile) lets a device skip login
 * entirely for the narrow /quick-add page and its own POST endpoint —
 * see docs/security.md's "Quick Add key" section for the full threat
 * model. Hashed with a fast, deterministic hash (SHA-256) rather than
 * password_hash()/bcrypt on purpose: unlocking has to look up *which*
 * user a pasted key belongs to by exact hash match (there's no other
 * context to search by), and bcrypt's per-call random salt makes that
 * kind of lookup impossible. Safe specifically because the key itself
 * is a long random secret with real entropy, unlike a human password —
 * this is exactly how this codebase already treats
 * webauthn_credentials.credential_id_hash, and for the same reason.
 */
final class QuickAddKey
{
    /**
     * 20 random bytes (160 bits) formatted as 8 dash-separated groups —
     * comfortably enough entropy that an unrate-limited SHA-256 lookup
     * attack is still infeasible; RateLimiter's throttle on
     * /quick-add/unlock is defense in depth on top of this, not the
     * only thing standing between a guesser and a match.
     */
    public static function generate(): string
    {
        $raw = strtoupper(bin2hex(random_bytes(20)));

        return implode('-', str_split($raw, 5));
    }

    /**
     * Strips the formatting dashes/whitespace a pasted or hand-typed key
     * might carry and normalizes case, so "ab12c-3d4e5" and
     * "AB12C3D4E5" both hash identically to generate()'s own output.
     */
    public static function normalize(string $key): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $key) ?? '');
    }

    public static function hash(string $key): string
    {
        return hash('sha256', self::normalize($key));
    }
}
