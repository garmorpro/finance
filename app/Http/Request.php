<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public readonly string $method;
    public readonly string $path;

    /** @var array<string, string> */
    private array $params = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $this->path = rtrim($path === false || $path === null ? '/' : $path, '/') ?: '/';
    }

    public function post(string $key, string $default = ''): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
    }

    public function query(string $key, string $default = ''): string
    {
        return is_string($_GET[$key] ?? null) ? $_GET[$key] : $default;
    }

    /**
     * Distinguishes "this key wasn't in the URL at all" from "it was
     * there but blank" — query()'s own empty-string default can't tell
     * those apart. TransactionController::index() needs exactly that
     * distinction: a bare /transactions visit (no date_from/date_to key
     * at all) defaults to the current month, while a filter form
     * submitted with those fields left blank means "show every date,"
     * and both cases would otherwise look identical to query().
     */
    public function hasQueryKey(string $key): bool
    {
        return array_key_exists($key, $_GET);
    }

    /**
     * For multi-select filter inputs (e.g. "account_ids[]"). Non-array or
     * non-string entries are dropped rather than trusted.
     *
     * @return list<string>
     */
    public function queryArray(string $key): array
    {
        $value = $_GET[$key] ?? null;

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * For multi-select form inputs (e.g. "tag_ids[]"). Non-array or
     * non-string entries are dropped rather than trusted.
     *
     * @return list<string>
     */
    public function postArray(string $key): array
    {
        $value = $_POST[$key] ?? null;

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * @return list<int>
     */
    public function queryIntList(string $key): array
    {
        return self::toIntList($this->queryArray($key));
    }

    /**
     * @return list<int>
     */
    public function postIntList(string $key): array
    {
        return self::toIntList($this->postArray($key));
    }

    /**
     * For nested array form inputs (e.g. "splits[1][category_id]",
     * "splits[1][amount]") — returns the raw nested structure so callers
     * can shape it themselves. Every leaf value still needs to be
     * type-checked by the caller, since PHP doesn't enforce any shape on
     * submitted array data.
     *
     * @return array<int|string, mixed>
     */
    public function postNestedArray(string $key): array
    {
        $value = $_POST[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param list<string> $values
     * @return list<int>
     */
    private static function toIntList(array $values): array
    {
        return array_values(array_unique(array_map('intval', array_filter($values, 'is_numeric'))));
    }

    /**
     * This deployment sits behind Cloudflare Tunnel (cloudflared), which
     * connects to Apache over the local loopback interface — every
     * request's real REMOTE_ADDR is always ::1/127.0.0.1 regardless of
     * who's actually visiting, which makes IP-based rate limiting
     * (RateLimiter) and audit-log IP tracking (AuditLogRepository)
     * meaningless without this. CF-Connecting-IP is safe to trust here
     * specifically because Cloudflare's edge sets it on every request
     * and the tunnel means there is no other way to reach this origin —
     * an attacker can't bypass Cloudflare to hit Apache directly and
     * forge the header themselves the way they could on a normal
     * internet-facing port. Falls back to REMOTE_ADDR (and validates
     * whatever CF-Connecting-IP contains) so this stays correct if the
     * header's ever missing — a local CLI request, or if this
     * deployment ever moves off Cloudflare Tunnel — rather than trusting
     * a malformed or absent value.
     */
    public function ip(): string
    {
        $cfConnectingIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        if (is_string($cfConnectingIp) && filter_var($cfConnectingIp, FILTER_VALIDATE_IP) !== false) {
            return $cfConnectingIp;
        }

        return is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Reads an arbitrary request header (e.g. "Authorization") — nothing
     * in this app has needed one before now (session-cookie auth doesn't
     * require reading headers), so this didn't exist previously. Falls
     * back to getallheaders() because some Apache/PHP-FPM setups don't
     * populate $_SERVER['HTTP_AUTHORIZATION'] specifically unless
     * explicitly configured (a long-standing PHP/Apache quirk, unrelated
     * to this app) — getallheaders() often sees it when $_SERVER doesn't.
     */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $headerName => $value) {
                if (strcasecmp($headerName, $name) === 0) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Distinguishes a fetch()-driven autosave call from a plain form post,
     * so a handler can return JSON instead of a redirect without needing a
     * separate route.
     */
    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        if (!is_array($file) || !isset($file['tmp_name'], $file['error'])) {
            return null;
        }

        return $file;
    }

    /**
     * @param array<string, string> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }
}
