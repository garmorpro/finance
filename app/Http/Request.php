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

    public function ip(): string
    {
        return is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
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
