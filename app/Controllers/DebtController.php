<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Services\DebtService;
use App\Support\Csrf;
use App\Support\View;
use App\Validation\MoneyInput;

final class DebtController
{
    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $accounts = array_values(array_filter(
            (new AccountRepository())->listForHousehold($householdId),
            fn (array $a): bool => AccountRepository::isLiability($a['account_type'])
        ));

        $debtService = new DebtService();
        $rows = [];

        foreach ($accounts as $account) {
            $balance = $account['current_balance'];

            $rows[] = [
                'account' => $account,
                'payoff' => $debtService->estimatePayoff($balance, $account['interest_rate'], $account['minimum_payment']),
                'utilization' => $account['account_type'] === 'credit_card'
                    ? $debtService->creditUtilization($balance, $account['credit_limit'])
                    : null,
            ];
        }

        $summary = $debtService->summarize($accounts);

        $rawExtra = trim($request->query('extra', '0'));
        $extraMonthly = MoneyInput::isValid($rawExtra) ? max(0.0, (float) $rawExtra) : 0.0;

        $comparableDebts = [];
        foreach ($accounts as $account) {
            if (bccomp($account['current_balance'], '0.00', 2) <= 0) {
                continue;
            }

            $comparableDebts[] = [
                'id' => (int) $account['id'],
                'name' => $account['name'],
                'balance' => $account['current_balance'],
                'rate' => $account['interest_rate'] !== null ? (float) $account['interest_rate'] : 0.0,
                'minimum' => $account['minimum_payment'] !== null ? (float) $account['minimum_payment'] : 0.0,
            ];
        }

        $comparison = null;
        if (count($comparableDebts) >= 2) {
            $comparison = [
                'snowball' => $debtService->simulatePayoff($comparableDebts, $extraMonthly, 'snowball'),
                'avalanche' => $debtService->simulatePayoff($comparableDebts, $extraMonthly, 'avalanche'),
            ];
        }

        Response::html(View::render('debt/index', [
            'rows' => $rows,
            'totalBalance' => $summary['totalBalance'],
            'totalMinimumPayment' => $summary['totalMinimumPayment'],
            'weightedAverageRate' => $summary['weightedAverageRate'],
            'extraMonthly' => $extraMonthly,
            'comparison' => $comparison,
            'comparableDebtCount' => count($comparableDebts),
            'csrfToken' => Csrf::token(),
        ]));
    }
}
