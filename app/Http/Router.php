<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $normalized = rtrim($path, '/') ?: '/';
        $this->routes[$method][$normalized] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;

        if ($handler === null) {
            Response::notFound();
            return;
        }

        $handler($request);
    }
}
