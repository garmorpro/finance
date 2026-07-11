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
