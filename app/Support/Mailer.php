<?php

declare(strict_types=1);

namespace App\Support;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Thin wrapper around PHPMailer, the only place in the app that talks
 * SMTP directly. Every caller (currently just
 * bin/send-budget-reminders.php) gets the same fallback behavior: if
 * MAIL_* isn't configured, or the send itself fails for any reason
 * (bad credentials, host unreachable, rejected by the receiving
 * server), send() returns false and logs why — the caller decides
 * whether that means falling back to logging the link instead, same
 * as this app's original stub-everything convention. A misconfigured
 * mail server should degrade this feature, not take down the request
 * that triggered it.
 */
final class Mailer
{
    public static function isConfigured(): bool
    {
        return !empty($_ENV['MAIL_HOST']) && !empty($_ENV['MAIL_USERNAME']) && !empty($_ENV['MAIL_PASSWORD']) && !empty($_ENV['MAIL_FROM_ADDRESS']);
    }

    /**
     * $fromAddress/$fromName override the default MAIL_FROM_ADDRESS/NAME
     * for this one message — e.g. household invitations and email
     * verification send from a distinct "no-reply" identity
     * (MAIL_NOREPLY_FROM_ADDRESS) rather than whatever address budget
     * reminders use, so a recipient can tell at a glance which kind of
     * message they're looking at. isConfigured() still only checks the
     * base MAIL_* vars — a from-address override doesn't need its own
     * separate "is mail configured at all" check.
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?string $fromAddress = null,
        ?string $fromName = null
    ): bool {
        if (!self::isConfigured()) {
            Logger::info('Mailer::send skipped — MAIL_* not fully configured in .env', ['to' => $toEmail]);
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $_ENV['MAIL_USERNAME'];
            $mail->Password = (string) $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) ($_ENV['MAIL_PORT'] ?? 587);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom(
                $fromAddress ?? (string) $_ENV['MAIL_FROM_ADDRESS'],
                $fromName ?? (string) ($_ENV['MAIL_FROM_NAME'] ?? 'MyCFO+')
            );
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();

            return true;
        } catch (PHPMailerException $e) {
            Logger::error('Mailer::send failed', [
                'to' => $toEmail,
                'error' => $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(),
            ]);
            return false;
        }
    }
}
