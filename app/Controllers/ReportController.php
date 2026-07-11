<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\HouseholdRepository;
use App\Services\ReportingService;
use App\Support\Csrf;
use App\Support\View;

final class ReportController
{
    private const GROUP_OPTIONS = ['category', 'merchant', 'account', 'month', 'member'];
    private const TYPE_OPTIONS = ['', 'income', 'expense', 'transfer'];

    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $filters = $this->readFilters($request);
        $groupBy = $this->resolveGroupBy($request->query('group_by'));

        $report = (new ReportingService())->transactionsReport($householdId, $filters, $groupBy);

        Response::html(View::render('reports/index', [
            'rows' => $report['rows'],
            'totals' => $report['totals'],
            'groupBy' => $groupBy,
            'filters' => $filters,
            'accounts' => (new AccountRepository())->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId, true),
            'members' => (new HouseholdRepository())->listMembers($householdId),
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function export(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $filters = $this->readFilters($request);
        $groupBy = $this->resolveGroupBy($request->query('group_by'));

        $report = (new ReportingService())->transactionsReport($householdId, $filters, $groupBy);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-' . $groupBy . '-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [ucfirst($groupBy), 'Income', 'Expenses', 'Transfers', 'Net', 'Transactions']);

        foreach ($report['rows'] as $row) {
            fputcsv($out, [$row['label'], $row['income'], $row['expense'], $row['transfer'], $row['net'], $row['count']]);
        }

        fputcsv($out, ['Total', $report['totals']['income'], $report['totals']['expense'], $report['totals']['transfer'], $report['totals']['net'], $report['totals']['count']]);

        fclose($out);
    }

    /**
     * @return array{date_from: string, date_to: string, account_ids: list<int>, category_ids: list<int>, type: string, user_id: int|null}
     */
    private function readFilters(Request $request): array
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        if ($dateFrom === '' || \DateTime::createFromFormat('Y-m-d', $dateFrom) === false) {
            $dateFrom = date('Y-m-01', strtotime('-2 months'));
        }

        if ($dateTo === '' || \DateTime::createFromFormat('Y-m-d', $dateTo) === false) {
            $dateTo = gmdate('Y-m-d');
        }

        $type = $request->query('type');
        if (!in_array($type, self::TYPE_OPTIONS, true)) {
            $type = '';
        }

        $userId = $request->query('user_id');

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_ids' => $this->toIntList($request->queryArray('account_ids')),
            'category_ids' => $this->toIntList($request->queryArray('category_ids')),
            'type' => $type,
            'user_id' => $userId !== '' ? (int) $userId : null,
        ];
    }

    /**
     * @param list<string> $values
     * @return list<int>
     */
    private function toIntList(array $values): array
    {
        return array_values(array_unique(array_map('intval', array_filter($values, 'is_numeric'))));
    }

    private function resolveGroupBy(string $value): string
    {
        return in_array($value, self::GROUP_OPTIONS, true) ? $value : 'category';
    }
}
