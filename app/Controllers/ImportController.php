<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AccountBalanceHistoryRepository;
use App\Repositories\AccountRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ImportRepository;
use App\Repositories\ImportRowRepository;
use App\Repositories\TransactionRepository;
use App\Services\RuleMatchingService;
use App\Support\CsvParser;
use App\Support\Csrf;
use App\Support\View;

final class ImportController
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const PREVIEW_ROWS = 10;

    public function showUploadForm(): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        Response::html(View::render('imports/upload', [
            'accounts' => (new AccountRepository())->listForHousehold($householdId),
            'recentImports' => (new ImportRepository())->listForHousehold($householdId),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_notice']);
    }

    public function upload(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /transactions/import');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $accountId = $request->post('account_id');
        if ($accountId === '' || (new AccountRepository())->findById((int) $accountId, $householdId) === null) {
            $redirectBack('Please choose a valid account.');
            return;
        }

        $file = $request->file('csv_file');

        if ($file === null) {
            $redirectBack('Please choose a CSV file to upload.');
            return;
        }

        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            // The server's php.ini upload_max_filesize/post_max_size can
            // reject a file before it ever reaches our own 5MB check below —
            // worth a distinct message since "choose a file" is misleading here.
            $redirectBack('That file exceeds the server\'s upload size limit. Ask whoever manages the server to raise upload_max_filesize / post_max_size in php.ini.');
            return;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $redirectBack('That upload failed. Please try again.');
            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            $redirectBack('That file is too large (5MB max).');
            return;
        }

        $originalName = basename($file['name']);
        if (!str_ends_with(strtolower($originalName), '.csv')) {
            $redirectBack('Please upload a .csv file.');
            return;
        }

        // The extension alone is client-controlled and trivially spoofed
        // (rename anything.exe to anything.csv) — this checks what the
        // file actually looks like. CSV has no reliable magic-byte
        // signature, so the whitelist is deliberately permissive rather
        // than trying to pin down one exact MIME type; the real
        // structural validation happens next, in CsvParser::parse().
        $mimeType = mime_content_type($file['tmp_name']);
        $allowedMimeTypes = ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        if ($mimeType === false || !in_array($mimeType, $allowedMimeTypes, true)) {
            $redirectBack('That file does not look like a CSV file.');
            return;
        }

        // Never trust the uploaded filename for the storage path — the
        // token is our own securely-random name, decoupled from anything
        // the client sent, which also rules out path traversal.
        $token = bin2hex(random_bytes(16));
        $storedPath = $this->importPath($token);

        if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
            $redirectBack('Could not read that upload. Please try again.');
            return;
        }

        $parsed = CsvParser::parse($storedPath);

        if ($parsed === null || $parsed['header'] === [] || $parsed['rows'] === []) {
            @unlink($storedPath);
            $redirectBack('That file could not be read as CSV, or has no data rows.');
            return;
        }

        Response::html(View::render('imports/preview', [
            'token' => $token,
            'originalName' => $originalName,
            'accountId' => (int) $accountId,
            'header' => $parsed['header'],
            'previewRows' => array_slice($parsed['rows'], 0, self::PREVIEW_ROWS),
            'totalRows' => count($parsed['rows']),
            'csrfToken' => Csrf::token(),
        ]));
    }

    public function confirm(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        $redirectBack = function (string $message): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /transactions/import');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $token = $request->post('token');
        $originalName = $request->post('original_name');
        $accountId = (int) $request->post('account_id');
        $dateCol = $request->post('date_column');
        $amountCol = $request->post('amount_column');
        $payeeCol = $request->post('payee_column');
        $categoryCol = $request->post('category_column');
        $notesCol = $request->post('notes_column');
        $flipSign = $request->post('flip_sign') === '1';

        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $redirectBack('That import session expired. Please upload the file again.');
            return;
        }

        $storedPath = $this->importPath($token);
        $parsed = CsvParser::parse($storedPath);

        if ($parsed === null) {
            $redirectBack('That import session expired. Please upload the file again.');
            return;
        }

        $account = (new AccountRepository())->findById($accountId, $householdId);
        if ($account === null) {
            @unlink($storedPath);
            $redirectBack('Please choose a valid account.');
            return;
        }

        if ($dateCol === '' || $amountCol === '' || $payeeCol === '') {
            $redirectBack('Please map the Date, Amount, and Payee columns.');
            return;
        }

        $categories = (new CategoryRepository())->listForHousehold($householdId);
        $categoryByName = [];
        foreach ($categories as $category) {
            $categoryByName[mb_strtolower($category['name'])] = (int) $category['id'];
        }

        $transactionRepo = new TransactionRepository();
        $importRowRepo = new ImportRowRepository();
        $ruleMatcher = new RuleMatchingService();

        $imported = 0;
        $skipped = 0;
        $rejected = 0;
        $netAmount = '0.00';

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $importId = (new ImportRepository())->create(
                $householdId,
                $userId,
                $accountId,
                $originalName !== '' ? $originalName : 'import.csv',
                count($parsed['rows']),
                0,
                0,
                0
            );

            foreach ($parsed['rows'] as $i => $row) {
                $rowNumber = $i + 1;
                $rawData = json_encode($row, JSON_UNESCAPED_SLASHES);

                $date = $this->normalizeDate($row[(int) $dateCol] ?? '');
                $amount = $this->normalizeAmount($row[(int) $amountCol] ?? '', $flipSign);
                $payee = trim($row[(int) $payeeCol] ?? '');

                if ($date === null || $amount === null || $payee === '') {
                    $importRowRepo->record($importId, $rowNumber, $rawData, 'rejected', null, 'Could not read date, amount, or payee.');
                    $rejected++;
                    continue;
                }

                if ($transactionRepo->existsSimilar($householdId, $accountId, $date, $amount, $payee)) {
                    $importRowRepo->record($importId, $rowNumber, $rawData, 'skipped', null, 'Matches an existing transaction.');
                    $skipped++;
                    continue;
                }

                $categoryId = null;
                if ($categoryCol !== '') {
                    $categoryName = mb_strtolower(trim($row[(int) $categoryCol] ?? ''));
                    $categoryId = $categoryByName[$categoryName] ?? null;
                }

                $notes = $notesCol !== '' ? (trim($row[(int) $notesCol] ?? '') ?: null) : null;

                $transactionType = bccomp($amount, '0', 2) < 0 ? 'expense' : 'income';

                $transactionId = $transactionRepo->create($householdId, $userId, [
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'transaction_type' => $transactionType,
                    'transaction_date' => $date,
                    'payee' => $payee,
                    'notes' => $notes,
                    'exclude_from_budget' => false,
                    'exclude_from_reports' => false,
                    'signed_amount' => $amount,
                ]);

                $ruleMatcher->applyToTransaction($householdId, $transactionId, [
                    'payee' => $payee,
                    'notes' => $notes,
                    'amount' => $amount,
                    'account_id' => $accountId,
                    'transaction_type' => $transactionType,
                ]);

                $importRowRepo->record($importId, $rowNumber, $rawData, 'imported', $transactionId, null);
                $netAmount = bcadd($netAmount, $amount, 2);
                $imported++;
            }

            if ($imported > 0) {
                $newBalance = AccountRepository::applyDelta($account['current_balance'], $netAmount, $account['account_type']);

                (new AccountBalanceHistoryRepository())->record(
                    $accountId,
                    $userId,
                    $account['current_balance'],
                    $newBalance,
                    "CSV import: {$originalName} ({$imported} transactions)"
                );
                (new AccountRepository())->updateBalance($accountId, $householdId, $newBalance);
            }

            // Backfill the summary counts now that the loop is done —
            // simpler than threading running totals through every branch
            // above, and this is one cheap UPDATE either way.
            Connection::get()->prepare(
                'UPDATE imports SET imported_count = :imported, skipped_count = :skipped, rejected_count = :rejected WHERE id = :id'
            )->execute([
                'imported' => $imported,
                'skipped' => $skipped,
                'rejected' => $rejected,
                'id' => $importId,
            ]);

            (new AuditLogRepository())->log(
                $userId,
                $householdId,
                'transactions.imported',
                'import',
                $importId,
                $request->ip(),
                ['filename' => $originalName, 'imported' => $imported, 'skipped' => $skipped, 'rejected' => $rejected]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            @unlink($storedPath);
            $redirectBack('Something went wrong processing that import. Please try again.');
            return;
        }

        @unlink($storedPath);

        Response::html(View::render('imports/result', [
            'imported' => $imported,
            'skipped' => $skipped,
            'rejected' => $rejected,
            'total' => count($parsed['rows']),
        ]));
    }

    private function importPath(string $token): string
    {
        return dirname(__DIR__, 2) . '/storage/imports/' . $token . '.csv';
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : gmdate('Y-m-d', $timestamp);
    }

    private function normalizeAmount(string $value, bool $flipSign): ?string
    {
        $value = trim($value);
        $value = str_replace([',', '$', '£', '€', ' '], '', $value);

        // Some exports use accounting-style parentheses for negatives.
        if (preg_match('/^\((.+)\)$/', $value, $matches)) {
            $value = '-' . $matches[1];
        }

        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        $normalized = bcadd($value, '0', 2);

        return $flipSign ? bcmul($normalized, '-1', 2) : $normalized;
    }
}
