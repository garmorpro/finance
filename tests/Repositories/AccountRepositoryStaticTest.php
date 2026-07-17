<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\AccountRepository;
use PHPUnit\Framework\TestCase;

/**
 * Covers AccountRepository's static helpers, which are pure functions
 * (no DB) despite living on a repository class.
 */
final class AccountRepositoryStaticTest extends TestCase
{
    public function test_is_liability_is_true_for_liability_account_types(): void
    {
        $this->assertTrue(AccountRepository::isLiability('credit_card'));
        $this->assertTrue(AccountRepository::isLiability('mortgage'));
        $this->assertTrue(AccountRepository::isLiability('auto_loan'));
    }

    public function test_is_liability_is_false_for_asset_account_types(): void
    {
        $this->assertFalse(AccountRepository::isLiability('checking'));
        $this->assertFalse(AccountRepository::isLiability('savings'));
        $this->assertFalse(AccountRepository::isLiability('investment'));
    }

    public function test_apply_delta_on_an_asset_account_adds_the_delta_directly(): void
    {
        // A $60 charge (signed as -60.00) on checking reduces what's in
        // the account; a $500 deposit increases it — plain addition.
        $this->assertSame('440.00', AccountRepository::applyDelta('500.00', '-60.00', 'checking'));
        $this->assertSame('1000.00', AccountRepository::applyDelta('500.00', '500.00', 'checking'));
    }

    public function test_apply_delta_on_a_liability_account_inverts_the_delta(): void
    {
        // A $60 charge (signed as -60.00) on a credit card increases
        // what's owed: 500.00 owed + 60.00 = 560.00 owed.
        $this->assertSame('560.00', AccountRepository::applyDelta('500.00', '-60.00', 'credit_card'));

        // A $200 payment (signed as +200.00, since it's the "to" side of
        // a transfer) reduces what's owed: 500.00 owed - 200.00 = 300.00.
        $this->assertSame('300.00', AccountRepository::applyDelta('500.00', '200.00', 'credit_card'));
    }

    public function test_apply_delta_fully_pays_off_a_liability_to_zero(): void
    {
        $this->assertSame('0.00', AccountRepository::applyDelta('412.50', '412.50', 'credit_card'));
    }
}
