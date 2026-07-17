<?php

declare(strict_types=1);

namespace Tests;

use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;

/**
 * Shared fixture builders for DB-backed tests. Goes through the same
 * repositories the application itself uses rather than raw SQL, so a
 * fixture is only ever as valid as the app actually requires — if a
 * repository's create() signature changes, these fail loudly instead of
 * quietly building fixtures the app could never produce itself.
 */
trait Fixtures
{
    /**
     * @return array{household_id: int, user_id: int}
     */
    private function makeHousehold(string $name = 'Test Household'): array
    {
        $email = 'owner-' . bin2hex(random_bytes(6)) . '@example.test';
        $userId = (new UserRepository())->create('Test Owner', $email, password_hash('correct horse battery staple', PASSWORD_DEFAULT));
        $householdId = (new HouseholdRepository())->create($name, $userId);
        (new HouseholdRepository())->addMember($householdId, $userId, 'owner');

        return ['household_id' => $householdId, 'user_id' => $userId];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeAccount(int $householdId, int $userId, array $overrides = []): int
    {
        return (new AccountRepository())->create($householdId, $userId, array_merge([
            'name' => 'Test Checking',
            'institution_name' => null,
            'account_type' => 'checking',
            'current_balance' => '1000.00',
            'available_balance' => null,
            'credit_limit' => null,
            'interest_rate' => null,
            'minimum_payment' => null,
            'payment_due_day' => null,
            'original_balance' => null,
            'color' => null,
            'include_in_net_worth' => true,
            'include_in_budget' => true,
            'notes' => null,
        ], $overrides));
    }

    private function makeCategory(int $householdId, string $type = 'expense', string $name = 'Test Category'): int
    {
        return (new CategoryRepository())->create($householdId, $name, $type);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transactionData(array $overrides = []): array
    {
        return array_merge([
            'account_id' => null,
            'category_id' => null,
            'transaction_type' => 'expense',
            'transaction_date' => gmdate('Y-m-d'),
            'payee' => 'Test Payee',
            'notes' => null,
            'exclude_from_budget' => false,
            'exclude_from_reports' => false,
            'signed_amount' => '-10.00',
        ], $overrides);
    }
}
