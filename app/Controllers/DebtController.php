<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Services\DebtService;
use App\Support\Csrf;
use App\Support\View;

final class DebtController
{
    public function index(): void
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

        Response::html(View::render('debt/index', [
            'rows' => $rows,
            'totalBalance' => $summary['totalBalance'],
            'totalMinimumPayment' => $summary['totalMinimumPayment'],
            'weightedAverageRate' => $summary['weightedAverageRate'],
            'csrfToken' => Csrf::token(),
        ]));
    }
}
