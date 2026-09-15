<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountBalanceHistoryRepository;
use App\Repositories\AccountRepository;
use App\Repositories\AttachmentRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\TagRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TransactionSplitRepository;
use App\Repositories\UserRepository;
use App\Services\RuleMatchingService;
use App\Support\Csrf;
use App\Support\Env;
use App\Support\QuickAddKey;
use App\Support\RateLimiter;
use App\Support\SafeRedirect;
use App\Support\View;
use App\Validation\MoneyInput;

final class TransactionController
{
    public function index(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        $filters = [
            'account_id' => $request->query('account_id'),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            ...$this->defaultToCurrentMonth($request),
            'search' => $request->query('search'),
            'tag_id' => $request->query('tag_id'),
            'amount_min' => $this->normalizeAmountFilter($request->query('amount_min')),
            'amount_max' => $this->normalizeAmountFilter($request->query('amount_max')),
        ];

        $page = max(1, (int) $request->query('page', '1'));
        $sort = in_array($request->query('sort'), ['date', 'payee', 'amount'], true) ? $request->query('sort') : 'date';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $transactionRepo = new TransactionRepository();
        $result = $transactionRepo->listForHousehold($householdId, $filters, $page, $sort, $dir);
        $sums = $transactionRepo->sumsForHousehold($householdId, $filters);
        $accountRepo = new AccountRepository();
        $transactionIds = array_column($result['rows'], 'id');
        $tagsByTransaction = (new TagRepository())->listForTransactions($transactionIds);
        $attachmentsByTransaction = (new AttachmentRepository())->listForTransactions($transactionIds);

        Response::html(View::render('transactions/index', [
            'transactions' => $result['rows'],
            'tagsByTransaction' => $tagsByTransaction,
            'attachmentsByTransaction' => $attachmentsByTransaction,
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'sums' => $sums,
            'sort' => $sort,
            'dir' => $dir,
            'accounts' => $accountRepo->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId),
            'tags' => (new TagRepository())->listForHousehold($householdId),
            'filters' => $filters,
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
            'error' => $_SESSION['_flash_error'] ?? null,
        ]));

