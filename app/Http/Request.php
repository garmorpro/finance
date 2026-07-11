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
}
