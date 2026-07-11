<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $path = dirname(__DIR__, 2) . '/resources/views/' . $template . '.php';

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    public static function partial(string $template, array $data = []): void
    {
        echo self::render($template, $data);
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Appends the file's mtime as a cache-busting query string, so a new
     * deploy is fetched immediately instead of serving a browser-cached
     * copy of the old asset under the same URL.
     */
    public static function asset(string $publicPath): string
    {
        $absolute = dirname(__DIR__, 2) . '/public/' . ltrim($publicPath, '/');
        $version = is_file($absolute) ? (string) filemtime($absolute) : '0';

        return '/' . ltrim($publicPath, '/') . '?v=' . $version;
    }
}
