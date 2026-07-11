<?php

declare(strict_types=1);

namespace Tests\Validation;

use App\Validation\MoneyInput;
use PHPUnit\Framework\TestCase;

final class MoneyInputTest extends TestCase
{
    /**
     * @dataProvider validAmounts
     */
    public function test_it_accepts_valid_amounts(string $value): void
    {
        $this->assertTrue(MoneyInput::isValid($value));
    }

    public static function validAmounts(): array
    {
        return [
            'whole number' => ['50'],
            'two decimals' => ['50.00'],
            'one decimal' => ['50.5'],
            'negative' => ['-12.34'],
            'zero' => ['0'],
            'zero with decimals' => ['0.00'],
            'padded with whitespace' => ['  42.10  '],
            'large amount' => ['999999999999'],
        ];
    }

    /**
     * @dataProvider invalidAmounts
     */
    public function test_it_rejects_invalid_amounts(string $value): void
    {
        $this->assertFalse(MoneyInput::isValid($value));
    }

    public static function invalidAmounts(): array
    {
        return [
            'empty string' => [''],
            'letters' => ['abc'],
            'three decimal places' => ['12.345'],
            'currency symbol' => ['$12.00'],
            'thousands separator' => ['1,000.00'],
            'trailing dash' => ['12.00-'],
            'double dash' => ['--12.00'],
            'too many digits' => ['1234567890123'],
            'only a decimal point' => ['.'],
            'multiple decimal points' => ['1.2.3'],
        ];
    }

    public function test_normalize_pads_to_two_decimal_places(): void
    {
        $this->assertSame('50.00', MoneyInput::normalize('50'));
        $this->assertSame('50.50', MoneyInput::normalize('50.5'));
        $this->assertSame('-12.34', MoneyInput::normalize('-12.34'));
    }

    public function test_optional_returns_null_for_blank_input(): void
    {
        $this->assertNull(MoneyInput::optional(''));
        $this->assertNull(MoneyInput::optional('   '));
    }

    public function test_optional_normalizes_non_blank_input(): void
    {
        $this->assertSame('50.00', MoneyInput::optional('50'));
    }
}
