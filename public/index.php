<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AttachmentController;
use App\Controllers\AuthController;
use App\Controllers\BudgetController;
use App\Controllers\CashFlowController;
use App\Controllers\CategoryController;
use App\Controllers\CategoryGroupController;
use App\Controllers\DashboardController;
use App\Controllers\DebtController;
use App\Controllers\GoalController;
use App\Controllers\HouseholdController;
use App\Controllers\ImportController;
use App\Controllers\NotificationController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Controllers\RecurringController;
use App\Controllers\ReportController;
use App\Controllers\RuleController;
use App\Controllers\SearchController;
use App\Controllers\TagController;
use App\Controllers\TransactionController;
use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Support\ErrorHandler;
use App\Support\SecurityHeaders;
use App\Support\View;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$appConfig = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

ErrorHandler::register($appConfig['debug']);

$isHttps = str_starts_with($appConfig['url'], 'https://');
SecurityHeaders::apply($isHttps);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

$router = new Router();

$authController = new AuthController();
$router->get('/login', fn (): mixed => $authController->showLogin());
$router->post('/login', fn (Request $r): mixed => $authController->login($r));
$router->post('/logout', fn (Request $r): mixed => $authController->logout($r));

$passwordResetController = new PasswordResetController();
$router->get('/forgot-password', fn (): mixed => $passwordResetController->showForgotForm());
$router->post('/forgot-password', fn (Request $r): mixed => $passwordResetController->sendReset($r));
$router->get('/reset-password', fn (Request $r): mixed => $passwordResetController->showResetForm($r));
$router->post('/reset-password', fn (Request $r): mixed => $passwordResetController->resetPassword($r));

$householdController = new HouseholdController();
$router->get('/settings/household', fn (): mixed => $householdController->showMembers());
$router->post('/settings/household/invite', fn (Request $r): mixed => $householdController->sendInvite($r));
$router->get('/accept-invite', fn (): mixed => $householdController->showAcceptForm());
$router->post('/accept-invite', fn (Request $r): mixed => $householdController->acceptInvite($r));

$profileController = new ProfileController();
$router->get('/settings/profile', fn (): mixed => $profileController->show());
$router->post('/settings/profile', fn (Request $r): mixed => $profileController->updateProfile($r));
$router->get('/settings/security', fn (): mixed => $profileController->showSecurity());
$router->post('/settings/security', fn (Request $r): mixed => $profileController->updatePassword($r));

$accountController = new AccountController();
$router->get('/accounts', fn (): mixed => $accountController->index());
$router->get('/accounts/create', fn (): mixed => $accountController->showCreateForm());
$router->post('/accounts', fn (Request $r): mixed => $accountController->store($r));
$router->get('/accounts/{id}/edit', fn (Request $r): mixed => $accountController->showEditForm($r));
$router->post('/accounts/{id}', fn (Request $r): mixed => $accountController->update($r));
$router->post('/accounts/{id}/balance', fn (Request $r): mixed => $accountController->updateBalance($r));
$router->post('/accounts/{id}/archive', fn (Request $r): mixed => $accountController->archive($r));
$router->post('/accounts/{id}/restore', fn (Request $r): mixed => $accountController->restore($r));

$transactionController = new TransactionController();
$router->get('/transactions', fn (Request $r): mixed => $transactionController->index($r));
$router->get('/transactions/export', fn (Request $r): mixed => $transactionController->export($r));
$router->get('/transactions/create', fn (): mixed => $transactionController->showCreateForm());
$router->post('/transactions', fn (Request $r): mixed => $transactionController->store($r));
$router->get('/transactions/transfer', fn (): mixed => $transactionController->showTransferForm());
$router->post('/transactions/transfer', fn (Request $r): mixed => $transactionController->storeTransfer($r));
$router->get('/transactions/review', fn (): mixed => $transactionController->review());
$router->post('/transactions/review/mark-all', fn (Request $r): mixed => $transactionController->markAllReviewed($r));
$router->post('/transactions/bulk', fn (Request $r): mixed => $transactionController->bulkAction($r));
$router->get('/transactions/{id}/edit', fn (Request $r): mixed => $transactionController->showEditForm($r));
$router->post('/transactions/{id}', fn (Request $r): mixed => $transactionController->update($r));
$router->post('/transactions/{id}/delete', fn (Request $r): mixed => $transactionController->destroy($r));
$router->post('/transactions/{id}/review', fn (Request $r): mixed => $transactionController->markReviewed($r));

