<?php

declare(strict_types=1);

namespace App\Support;

final class Logger
{
    private static string $logDir = __DIR__ . '/../../storage/logs';

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            '[%s] %s: %s %s%s',
            gmdate('Y-m-d\TH:i:s\Z'),
            $level,
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        $file = self::$logDir . '/app-' . gmdate('Y-m-d') . '.log';
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
