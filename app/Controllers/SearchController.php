<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\TagRepository;
use App\Repositories\TransactionRepository;
use App\Support\Csrf;
use App\Support\View;

/**
 * The sidebar's quick search — a handful of likely matches per entity
 * type, not a full filterable browse (that's what /transactions and
 * /reports are for). Accounts, categories, and tags are typically small
 * per-household lists already loaded elsewhere in the app, so those are
 * filtered in PHP rather than adding a near-duplicate SQL LIKE query to
 * three more repositories; transactions reuse the existing search filter
 * on listForHousehold() rather than a second, redundant query method.
 */
final class SearchController
{
    private const RESULT_LIMIT = 8;

    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $query = trim($request->query('q'));

        $results = null;

        if ($query !== '') {
            $transactionResult = (new TransactionRepository())->listForHousehold($householdId, ['search' => $query], 1);

            $results = [
                'transactions' => array_slice($transactionResult['rows'], 0, self::RESULT_LIMIT),
                'transactionTotal' => $transactionResult['total'],
                'accounts' => $this->filterByFields(
                    (new AccountRepository())->listForHousehold($householdId, true),
                    ['name', 'institution_name'],
                    $query
                ),
                'categories' => $this->filterByFields(
                    (new CategoryRepository())->listForHousehold($householdId, true),
                    ['name'],
                    $query
                ),
                'tags' => $this->filterByFields(
                    (new TagRepository())->listForHousehold($householdId),
                    ['name'],
                    $query
                ),
            ];
        }

        Response::html(View::render('search/index', [
            'query' => $query,
            'results' => $results,
            'csrfToken' => Csrf::token(),
        ]));
    }

    /**
     * @param list<array> $rows
     * @param list<string> $fields
     * @return list<array>
     */
    private function filterByFields(array $rows, array $fields, string $query): array
    {
        $matches = array_values(array_filter($rows, function (array $row) use ($fields, $query): bool {
            foreach ($fields as $field) {
                if (isset($row[$field]) && is_string($row[$field]) && mb_stripos($row[$field], $query) !== false) {
                    return true;
                }
            }
            return false;
        }));

        return array_slice($matches, 0, self::RESULT_LIMIT);
    }
}
