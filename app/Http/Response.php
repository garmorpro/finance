<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function notFound(): void
    {
        self::json(['error' => 'Not found'], 404);
    }
}
