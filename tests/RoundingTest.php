<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * CLAUDE.md requires financial rounding to be tested explicitly and
 * requires decimal-safe (bcmath) arithmetic everywhere currency is
 * calculated — never binary float. These tests demonstrate why: the
 * classic float traps below are exactly the kind of silent drift that
 * would corrupt account balances over many transactions if the app ever
 * used +/-/* directly on money instead of bcadd/bcsub/bcmul/bcdiv.
 */
final class RoundingTest extends TestCase
{
    public function test_bcmath_avoids_the_classic_binary_float_rounding_trap(): void
    {
        // 0.1 + 0.2 !== 0.3 in binary floating point (it's
        // 0.30000000000000004) — this is the single most common source
        // of "why is my balance off by a cent" bugs in money code.
        $this->assertSame('0.30', bcadd('0.10', '0.20', 2));
        $this->assertNotEquals('0.3', 0.1 + 0.2, 'Sanity check that the float trap this test guards against is real.');
    }

    public function test_bcmath_stays_exact_across_many_repeated_additions(): void
    {
        // Simulates 1,000 transactions of $0.10 each hitting the same
        // account balance — with float arithmetic this drifts measurably
        // after a few hundred additions; bcmath must not.
        $balance = '0.00';
        for ($i = 0; $i < 1000; $i++) {
            $balance = bcadd($balance, '0.10', 2);
        }

        $this->assertSame('100.00', $balance);
    }

    public function test_signed_amount_for_an_expense_negates_a_positive_input(): void
    {
        // Mirrors TransactionController::signedAmount(): users always
        // enter a positive number plus a type selector; the server, never
        // the client, decides the sign.
        $this->assertSame('-42.50', bcmul('42.50', '-1', 2));
    }

    public function test_bccomp_treats_differently_formatted_equal_values_as_equal(): void
    {
        // "50" and "50.00" must compare equal for validation logic (e.g.
        // "amount must be greater than zero") even though they're
        // different strings.
        $this->assertSame(0, bccomp('50', '50.00', 2));
        $this->assertSame(0, bccomp('0', '0.00', 2));
    }

    public function test_bcdiv_truncates_rather_than_rounds(): void
    {
        // bcdiv truncates toward zero at the given scale by default — it
        // does not round. Code that divides money (e.g. "split evenly
        // across 3") must account for this explicitly rather than assume
        // standard rounding, or a three-way split of $10.00 silently
        // loses a cent ($3.33 * 3 = $9.99, not $10.00).
        $this->assertSame('3.33', bcdiv('10.00', '3', 2));
    }

    public function test_net_worth_subtraction_stays_exact_for_values_with_many_cents(): void
    {
        $assets = bcadd('12345.67', '890.12', 2);
        $liabilities = '4321.98';

        $this->assertSame('13235.79', $assets);
        $this->assertSame('8913.81', bcsub($assets, $liabilities, 2));
    }
}
