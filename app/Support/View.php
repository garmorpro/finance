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

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
