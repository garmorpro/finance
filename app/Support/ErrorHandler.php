<?php

declare(strict_types=1);

namespace App\Support;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(bool $debug): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e) use ($debug): void {
            Logger::error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            http_response_code(500);
            header('Content-Type: application/json');

            if ($debug) {
                echo json_encode([
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ], JSON_PRETTY_PRINT);
            } else {
                echo json_encode(['error' => 'Something went wrong. Please try again.']);
            }
        });
    }
}
