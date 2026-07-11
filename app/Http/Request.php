<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public readonly string $method;
    public readonly string $path;

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

    public function ip(): string
    {
        return is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}
