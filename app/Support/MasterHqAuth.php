<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Request;

/**
 * Server-to-server auth for the Master HQ read-only API
 * (`GET /api/master-hq/v1/summary`) — a single static bearer token in
 * `MASTER_HQ_API_TOKEN`, compared with `hash_equals()`. This app has no
 * other server-to-server auth pattern to reuse (see docs/api.md — every
 * other JSON endpoint is browser session-cookie auth); a static token is
 * the minimal mechanism that fits "one trusted internal consumer," not a
 * general-purpose API key system with per-client tokens/scopes/rotation.
 */
final class MasterHqAuth
{
    /**
     * Fails closed if the token isn't configured at all (empty/unset) —
     * never treated as "auth disabled." Forgetting to set
     * MASTER_HQ_API_TOKEN in .env must never leave this endpoint open to
     * anyone, it should just mean nobody (including Master HQ) can use it
     * yet.
     */
    public static function isAuthorized(?string $providedToken): bool
    {
        $configured = $_ENV['MASTER_HQ_API_TOKEN'] ?? '';

        if ($configured === '' || $providedToken === null || $providedToken === '') {
            return false;
        }

        return hash_equals($configured, $providedToken);
    }

    /**
     * Extracts the token from "Authorization: Bearer <token>". Returns
     * null for a missing header, a non-Bearer scheme, or an empty token
     * — all treated identically by isAuthorized() (fails closed either
     * way), kept separate only so the controller can tell "no credential
     * supplied" apart from "wrong credential" if it ever wants to.
     */
    public static function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }
}