        unset($_SESSION['_flash_notice'], $_SESSION['_flash_error']);
    }

    /**
     * A malformed amount filter (non-numeric, negative) is simply dropped
     * rather than surfaced as a validation error — this is a read-only GET
     * filter, so silently ignoring garbage input is friendlier than a hard
     * failure, and it keeps buildWhere() from ever seeing a non-numeric
     * value in a numeric comparison.
     */
    private function normalizeAmountFilter(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !MoneyInput::isValid($value) || bccomp($value, '0', 2) < 0) {
            return '';
        }

        return MoneyInput::normalize($value);
    }

    /**
     * Shared by index() and export(): a request with neither date_from
     * nor date_to in the URL at all defaults to the current calendar
     * month rather than every transaction ever entered. Checked with
     * hasQueryKey() rather than query()'s own empty-string default
     * specifically so a filter form submitted with both date fields
     * deliberately cleared — a real "show every date" request, which
     * does carry the keys, just blank — still works and is left alone
     * here. Sort/page/column-header links and the Export link all carry
     * $filters forward as concrete values too, so this only ever fires
     * on a genuinely fresh visit — clicking "Clear" included, which is
     * the point: Clear resets to this same default view, not to
     * all-time.
     *
     * @return array{date_from: string, date_to: string}
     */
    private function defaultToCurrentMonth(Request $request): array
    {
        if (!$request->hasQueryKey('date_from') && !$request->hasQueryKey('date_to')) {
            return ['date_from' => gmdate('Y-m-01'), 'date_to' => gmdate('Y-m-t')];
        }

        return ['date_from' => $request->query('date_from'), 'date_to' => $request->query('date_to')];
    }

    public function export(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        $filters = [
            'account_id' => $request->query('account_id'),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            ...$this->defaultToCurrentMonth($request),
            'search' => $request->query('search'),
            'tag_id' => $request->query('tag_id'),
            'amount_min' => $this->normalizeAmountFilter($request->query('amount_min')),
            'amount_max' => $this->normalizeAmountFilter($request->query('amount_max')),
        ];

        $rows = (new TransactionRepository())->exportForHousehold($householdId, $filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transactions-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Payee', 'Category', 'Account', 'Type', 'Amount', 'Notes']);

        foreach ($rows as $row) {
            $category = $row['category_name'] ?? '';
            if ((int) $row['is_split'] === 1 && $category !== '') {
                $category .= ' (split)';
            }

            fputcsv($out, [
                $row['transaction_date'],
                $row['payee'],
                $category,
                $row['account_name'],
                $row['transaction_type'],
                $row['amount'],
                $row['notes'] ?? '',
            ]);
        }

        fclose($out);
    }

    public function showCreateForm(): void
    {
        AuthMiddleware::requireAuth();

        $this->renderForm('transactions/create', [
            'error' => $_SESSION['_flash_error'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]);

        unset($_SESSION['_flash_error'], $_SESSION['_flash_old']);
    }

    /**
     * A standalone page (no sidebar/nav chrome) with an Expense/Income
     * toggle plus Amount/Payee/Account/Category — the home-screen icon's
     * launch target (public/manifest.json's start_url), for logging a
     * transaction the moment the app opens rather than navigating to it
     * from the dashboard first.
     *
     * Reachable two ways, resolved by resolveQuickAddAuth(): a normal
     * logged-in session (unchanged from before), or a device that's
     * unlocked itself with a Quick Add key (Settings > Profile) and
     * skips login entirely — see docs/security.md's "Quick Add key"
     * section for the full threat model. Neither present renders the
     * "enter your key" screen instead of the form.
     *
     * Posts to storeQuickAdd() (POST /quick-add), not store() — a
     * separate, deliberately narrower endpoint than the full form/
     * sidebar popup use, since a Quick Add key must never be able to
     * reach anything store() can do beyond "create one income or
     * expense transaction" — never a transfer, never a split.
     */
    public function showQuickAdd(): void
    {
        $auth = $this->resolveQuickAddAuth();

        if ($auth === null) {
            Response::html(View::render('transactions/quick-add-locked', [
                'csrfToken' => Csrf::token(),
                'error' => $_SESSION['_flash_error'] ?? null,
            ]));
            unset($_SESSION['_flash_error']);
            return;
        }

        $householdId = $auth['householdId'];
        $accounts = (new AccountRepository())->listForHousehold($householdId);

        $user = (new UserRepository())->findById($auth['userId']);
        $defaultAccountId = $user['quick_add_default_account_id'] ?? null;

        // Settings > Profile only ever lets someone choose from this
        // same active-accounts list, but re-check here too: the default
        // could have been set before the account was archived, and an
        // id that isn't one of these <option>s would just leave the
        // field looking unset anyway (the browser can't preselect an
        // option that isn't there).
        if ($defaultAccountId !== null && !in_array((int) $defaultAccountId, array_map(fn (array $a): int => (int) $a['id'], $accounts), true)) {
            $defaultAccountId = null;
        }

        // Both types, not just expense — the Expense/Income toggle in
        // the view filters which of these actually show in the
        // <select> client-side (each <option> carries its own
        // data-type), same "swap what's visible, not what's fetched"
        // approach the account/category color swatches already use.
        $categories = array_values(array_filter(
            (new CategoryRepository())->listForHousehold($householdId),
            fn (array $c): bool => in_array($c['type'], ['expense', 'income'], true)
        ));

        Response::html(View::render('transactions/quick-add', [
            'accounts' => $accounts,
            'categories' => $categories,
            'defaultAccountId' => $defaultAccountId !== null ? (int) $defaultAccountId : null,
            'isKeyAuth' => !AuthMiddleware::check(),
            'csrfToken' => Csrf::token(),
        ]));
    }

    /**
     * Exchanges a pasted Quick Add key for the long-lived cookie that
     * lets this device skip login on /quick-add from now on. Public —
     * reachable with no session at all, which is the entire point — so
     * every failure path here is rate-limited and audit-logged rather
     * than trusted to be a good-faith mistake.
     */
    public function unlockQuickAdd(Request $request): void
    {
        $ip = $request->ip();

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /quick-add');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        if (RateLimiter::tooManyQuickAddKeyAttempts($ip)) {
            (new AuditLogRepository())->log(null, null, 'quick_add_key.rate_limited', null, null, $ip);
            $redirectBack('Too many attempts. Please wait 15 minutes and try again.');
            return;
        }

        $key = trim($request->post('key'));
        $user = $key !== '' ? (new UserRepository())->findByQuickAddKeyHash(QuickAddKey::hash($key)) : null;

        if ($user === null) {
            RateLimiter::recordQuickAddKeyAttempt($ip, false);
            (new AuditLogRepository())->log(null, null, 'quick_add_key.unlock_failed', null, null, $ip);
            $redirectBack("That key wasn't recognized. Please check it and try again.");
            return;
        }

        RateLimiter::recordQuickAddKeyAttempt($ip, true);

        $userRepo = new UserRepository();
        $userRepo->touchQuickAddKeyLastUsed((int) $user['id']);

        (new AuditLogRepository())->log((int) $user['id'], null, 'quick_add_key.unlocked', 'user', (int) $user['id'], $ip);

        // Path scoped to /quick-add only — this cookie is never sent to
        // any other route, so even a bug elsewhere in the app couldn't
        // accidentally read or act on it. HttpOnly keeps it invisible to
        // JavaScript (an XSS bug elsewhere can't exfiltrate it);
        // SameSite=Strict is safe (not just extra-safe) because opening
        // an installed home-screen icon is always a same-site
        // navigation, never a cross-site one.
        setcookie('quick_add_key', QuickAddKey::normalize($key), [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/quick-add',
            'httponly' => true,
            'secure' => Env::isHttps(),
            'samesite' => 'Strict',
        ]);

        header('Location: /quick-add');
    }

    /**
     * "Forget this device" on the unlocked Quick Add page — clears the
     * cookie set by unlockQuickAdd() above without touching the
     * underlying key itself (Settings > Profile's "Revoke" does that).
     * For handing back a shared/borrowed device, or just not trusting a
     * particular phone with standing access anymore, without having to
     * regenerate the key and re-enter it on every other device too.
     */
    public function forgetQuickAddDevice(Request $request): void
    {
        if (!Csrf::verify($request->post('csrf_token'))) {
            header('Location: /quick-add');
            return;
        }

        setcookie('quick_add_key', '', [
            'expires' => time() - 3600,
            'path' => '/quick-add',
            'httponly' => true,
            'secure' => Env::isHttps(),
            'samesite' => 'Strict',
        ]);

        header('Location: /quick-add');
    }

    /**
     * A logged-in session always wins when both are present. Otherwise,
     * a Quick Add key cookie resolves to whichever user it belongs to
     * (via an exact hash match — see QuickAddKey's own doc comment) and,
     * from there, that user's current household — re-derived every call
     * rather than trusted from the cookie, in case membership ever
     * changes. Returns null when neither authorizes anything, meaning
     * the caller should show the "enter your key" screen instead.
     *
     * @return array{householdId: int, userId: int}|null
     */
    private function resolveQuickAddAuth(): ?array
    {
        if (AuthMiddleware::check()) {
            return ['householdId' => (int) AuthMiddleware::householdId(), 'userId' => (int) AuthMiddleware::userId()];
        }

        $cookieKey = $_COOKIE['quick_add_key'] ?? null;
        if (!is_string($cookieKey) || $cookieKey === '') {
            return null;
        }

        $user = (new UserRepository())->findByQuickAddKeyHash(QuickAddKey::hash($cookieKey));
        if ($user === null) {
            return null;
        }

        $membership = (new HouseholdRepository())->findMembership((int) $user['id']);
        if ($membership === null) {
            return null;
        }

        return ['householdId' => (int) $membership['household_id'], 'userId' => (int) $user['id']];
    }

    /**
     * POST /quick-add — the only thing a Quick Add key is ever able to
     * do. Deliberately separate from store(), not a shared code path
     * with it: store() also handles transfers, splits, and arbitrary
     * category/tag/notes input for the full form and the sidebar's
     * (session-only) popup, none of which exist as fields here at all.
     * transaction_type IS read from the request now (income or expense,
     * an Expense/Income toggle in the view), but strictly whitelisted —
     * never anything else — so there is still no input on this endpoint
     * a leaked key could use for anything beyond "create one income or
     * expense transaction on an account this household already has."
     */
    public function storeQuickAdd(Request $request): void
    {
        $auth = $this->resolveQuickAddAuth();
        if ($auth === null) {
            Response::json(['error' => 'Please unlock Quick Add first.'], 401);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $householdId = $auth['householdId'];
        $userId = $auth['userId'];

        $transactionType = $request->post('transaction_type');
        if (!in_array($transactionType, ['income', 'expense'], true)) {
            Response::json(['error' => 'Please choose a valid transaction type.'], 422);
            return;
        }

        $payee = trim($request->post('payee'));
        $amount = trim($request->post('amount'));
        $accountId = (int) $request->post('account_id');
        $categoryId = $request->post('category_id') !== '' ? (int) $request->post('category_id') : null;

        $accounts = (new AccountRepository())->listForHousehold($householdId);
        if (!in_array($accountId, array_map(fn (array $a): int => (int) $a['id'], $accounts), true)) {
            Response::json(['error' => 'Please choose a valid account.'], 422);
            return;
        }

        if ($categoryId !== null) {
            // Matched to the selected type — an expense category can't
            // be attached to an income transaction here even though the
            // full form's own validate() doesn't bother re-checking
            // that (its category <select> is already filtered to match
            // whatever the form's own type picker shows); this endpoint
            // holds itself to a stricter bar than the rest of the app.
            $categories = array_filter(
                (new CategoryRepository())->listForHousehold($householdId),
                fn (array $c): bool => $c['type'] === $transactionType
            );
            if (!in_array($categoryId, array_map(fn (array $c): int => (int) $c['id'], $categories), true)) {
                Response::json(['error' => 'Please choose a valid category.'], 422);
                return;
            }
        }

        if ($payee === '') {
            Response::json(['error' => 'Payee is required.'], 422);
            return;
        }

        if (!MoneyInput::isValid($amount) || bccomp($amount, '0', 2) <= 0) {
            Response::json(['error' => 'Please enter a valid amount greater than zero.'], 422);
            return;
        }

        $accountRepo = new AccountRepository();
        $account = $accountRepo->findById($accountId, $householdId);
        $signedAmount = $this->signedAmount($transactionType, $amount);

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionId = (new TransactionRepository())->create($householdId, $userId, [
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'is_split' => false,
                'transaction_type' => $transactionType,
                'transaction_date' => date('Y-m-d'),
                'signed_amount' => $signedAmount,
                'payee' => $payee,
                'notes' => null,
                'exclude_from_budget' => false,
                'exclude_from_reports' => false,
            ]);

            (new TagRepository())->setTagsForTransaction($transactionId, []);

            (new RuleMatchingService())->applyToTransaction($householdId, $transactionId, [
                'payee' => $payee,
                'notes' => null,
                'amount' => $signedAmount,
                'account_id' => $accountId,
                'transaction_type' => $transactionType,
            ]);

            $newBalance = AccountRepository::applyDelta($account['current_balance'], $signedAmount, $account['account_type']);

            (new AccountBalanceHistoryRepository())->record($accountId, $userId, $account['current_balance'], $newBalance, 'Transaction: ' . $payee);
            $accountRepo->updateBalance($accountId, $householdId, $newBalance);

            $isKeyAuth = !AuthMiddleware::check();

            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                $isKeyAuth ? 'transaction.created_via_quick_add_key' : 'transaction.created',
                'transaction',
                $transactionId,
                $request->ip()
            );

            if ($isKeyAuth) {
                (new UserRepository())->touchQuickAddKeyLastUsed($userId);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::json(['error' => 'Something went wrong saving that transaction. Please try again.'], 500);
            return;
        }

        Response::json(['success' => true]);
    }

    public function store(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $input = $this->readInput($request);

        // The dedicated Quick Add page (resources/views/transactions/
        // quick-add.php) posts here via fetch() with this header set, so
        // it can show its own inline success/error state instead of a
        // full-page redirect — every other caller (the full /transactions/
        // create form, the sidebar's quick-add popup) is a plain <form>
        // POST with no Accept header override, so their behavior below is
        // completely unchanged.
        $wantsJson = str_contains($request->header('Accept') ?? '', 'application/json');

        $redirectBack = function (string $message) use ($input, $wantsJson): void {
            if ($wantsJson) {
                Response::json(['error' => $message], 422);
                return;
            }
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /transactions/create');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validate($input, $householdId);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $accountRepo = new AccountRepository();
        $account = $accountRepo->findById((int) $input['account_id'], $householdId);

        $signedAmount = $this->signedAmount($input['transaction_type'], $input['amount']);

        // A split transaction's own category_id is a "primary" display
        // category (the largest split), not what actually determines
        // budget/spending totals — see the doc comment on
        // primaryCategoryFromSplits().
        $categoryId = $input['is_split']
            ? $this->primaryCategoryFromSplits($input['splits'])
            : ($input['category_id'] !== null ? (int) $input['category_id'] : null);

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionId = (new TransactionRepository())->create($householdId, $userId, [
                ...$input,
                'category_id' => $categoryId,
                'signed_amount' => $signedAmount,
            ]);

            (new TagRepository())->setTagsForTransaction($transactionId, $input['tag_ids']);

            if ($input['is_split']) {
                (new TransactionSplitRepository())->replaceForTransaction(
                    $transactionId,
                    $this->normalizeSplitsForStorage($input['splits'], $input['transaction_type'])
                );
            } else {
                // Rules are skipped for a split transaction — the user
                // just explicitly categorized it by hand across multiple
                // lines, and an auto-rule overriding that single
                // "primary" category field would be surprising.
                (new RuleMatchingService())->applyToTransaction($householdId, $transactionId, [
                    'payee' => $input['payee'],
                    'notes' => $input['notes'],
                    'amount' => $signedAmount,
                    'account_id' => (int) $input['account_id'],
                    'transaction_type' => $input['transaction_type'],
                ]);
            }

            $newBalance = AccountRepository::applyDelta($account['current_balance'], $signedAmount, $account['account_type']);

            (new AccountBalanceHistoryRepository())->record(
                (int) $input['account_id'],
                $userId,
                $account['current_balance'],
                $newBalance,
                'Transaction: ' . $input['payee']
            );
            $accountRepo->updateBalance((int) $input['account_id'], $householdId, $newBalance);

            // payee/amount are encrypted (see App\Support\FieldCipher) — the
            // audit log itself isn't, so metadata never carries their real
            // value, only the identifying transaction ID above.
            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                'transaction.created',
                'transaction',
                $transactionId,
                $request->ip()
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong saving that transaction. Please try again.');
            return;
        }

        if ($wantsJson) {
            Response::json(['success' => true]);
            return;
        }

        $_SESSION['_flash_notice'] = 'Transaction added.';
        header('Location: ' . (SafeRedirect::path($request->post('redirect_to')) ?? '/transactions'));
    }

    public function showEditForm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();

        $transactionRepo = new TransactionRepository();
        $transaction = $transactionRepo->findById($transactionId, $householdId);

        if ($transaction === null) {
            Response::html('Transaction not found.', 404);
            return;
        }

        // Transfers are two linked rows — editing the amount or account on
        // just one side would desync the balances, so they get a reduced
        // edit view (notes only) with delete as the way to "undo."
        if ($transaction['transaction_type'] === 'transfer') {
            $pairAccountName = null;
            if ($transaction['transfer_pair_id'] !== null) {
                $pair = $transactionRepo->findById((int) $transaction['transfer_pair_id'], $householdId);
                $pairAccountName = $pair !== null ? (new AccountRepository())->findById((int) $pair['account_id'], $householdId)['name'] ?? null : null;
            }

            Response::html(View::render('transactions/edit-transfer', [
                'transaction' => $transaction,
                'sourceAccount' => (new AccountRepository())->findById((int) $transaction['account_id'], $householdId),
                'pairAccountName' => $pairAccountName,
                'csrfToken' => Csrf::token(),
                'error' => $_SESSION['_flash_error'] ?? null,
            ]));

            unset($_SESSION['_flash_error']);
            return;
        }

        $this->renderForm('transactions/edit', [
            'transaction' => $transaction,
            'selectedTagIds' => array_column((new TagRepository())->listForTransaction($transactionId), 'id'),
            'existingSplits' => (new TransactionSplitRepository())->listForTransaction($transactionId),
            'attachments' => (new AttachmentRepository())->listForTransaction($transactionId),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]);

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function update(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $transactionRepo = new TransactionRepository();
        $existing = $transactionRepo->findById($transactionId, $householdId);

        if ($existing === null) {
            Response::html('Transaction not found.', 404);
            return;
        }

        if ($existing['transaction_type'] === 'transfer') {
            $this->updateTransferNotes($request, $transactionRepo, $transactionId, $householdId, $userId);
            return;
        }

        $input = $this->readInput($request);

        $redirectBack = function (string $message) use ($transactionId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /transactions/' . $transactionId . '/edit');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $error = $this->validate($input, $householdId);
        if ($error !== null) {
            $redirectBack($error);
            return;
        }

        $accountRepo = new AccountRepository();
        $newSignedAmount = $this->signedAmount($input['transaction_type'], $input['amount']);
        $oldSignedAmount = $existing['amount'];
        $oldAccountId = (int) $existing['account_id'];
        $newAccountId = (int) $input['account_id'];

        $categoryId = $input['is_split']
            ? $this->primaryCategoryFromSplits($input['splits'])
            : ($input['category_id'] !== null ? (int) $input['category_id'] : null);

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionRepo->update($transactionId, $householdId, $userId, [
                ...$input,
                'category_id' => $categoryId,
                'signed_amount' => $newSignedAmount,
            ]);

            (new TagRepository())->setTagsForTransaction($transactionId, $input['tag_ids']);

            // Always replaced, not conditionally — an empty array clears
            // existing splits back to none, so unchecking "split" on an
            // edit correctly un-splits the transaction.
            (new TransactionSplitRepository())->replaceForTransaction(
                $transactionId,
                $input['is_split'] ? $this->normalizeSplitsForStorage($input['splits'], $input['transaction_type']) : []
            );

            $historyRepo = new AccountBalanceHistoryRepository();

            if ($oldAccountId === $newAccountId) {
                $account = $accountRepo->findById($newAccountId, $householdId);
                $reverted = AccountRepository::applyDelta($account['current_balance'], bcmul($oldSignedAmount, '-1', 2), $account['account_type']);
                $newBalance = AccountRepository::applyDelta($reverted, $newSignedAmount, $account['account_type']);

                $historyRepo->record($newAccountId, $userId, $account['current_balance'], $newBalance, 'Transaction edited: ' . $input['payee']);
                $accountRepo->updateBalance($newAccountId, $householdId, $newBalance);
            } else {
                $oldAccount = $accountRepo->findById($oldAccountId, $householdId);
                $revertedOld = AccountRepository::applyDelta($oldAccount['current_balance'], bcmul($oldSignedAmount, '-1', 2), $oldAccount['account_type']);
                $historyRepo->record($oldAccountId, $userId, $oldAccount['current_balance'], $revertedOld, 'Transaction moved to another account: ' . $input['payee']);
                $accountRepo->updateBalance($oldAccountId, $householdId, $revertedOld);

                $newAccount = $accountRepo->findById($newAccountId, $householdId);
                $appliedNew = AccountRepository::applyDelta($newAccount['current_balance'], $newSignedAmount, $newAccount['account_type']);
                $historyRepo->record($newAccountId, $userId, $newAccount['current_balance'], $appliedNew, 'Transaction moved from another account: ' . $input['payee']);
                $accountRepo->updateBalance($newAccountId, $householdId, $appliedNew);
            }

            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                'transaction.updated',
                'transaction',
                $transactionId,
                $request->ip()
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong saving that transaction. Please try again.');
            return;
        }

        $_SESSION['_flash_notice'] = 'Transaction updated.';
        header('Location: /transactions');
    }

    public function destroy(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $transactionRepo = new TransactionRepository();
        $transaction = $transactionRepo->findById($transactionId, $householdId);

        if ($transaction === null) {
            Response::html('Transaction not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /transactions');
            return;
        }

        $accountRepo = new AccountRepository();
        $historyRepo = new AccountBalanceHistoryRepository();
        $auditRepo = new AuditLogRepository();

        $pair = $transaction['transfer_pair_id'] !== null
            ? $transactionRepo->findById((int) $transaction['transfer_pair_id'], $householdId)
            : null;

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $this->deleteOneSideAndReverseBalance($transactionRepo, $accountRepo, $historyRepo, $auditRepo, $transaction, $householdId, $userId, $request->ip());

            // A transfer is two rows representing one real-world event —
            // deleting only one side would leave the other's balance
            // effect applied with no matching counterpart, so both sides
            // are deleted together or not at all.
            if ($pair !== null) {
                $this->deleteOneSideAndReverseBalance($transactionRepo, $accountRepo, $historyRepo, $auditRepo, $pair, $householdId, $userId, $request->ip());
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['_flash_error'] = 'Something went wrong deleting that transaction. Please try again.';
            header('Location: /transactions');
            return;
        }

        $_SESSION['_flash_notice'] = $pair !== null ? 'Transfer deleted.' : 'Transaction deleted.';
        header('Location: /transactions');
    }

    /**
     * Applies one action to every selected row from the transactions list
     * at once — set category, add a tag, or delete. Selection is scoped
     * to the current (paginated) page's checkboxes, not a cross-page
     * "select everything matching this filter."
     */
    public function bulkAction(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();
        $returnQuery = $request->post('return_query');
        $returnUrl = '/transactions' . ($returnQuery !== '' ? '?' . $returnQuery : '');

        $redirectBack = function (string $message, bool $isError = true) use ($returnUrl): void {
            $_SESSION[$isError ? '_flash_error' : '_flash_notice'] = $message;
            header('Location: ' . $returnUrl);
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $requestedIds = $request->postIntList('transaction_ids');
        if ($requestedIds === []) {
            $redirectBack('Please select at least one transaction.');
            return;
        }

        $transactionRepo = new TransactionRepository();

        // Never trust client-supplied IDs on their own — only IDs that
        // actually resolve to a transaction in this household are used
        // from here on.
        $validTransactions = $transactionRepo->findManyById($requestedIds, $householdId);
        $validIds = array_map(fn (array $t): int => (int) $t['id'], $validTransactions);

        if ($validIds === []) {
            $redirectBack('Please select at least one valid transaction.');
            return;
        }

        $action = $request->post('action');

        switch ($action) {
            case 'set_category':
                $categoryId = $request->post('category_id');
                if ($categoryId === '') {
                    $redirectBack('Please choose a category.');
                    return;
                }
                if ((new CategoryRepository())->findById((int) $categoryId, $householdId) === null) {
                    $redirectBack('Please choose a valid category.');
                    return;
                }

                $updated = $transactionRepo->bulkSetCategory($validIds, $householdId, (int) $categoryId);
                $skipped = count($validIds) - $updated;

                (new AuditLogRepository())->log($userId, $householdId, 'transaction.bulk_category_set', 'transaction', $categoryId, $request->ip(), ['count' => $updated]);

                $message = "Category set on {$updated} transaction" . ($updated === 1 ? '' : 's') . '.';
                if ($skipped > 0) {
                    $message .= " {$skipped} split transaction" . ($skipped === 1 ? '' : 's') . " skipped — edit " . ($skipped === 1 ? 'it' : 'them') . " individually.";
                }
                $redirectBack($message, false);
                return;

            case 'add_tag':
                $tagId = $request->post('tag_id');
                if ($tagId === '') {
                    $redirectBack('Please choose a tag.');
                    return;
                }
                if ((new TagRepository())->findById((int) $tagId, $householdId) === null) {
                    $redirectBack('Please choose a valid tag.');
                    return;
                }

                $tagRepo = new TagRepository();
                foreach ($validIds as $id) {
                    $tagRepo->addTagsToTransaction($id, [(int) $tagId]);
                }

                (new AuditLogRepository())->log($userId, $householdId, 'transaction.bulk_tag_added', 'transaction', (int) $tagId, $request->ip(), ['count' => count($validIds)]);

                $redirectBack('Tag added to ' . count($validIds) . ' transaction' . (count($validIds) === 1 ? '' : 's') . '.', false);
                return;

            case 'delete':
                [$message, $isError] = $this->bulkDelete($validTransactions, $householdId, $userId, $request->ip());
                $redirectBack($message, $isError);
                return;

            default:
                $redirectBack('Please choose a bulk action.');
                return;
        }
    }

    /**
     * @param list<array> $transactions already household-verified
     * @return array{0: string, 1: bool} the flash message, and whether it's an error
     */
    private function bulkDelete(array $transactions, int $householdId, int $userId, string $ip): array
    {
        $accountRepo = new AccountRepository();
        $historyRepo = new AccountBalanceHistoryRepository();
        $auditRepo = new AuditLogRepository();
        $transactionRepo = new TransactionRepository();

        $pdo = Connection::get();
        $pdo->beginTransaction();

        $processedIds = [];
        $deletedCount = 0;

        try {
            foreach ($transactions as $transaction) {
                $id = (int) $transaction['id'];
                if (in_array($id, $processedIds, true)) {
                    continue;
                }

                // Re-fetch: an earlier iteration of this same loop may
                // already have deleted this row as another transaction's
                // transfer pair.
                $current = $transactionRepo->findById($id, $householdId);
                if ($current === null) {
                    continue;
                }

                $this->deleteOneSideAndReverseBalance($transactionRepo, $accountRepo, $historyRepo, $auditRepo, $current, $householdId, $userId, $ip);
                $processedIds[] = $id;
                $deletedCount++;

                if ($current['transfer_pair_id'] !== null) {
                    $pair = $transactionRepo->findById((int) $current['transfer_pair_id'], $householdId);
                    if ($pair !== null) {
                        $this->deleteOneSideAndReverseBalance($transactionRepo, $accountRepo, $historyRepo, $auditRepo, $pair, $householdId, $userId, $ip);
                        $processedIds[] = (int) $pair['id'];
                        $deletedCount++;
                    }
                }
            }

            $pdo->commit();

            return ["Deleted {$deletedCount} transaction" . ($deletedCount === 1 ? '' : 's') . '.', false];
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return ['Something went wrong deleting those transactions. Please try again.', true];
        }
    }

    private function deleteOneSideAndReverseBalance(
        TransactionRepository $transactionRepo,
        AccountRepository $accountRepo,
        AccountBalanceHistoryRepository $historyRepo,
        AuditLogRepository $auditRepo,
        array $transaction,
        int $householdId,
        int $userId,
        string $ip
    ): void {
        $transactionRepo->softDelete((int) $transaction['id'], $householdId);

        $accountId = (int) $transaction['account_id'];
        $account = $accountRepo->findById($accountId, $householdId);
        $newBalance = AccountRepository::applyDelta($account['current_balance'], bcmul($transaction['amount'], '-1', 2), $account['account_type']);

        $historyRepo->record($accountId, $userId, $account['current_balance'], $newBalance, 'Transaction deleted: ' . $transaction['payee']);
        $accountRepo->updateBalance($accountId, $householdId, $newBalance);

        $auditRepo->log($userId, $householdId, 'transaction.deleted', 'transaction', (int) $transaction['id'], $ip);
    }

    private function updateTransferNotes(
        Request $request,
        TransactionRepository $transactionRepo,
        int $transactionId,
        int $householdId,
        int $userId
    ): void {
        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /transactions/' . $transactionId . '/edit');
            return;
        }

        $transactionRepo->updateNotes(
            $transactionId,
            $householdId,
            $userId,
            trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null
        );

        (new AuditLogRepository())->log($userId, $householdId, 'transaction.updated', 'transaction', $transactionId, $request->ip());

        $_SESSION['_flash_notice'] = 'Transfer updated.';
        header('Location: /transactions');
    }

    public function showTransferForm(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render('transactions/transfer', [
            'accounts' => (new AccountRepository())->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_old']);
    }

    public function storeTransfer(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $input = [
            'from_account_id' => $request->post('from_account_id'),
            'to_account_id' => $request->post('to_account_id'),
            'transaction_date' => $request->post('transaction_date'),
            'amount' => trim($request->post('amount')),
            'notes' => trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null,
        ];

        $redirectBack = function (string $message) use ($input): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = $input;
            header('Location: /transactions/transfer');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        if (in_array($input['from_account_id'], ['', null], true) || in_array($input['to_account_id'], ['', null], true)) {
            $redirectBack('Please choose both accounts.');
            return;
        }

        if ($input['from_account_id'] === $input['to_account_id']) {
            $redirectBack('Please choose two different accounts.');
            return;
        }

        if (!MoneyInput::isValid($input['amount']) || bccomp($input['amount'], '0', 2) <= 0) {
            $redirectBack('Please enter a valid amount greater than zero.');
            return;
        }

        if (($input['transaction_date'] ?? '') === '' || \DateTime::createFromFormat('Y-m-d', $input['transaction_date']) === false) {
            $redirectBack('Please enter a valid transaction date.');
            return;
        }

        $accountRepo = new AccountRepository();
        $fromAccount = $accountRepo->findById((int) $input['from_account_id'], $householdId);
        $toAccount = $accountRepo->findById((int) $input['to_account_id'], $householdId);

        if ($fromAccount === null || $toAccount === null) {
            $redirectBack('Please choose valid accounts.');
            return;
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $transactionRepo = new TransactionRepository();
            $historyRepo = new AccountBalanceHistoryRepository();
            $auditRepo = new AuditLogRepository();

            $fromId = $transactionRepo->create($householdId, $userId, [
                'account_id' => $fromAccount['id'],
                'category_id' => null,
                'transaction_type' => 'transfer',
                'transaction_date' => $input['transaction_date'],
                'payee' => 'Transfer to ' . $toAccount['name'],
                'notes' => $input['notes'],
                'exclude_from_budget' => true,
                'exclude_from_reports' => true,
                'signed_amount' => bcmul($input['amount'], '-1', 2),
            ]);

            $toId = $transactionRepo->create($householdId, $userId, [
                'account_id' => $toAccount['id'],
                'category_id' => null,
                'transaction_type' => 'transfer',
                'transaction_date' => $input['transaction_date'],
                'payee' => 'Transfer from ' . $fromAccount['name'],
                'notes' => $input['notes'],
                'exclude_from_budget' => true,
                'exclude_from_reports' => true,
                'signed_amount' => $input['amount'],
            ]);

            $transactionRepo->linkTransferPair($fromId, $householdId, $toId);
            $transactionRepo->linkTransferPair($toId, $householdId, $fromId);

            $newFromBalance = AccountRepository::applyDelta($fromAccount['current_balance'], bcmul($input['amount'], '-1', 2), $fromAccount['account_type']);
            $historyRepo->record($fromAccount['id'], $userId, $fromAccount['current_balance'], $newFromBalance, 'Transfer to ' . $toAccount['name']);
            $accountRepo->updateBalance($fromAccount['id'], $householdId, $newFromBalance);

            $newToBalance = AccountRepository::applyDelta($toAccount['current_balance'], $input['amount'], $toAccount['account_type']);
            $historyRepo->record($toAccount['id'], $userId, $toAccount['current_balance'], $newToBalance, 'Transfer from ' . $fromAccount['name']);
            $accountRepo->updateBalance($toAccount['id'], $householdId, $newToBalance);

            // account names/amount are encrypted (see App\Support\FieldCipher)
            // — the audit log itself isn't, so metadata carries the account
            // IDs (not user-identifying on their own) rather than the real
            // names/amount. $toId is the paired transaction's own ID.
            $auditRepo->log($userId, $householdId, 'transaction.transfer_created', 'transaction', $fromId, $request->ip(), [
                'from_account_id' => $fromAccount['id'],
                'to_account_id' => $toAccount['id'],
                'paired_transaction_id' => $toId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $redirectBack('Something went wrong saving that transfer. Please try again.');
            return;
        }

        $_SESSION['_flash_notice'] = 'Transfer added.';
        header('Location: /transactions');
    }

    private function renderForm(string $view, array $extra): void
    {
        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render($view, [
            ...$extra,
            // Includes archived accounts: an edit form must still be able to
            // show the transaction's current account even if it was archived
            // after the transaction was created, or the dropdown would
            // silently reassign it to whatever option happens to be first.
            'accounts' => (new AccountRepository())->listForHousehold($householdId, true),
            'categories' => (new CategoryRepository())->listForHousehold($householdId),
            'tags' => (new TagRepository())->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
        ]));
    }

    private function signedAmount(string $type, string $amount): string
    {
        return $type === 'expense' ? bcmul($amount, '-1', 2) : $amount;
    }

    /**
     * @return array<string, mixed>
     */
    private function readInput(Request $request): array
    {
        return [
            'account_id' => $request->post('account_id'),
            'category_id' => $request->post('category_id') !== '' ? $request->post('category_id') : null,
            'transaction_type' => $request->post('transaction_type'),
            'transaction_date' => $request->post('transaction_date'),
            'amount' => trim($request->post('amount')),
            'payee' => trim($request->post('payee')),
            'notes' => trim($request->post('notes')) !== '' ? trim($request->post('notes')) : null,
            'exclude_from_budget' => $request->post('exclude_from_budget') === '1',
            'exclude_from_reports' => $request->post('exclude_from_reports') === '1',
            'tag_ids' => $request->postIntList('tag_ids'),
            'is_split' => $request->post('is_split') === '1',
            'splits' => $this->readSplits($request),
        ];
    }

    /**
     * Up to 4 fixed split rows (a static form, no add/remove-row
     * JavaScript, matching this app's Rules engine form) — a blank row
     * (no amount entered) is silently dropped rather than treated as an
     * error, so the user doesn't have to fill every slot.
     *
     * @return list<array{category_id: string, amount: string}>
     */
    private function readSplits(Request $request): array
    {
        $raw = $request->postNestedArray('splits');
        $splits = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $amount = isset($row['amount']) && is_string($row['amount']) ? trim($row['amount']) : '';
            if ($amount === '') {
                continue;
            }

            $categoryId = isset($row['category_id']) && is_string($row['category_id']) ? $row['category_id'] : '';

            $splits[] = [
                'category_id' => $categoryId,
                'amount' => $amount,
            ];
        }

        return $splits;
    }

    private function validate(array $input, int $householdId): ?string
    {
        if (in_array($input['account_id'], ['', null], true)) {
            return 'Please choose an account.';
        }

        if ((new AccountRepository())->findById((int) $input['account_id'], $householdId) === null) {
            return 'Please choose a valid account.';
        }

        if (!in_array($input['transaction_type'], ['income', 'expense'], true)) {
            return 'Please choose a valid transaction type.';
        }

        if ($input['payee'] === '') {
            return 'Payee is required.';
        }

        if (!MoneyInput::isValid($input['amount']) || bccomp($input['amount'], '0', 2) <= 0) {
            return 'Please enter a valid amount greater than zero.';
        }

        if (($input['transaction_date'] ?? '') === '' || \DateTime::createFromFormat('Y-m-d', $input['transaction_date']) === false) {
            return 'Please enter a valid transaction date.';
        }

        if ($input['category_id'] !== null && (new CategoryRepository())->findById((int) $input['category_id'], $householdId) === null) {
            return 'Please choose a valid category.';
        }

        if ($input['tag_ids'] !== []) {
            $tagRepo = new TagRepository();
            foreach ($input['tag_ids'] as $tagId) {
                if ($tagRepo->findById($tagId, $householdId) === null) {
                    return 'Please choose valid tags.';
                }
            }
        }

        if ($input['is_split']) {
            return $this->validateSplits($input, $householdId);
        }

        return null;
    }

    private function validateSplits(array $input, int $householdId): ?string
    {
        if (count($input['splits']) < 2) {
            return 'A split transaction needs at least two categories.';
        }

        $categoryRepo = new CategoryRepository();
        $sum = '0.00';

        foreach ($input['splits'] as $split) {
            if ($split['category_id'] === '') {
                return 'Please choose a category for every split.';
            }

            if (!MoneyInput::isValid($split['amount']) || bccomp($split['amount'], '0', 2) <= 0) {
                return 'Each split amount must be greater than zero.';
            }

            $category = $categoryRepo->findById((int) $split['category_id'], $householdId);
            if ($category === null || $category['type'] !== $input['transaction_type']) {
                return 'Please choose a valid ' . $input['transaction_type'] . ' category for every split.';
            }

            $sum = bcadd($sum, MoneyInput::normalize($split['amount']), 2);
        }

        if (bccomp($sum, $input['amount'], 2) !== 0) {
            return 'Split amounts must add up to the transaction total.';
        }

        return null;
    }

    /**
     * The category shown on the transaction outside the split-detail view
     * (the transaction list, filters, CSV export, and the Reports page)
     * is the largest split's category — a "primary" display category, not
     * a claim that the whole amount belongs there. The Budgets page and
     * the dashboard's spending-by-category chart read each split's own
     * category and amount individually instead, so those stay exact.
     *
     * @param list<array{category_id: string, amount: string}> $splits
     */
    private function primaryCategoryFromSplits(array $splits): int
    {
        $largestCategoryId = (int) $splits[0]['category_id'];
        $largestAmount = MoneyInput::normalize($splits[0]['amount']);

        foreach ($splits as $split) {
            $amount = MoneyInput::normalize($split['amount']);
            if (bccomp($amount, $largestAmount, 2) > 0) {
                $largestAmount = $amount;
                $largestCategoryId = (int) $split['category_id'];
            }
        }

        return $largestCategoryId;
    }

    /**
     * @param list<array{category_id: string, amount: string}> $splits
     * @return list<array{category_id: int, amount: string}>
     */
    private function normalizeSplitsForStorage(array $splits, string $transactionType): array
    {
        return array_map(fn (array $split): array => [
            'category_id' => (int) $split['category_id'],
            'amount' => $this->signedAmount($transactionType, MoneyInput::normalize($split['amount'])),
        ], $splits);
    }
}