$attachmentController = new AttachmentController();
$router->post('/transactions/{id}/attachments', fn (Request $r): mixed => $attachmentController->upload($r));
$router->get('/transactions/{id}/attachments/{attachmentId}', fn (Request $r): mixed => $attachmentController->download($r));
$router->post('/transactions/{id}/attachments/{attachmentId}/delete', fn (Request $r): mixed => $attachmentController->destroy($r));

$importController = new ImportController();
$router->get('/transactions/import', fn (): mixed => $importController->showUploadForm());
$router->post('/transactions/import', fn (Request $r): mixed => $importController->upload($r));
$router->post('/transactions/import/confirm', fn (Request $r): mixed => $importController->confirm($r));

$budgetController = new BudgetController();
$router->get('/budgets', fn (Request $r): mixed => $budgetController->index($r));
$router->post('/budgets/items', fn (Request $r): mixed => $budgetController->saveItem($r));
$router->post('/budgets/copy-previous', fn (Request $r): mixed => $budgetController->copyPrevious($r));

$recurringController = new RecurringController();
$router->get('/recurring', fn (): mixed => $recurringController->index());
$router->get('/recurring/create', fn (): mixed => $recurringController->showCreateForm());
$router->post('/recurring', fn (Request $r): mixed => $recurringController->store($r));
$router->get('/recurring/{id}/edit', fn (Request $r): mixed => $recurringController->showEditForm($r));
$router->post('/recurring/{id}', fn (Request $r): mixed => $recurringController->update($r));
$router->get('/recurring/{id}/pay', fn (Request $r): mixed => $recurringController->showMarkPaidForm($r));
$router->post('/recurring/{id}/pay', fn (Request $r): mixed => $recurringController->markPaid($r));
$router->post('/recurring/{id}/cancel', fn (Request $r): mixed => $recurringController->cancel($r));
$router->post('/recurring/{id}/reactivate', fn (Request $r): mixed => $recurringController->reactivate($r));

$goalController = new GoalController();
$router->get('/goals', fn (): mixed => $goalController->index());
$router->get('/goals/create', fn (): mixed => $goalController->showCreateForm());
$router->post('/goals', fn (Request $r): mixed => $goalController->store($r));
$router->get('/goals/{id}/edit', fn (Request $r): mixed => $goalController->showEditForm($r));
$router->post('/goals/{id}', fn (Request $r): mixed => $goalController->update($r));
$router->post('/goals/{id}/contributions', fn (Request $r): mixed => $goalController->addContribution($r));
$router->post('/goals/{id}/contributions/{contributionId}/delete', fn (Request $r): mixed => $goalController->deleteContribution($r));
$router->post('/goals/{id}/complete', fn (Request $r): mixed => $goalController->complete($r));
$router->post('/goals/{id}/archive', fn (Request $r): mixed => $goalController->archive($r));
$router->post('/goals/{id}/reactivate', fn (Request $r): mixed => $goalController->reactivate($r));

$debtController = new DebtController();
$router->get('/debt', fn (): mixed => $debtController->index());

$cashFlowController = new CashFlowController();
$router->get('/cash-flow', fn (Request $r): mixed => $cashFlowController->index($r));

$reportController = new ReportController();
$router->get('/reports', fn (Request $r): mixed => $reportController->index($r));
$router->get('/reports/export', fn (Request $r): mixed => $reportController->export($r));

