<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_formats_a_positive_amount(): void
    {
        $this->assertSame('$1,234.56', Money::format('1234.56'));
    }

    public function test_it_formats_a_negative_amount_with_a_leading_minus_before_the_dollar_sign(): void
    {
        $this->assertSame('-$45.00', Money::format('-45.00'));
    }

    public function test_it_formats_zero_without_a_sign(): void
    {
        $this->assertSame('$0.00', Money::format('0.00'));
    }

    public function test_it_renders_a_dash_for_null(): void
    {
        $this->assertSame('—', Money::format(null));
    }

    public function test_it_pads_a_whole_number_to_two_decimals(): void
    {
        $this->assertSame('$50.00', Money::format('50'));
    }
}
