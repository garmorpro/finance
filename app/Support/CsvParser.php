<?php

declare(strict_types=1);

namespace App\Support;

final class CsvParser
{
    private const MAX_ROWS = 5000;

    /**
     * @return array{header: list<string>, rows: list<list<string>>}|null
     *         null means the file couldn't be parsed as CSV at all.
     */
    public static function parse(string $path): ?array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $header = fgetcsv($handle);

        if ($header === false || $header === null) {
            fclose($handle);
            return null;
        }

        $header = array_map(static fn ($v): string => trim((string) $v), $header);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === null || $row === [null]) {
                continue;
            }

            $rows[] = array_map(static fn ($v): string => trim((string) $v), $row);

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }
}
