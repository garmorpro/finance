<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\AccountGroups;
use PHPUnit\Framework\TestCase;

final class AccountGroupsTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function account(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Test Account',
            'account_type' => 'checking',
            'current_balance' => '0.00',
        ], $overrides);
    }

    public function test_split_by_asset_liability_separates_correctly(): void
    {
        $accounts = [
            $this->account(['id' => 1, 'account_type' => 'checking', 'current_balance' => '1000.00']),
            $this->account(['id' => 2, 'account_type' => 'credit_card', 'current_balance' => '200.00']),
            $this->account(['id' => 3, 'account_type' => 'mortgage', 'current_balance' => '300000.00']),
            $this->account(['id' => 4, 'account_type' => 'investment', 'current_balance' => '5000.00']),
        ];

        $result = AccountGroups::splitByAssetLiability($accounts);

        $assetIds = array_column($result['assets']['accounts'], 'id');
        $liabilityIds = array_column($result['liabilities']['accounts'], 'id');

        $this->assertSame([1, 4], $assetIds);
        $this->assertSame([2, 3], $liabilityIds);
        $this->assertSame('6000.00', $result['assets']['total']);
        $this->assertSame('300200.00', $result['liabilities']['total']);
        $this->assertSame('Assets', $result['assets']['label']);
        $this->assertSame('Liabilities', $result['liabilities']['label']);
    }

    public function test_split_by_asset_liability_preserves_group_order_within_each_side(): void
    {
        // Investments come after Cash in group()'s own type ordering —
        // this asserts splitByAssetLiability() doesn't just preserve the
        // caller's original array order, but the display order group()
        // already establishes.
        $accounts = [
            $this->account(['id' => 1, 'account_type' => 'investment']),
            $this->account(['id' => 2, 'account_type' => 'checking']),
        ];

        $result = AccountGroups::splitByAssetLiability($accounts);

        $this->assertSame([2, 1], array_column($result['assets']['accounts'], 'id'));
    }

    public function test_split_by_asset_liability_handles_empty_input(): void
    {
        $result = AccountGroups::splitByAssetLiability([]);

        $this->assertSame([], $result['assets']['accounts']);
        $this->assertSame([], $result['liabilities']['accounts']);
        $this->assertSame('0.00', $result['assets']['total']);
        $this->assertSame('0.00', $result['liabilities']['total']);
    }

    public function test_split_by_asset_liability_handles_all_assets_or_all_liabilities(): void
    {
        $allAssets = [$this->account(['id' => 1, 'account_type' => 'savings'])];
        $result = AccountGroups::splitByAssetLiability($allAssets);
        $this->assertCount(1, $result['assets']['accounts']);
        $this->assertSame([], $result['liabilities']['accounts']);

        $allLiabilities = [$this->account(['id' => 1, 'account_type' => 'auto_loan'])];
        $result = AccountGroups::splitByAssetLiability($allLiabilities);
        $this->assertSame([], $result['assets']['accounts']);
        $this->assertCount(1, $result['liabilities']['accounts']);
    }
}
