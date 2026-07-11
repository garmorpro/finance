<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AttachmentRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\TransactionRepository;
use App\Support\Csrf;

final class AttachmentController
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB — receipt photos run larger than a CSV export

    /**
     * Server-decided extension per MIME type, never the client-supplied
     * filename's extension — matches CSV import's "never trust the
     * client" file-handling stance.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function upload(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        if ((new TransactionRepository())->findById($transactionId, $householdId) === null) {
            Response::html('Transaction not found.', 404);
            return;
        }

        $redirectBack = function (string $message) use ($transactionId): void {
            $_SESSION['_flash_error'] = $message;
            header('Location: /transactions/' . $transactionId . '/edit');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        $file = $request->file('attachment');
        if ($file === null) {
            $redirectBack('Please choose a file to upload.');
            return;
        }

        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $redirectBack('That file exceeds the server\'s upload size limit. Ask whoever manages the server to raise upload_max_filesize / post_max_size in php.ini.');
            return;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $redirectBack('That upload failed. Please try again.');
            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            $redirectBack('That file is too large (10MB max).');
            return;
        }

        // The extension alone is client-controlled and trivially spoofed —
        // this checks what the file actually looks like, against a tight
        // whitelist (unlike CSV import's permissive one, since receipts
        // are only ever an image or a PDF).
        $mimeType = mime_content_type($file['tmp_name']);
        if ($mimeType === false || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            $redirectBack('Please upload a JPG, PNG, WEBP, or PDF file.');
            return;
        }

        // Never trust the uploaded filename for the storage path — the
        // token is our own securely-random name, decoupled from anything
        // the client sent, which also rules out path traversal.
        $storedFilename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_MIME_TYPES[$mimeType];
        $storedPath = $this->attachmentPath($storedFilename);

        if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
            $redirectBack('Could not read that upload. Please try again.');
            return;
        }

        $originalName = basename($file['name']);

        (new AttachmentRepository())->create($transactionId, $userId, $originalName, $storedFilename, $mimeType, (int) $file['size']);

        (new AuditLogRepository())->log($userId, $householdId, 'attachment.uploaded', 'transaction', $transactionId, $request->ip(), ['filename' => $originalName]);

        $_SESSION['_flash_notice'] = 'Attachment added.';
        header('Location: /transactions/' . $transactionId . '/edit');
    }

    public function download(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $attachmentId = (int) $request->param('attachmentId');
        $householdId = (int) AuthMiddleware::householdId();

        if ((new TransactionRepository())->findById($transactionId, $householdId) === null) {
            Response::html('Not found.', 404);
            return;
        }

        $attachment = (new AttachmentRepository())->findByIdForTransaction($attachmentId, $transactionId);
        if ($attachment === null) {
            Response::html('Not found.', 404);
            return;
        }

        $path = $this->attachmentPath($attachment['stored_filename']);
        if (!is_file($path)) {
            Response::html('File not found.', 404);
            return;
        }

        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Disposition: inline; filename="' . rawurlencode($attachment['original_filename']) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    public function destroy(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $transactionId = (int) $request->param('id');
        $attachmentId = (int) $request->param('attachmentId');
        $householdId = (int) AuthMiddleware::householdId();
        $userId = (int) AuthMiddleware::userId();

        if ((new TransactionRepository())->findById($transactionId, $householdId) === null) {
            Response::html('Not found.', 404);
            return;
        }

        $attachmentRepo = new AttachmentRepository();
        $attachment = $attachmentRepo->findByIdForTransaction($attachmentId, $transactionId);
        if ($attachment === null) {
            Response::html('Not found.', 404);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /transactions/' . $transactionId . '/edit');
            return;
        }

        $attachmentRepo->delete($attachmentId);
        @unlink($this->attachmentPath($attachment['stored_filename']));

        (new AuditLogRepository())->log($userId, $householdId, 'attachment.deleted', 'transaction', $transactionId, $request->ip(), ['filename' => $attachment['original_filename']]);

        $_SESSION['_flash_notice'] = 'Attachment removed.';
        header('Location: /transactions/' . $transactionId . '/edit');
    }

    private function attachmentPath(string $storedFilename): string
    {
        return dirname(__DIR__, 2) . '/storage/attachments/' . $storedFilename;
    }
}
