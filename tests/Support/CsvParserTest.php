<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\CsvParser;
use PHPUnit\Framework\TestCase;

final class CsvParserTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
        $this->tempFiles = [];
    }

    public function test_it_parses_a_header_and_rows(): void
    {
        $path = $this->writeTempCsv("Date,Payee,Amount\n2026-01-01,Coffee Shop,-4.50\n2026-01-02,Paycheck,1500.00\n");

        $result = CsvParser::parse($path);

        $this->assertNotNull($result);
        $this->assertSame(['Date', 'Payee', 'Amount'], $result['header']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(['2026-01-01', 'Coffee Shop', '-4.50'], $result['rows'][0]);
        $this->assertSame(['2026-01-02', 'Paycheck', '1500.00'], $result['rows'][1]);
    }

    public function test_it_trims_whitespace_from_every_field(): void
    {
        $path = $this->writeTempCsv("Date, Payee , Amount\n 2026-01-01 , Coffee Shop , -4.50 \n");

        $result = CsvParser::parse($path);

        $this->assertSame(['Date', 'Payee', 'Amount'], $result['header']);
        $this->assertSame(['2026-01-01', 'Coffee Shop', '-4.50'], $result['rows'][0]);
    }

    public function test_it_returns_null_for_a_nonexistent_file(): void
    {
        // Suppressed: CsvParser's fopen() call raises a native PHP
        // warning for a missing file before returning false/null, and
        // PHPUnit converts unsuppressed warnings into test failures —
        // this test is about the null return value, not that warning.
        $result = @CsvParser::parse('/tmp/this-file-does-not-exist-' . uniqid('', true) . '.csv');

        $this->assertNull($result);
    }

    public function test_it_returns_an_empty_row_list_for_a_header_only_file(): void
    {
        $path = $this->writeTempCsv("Date,Payee,Amount\n");

        $result = CsvParser::parse($path);

        $this->assertNotNull($result);
        $this->assertSame(['Date', 'Payee', 'Amount'], $result['header']);
        $this->assertSame([], $result['rows']);
    }

    public function test_it_returns_null_for_a_completely_empty_file(): void
    {
        $path = $this->writeTempCsv('');

        $this->assertNull(CsvParser::parse($path));
    }

    private function writeTempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