$categoryController = new CategoryController();
$router->get('/settings/categories', fn (): mixed => $categoryController->index());
$router->post('/settings/categories', fn (Request $r): mixed => $categoryController->store($r));
$router->post('/settings/categories/{id}', fn (Request $r): mixed => $categoryController->update($r));
$router->post('/settings/categories/{id}/archive', fn (Request $r): mixed => $categoryController->archive($r));
$router->post('/settings/categories/{id}/restore', fn (Request $r): mixed => $categoryController->restore($r));

$categoryGroupController = new CategoryGroupController();
$router->post('/settings/category-groups', fn (Request $r): mixed => $categoryGroupController->store($r));
$router->post('/settings/category-groups/{id}', fn (Request $r): mixed => $categoryGroupController->update($r));
$router->post('/settings/category-groups/{id}/delete', fn (Request $r): mixed => $categoryGroupController->destroy($r));

$tagController = new TagController();
$router->get('/settings/tags', fn (): mixed => $tagController->index());
$router->post('/settings/tags', fn (Request $r): mixed => $tagController->store($r));
$router->post('/settings/tags/{id}', fn (Request $r): mixed => $tagController->update($r));
$router->post('/settings/tags/{id}/delete', fn (Request $r): mixed => $tagController->destroy($r));

$notificationController = new NotificationController();
$router->get('/notifications', fn (): mixed => $notificationController->index());
$router->get('/settings/notifications', fn (): mixed => $notificationController->settings());
$router->post('/settings/notifications', fn (Request $r): mixed => $notificationController->updatePreferences($r));

$searchController = new SearchController();
$router->get('/search', fn (Request $r): mixed => $searchController->index($r));

$ruleController = new RuleController();
$router->get('/settings/rules', fn (): mixed => $ruleController->index());
$router->get('/settings/rules/create', fn (): mixed => $ruleController->showCreateForm());
$router->post('/settings/rules', fn (Request $r): mixed => $ruleController->store($r));
$router->get('/settings/rules/{id}/edit', fn (Request $r): mixed => $ruleController->showEditForm($r));
$router->post('/settings/rules/{id}', fn (Request $r): mixed => $ruleController->update($r));
$router->post('/settings/rules/{id}/toggle', fn (Request $r): mixed => $ruleController->toggleActive($r));
$router->post('/settings/rules/{id}/delete', fn (Request $r): mixed => $ruleController->destroy($r));
$router->get('/settings/rules/{id}/apply', fn (Request $r): mixed => $ruleController->previewApply($r));
$router->post('/settings/rules/{id}/apply', fn (Request $r): mixed => $ruleController->applyToExisting($r));

$dashboardController = new DashboardController();
$router->post('/dashboard/layout', fn (Request $r): mixed => $dashboardController->saveLayout($r));
$router->post('/dashboard/widgets', fn (Request $r): mixed => $dashboardController->saveVisibility($r));
$router->post('/dashboard/width', fn (Request $r): mixed => $dashboardController->saveWidth($r));

$router->get('/', function () use ($appConfig, $dashboardController): void {
    if (!empty($_SESSION['user_id'])) {
        $dashboardController->index();
        return;
    }

    $name = htmlspecialchars($appConfig['name'], ENT_QUOTES);
    $appCss = View::asset('/assets/css/app.css');

    Response::html(<<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$name}</title>
            <link rel="stylesheet" href="{$appCss}">
        </head>
        <body class="auth-shell">
            <div class="text-center">
                <h1 class="text-3xl font-semibold tracking-tight text-stone-900 dark:text-white mb-2">{$name}</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mb-6">A self-hosted household finance dashboard.</p>
                <a href="/login" class="btn-primary">Log in</a>
            </div>
        </body>
        </html>
        HTML
    );
});

$router->get('/health', function (): void {
    $status = 'ok';
    $dbStatus = 'ok';

    try {
        Connection::get()->query('SELECT 1');
    } catch (\Throwable $e) {
        $status = 'degraded';
        $dbStatus = 'unreachable';
    }

    Response::json([
        'status' => $status,
        'database' => $dbStatus,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ]);
});

$router->dispatch(new Request());
