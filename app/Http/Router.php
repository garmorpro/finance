<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    /** @var array<string, list<array{pattern: string, params: list<string>, handler: callable}>> */
    private array $patternRoutes = [];

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

        if (!str_contains($normalized, '{')) {
            $this->routes[$method][$normalized] = $handler;
            return;
        }

        $paramNames = [];
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_]+)\}/',
            function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                return '([^/]+)';
            },
            $normalized
        );

        $this->patternRoutes[$method][] = [
            'pattern' => '#^' . $pattern . '$#',
            'params' => $paramNames,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;

        if ($handler !== null) {
            $handler($request);
            return;
        }

        foreach ($this->patternRoutes[$request->method] ?? [] as $route) {
            if (preg_match($route['pattern'], $request->path, $matches) === 1) {
                $params = array_combine($route['params'], array_slice($matches, 1));
                $request->setParams($params);
                ($route['handler'])($request);
                return;
            }
        }

        Response::notFound();
    }
}
