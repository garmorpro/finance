<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\EmailVerificationRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use App\Support\Csrf;
use App\Support\Logger;
use App\Support\Mailer;
use App\Support\RateLimiter;
use App\Support\Turnstile;
use App\Support\View;
use App\Validation\PasswordPolicy;

/**
 * Public, self-service household creation — the internet-facing
 * counterpart to bin/create-owner.php, which remains how *this*
 * deployment's very first household got created. Every household
 * created here goes through the exact same household_id-scoped
 * repositories as everything else in the app (see docs/security.md's
 * "Authorization" section) — opening account *creation* to the public
 * doesn't touch the isolation boundary between existing households at
 * all, and tests/Integration/HouseholdIsolationTest.php covers that
 * explicitly for registration-created households.
 */
final class RegistrationController
{
    public function showForm(): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            return;
        }

        Response::html(View::render('auth/register', [
            'turnstileSiteKey' => Turnstile::siteKey(),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['_flash_error'] ?? null,
            'old' => $_SESSION['_flash_old'] ?? [],
        ]));

        unset($_SESSION['_flash_error'], $_SESSION['_flash_old']);
    }

    public function register(Request $request): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            return;
        }

        $ip = $request->ip();
        $name = trim($request->post('name'));
        $email = trim(mb_strtolower($request->post('email')));
        $householdName = trim($request->post('household_name'));
        $password = $request->post('password');
        $confirm = $request->post('password_confirmation');

        $redirectBack = function (string $message) use ($name, $email, $householdName): void {
            $_SESSION['_flash_error'] = $message;
            $_SESSION['_flash_old'] = ['name' => $name, 'email' => $email, 'household_name' => $householdName];
            header('Location: /register');
        };

        if (!Csrf::verify($request->post('csrf_token'))) {
            $redirectBack('Your session expired. Please try again.');
            return;
        }

        if (RateLimiter::tooManyRegistrationAttempts($ip)) {
            (new AuditLogRepository())->log(null, null, 'registration.rate_limited', null, null, $ip);
            $redirectBack('Too many signups attempted from this location recently. Please try again in an hour.');
            return;
        }

        RateLimiter::recordRegistrationAttempt($ip);

        // Skips enforcement (rather than blocking every signup) when
        // TURNSTILE_* isn't configured — same "degrade the feature, not
        // the app" reasoning as Mailer's own isConfigured() check. Left
        // blank, registration works but has no bot check beyond the IP
        // rate limit above.
        if (Turnstile::isConfigured() && !Turnstile::verify($request->post('cf-turnstile-response'), $ip)) {
            $redirectBack("We couldn't verify you're not a bot. Please try again.");
            return;
        }

        if ($name === '' || $householdName === '') {
            $redirectBack('Please fill in every field.');
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $redirectBack('Please enter a valid email address.');
            return;
        }

        if (!PasswordPolicy::isValid($password)) {
            $redirectBack(PasswordPolicy::message());
            return;
        }

        if (!hash_equals($password, $confirm)) {
            $redirectBack('Passwords do not match.');
            return;
        }

        $userRepo = new UserRepository();

        if ($userRepo->findByEmail($email) !== null) {
            // Unlike password reset's deliberately neutral "if that email
            // is registered..." messaging, there's no account-enumeration
            // risk worth hiding here — successful registration is
            // publicly self-evident already (anyone can attempt to
            // register with any address and observe this exact outcome
            // either way), and a real person mid-signup needs to know
            // their email is already taken so they don't think nothing
            // happened.
            $redirectBack('An account with that email already exists. Try logging in, or resetting your password if you\'ve forgotten it.');
            return;
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $userId = $userRepo->create($name, $email, password_hash($password, PASSWORD_DEFAULT));

            $householdRepo = new HouseholdRepository();
            $householdId = $householdRepo->create($householdName, $userId);
            $householdRepo->addMember($householdId, $userId, 'owner');
            (new CategoryRepository())->seedDefaults($householdId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::error('Public registration failed', ['error' => $e->getMessage(), 'email' => $email]);
            $redirectBack('Something went wrong creating your account. Please try again.');
            return;
        }

        (new AuditLogRepository())->log(
            $userId,
            $householdId,
            'household.registered',
            'user',
            $userId,
            $ip,
            ['email' => $email, 'household_name' => $householdName]
        );

        $this->issueAndSendVerification($userId, $name, $email);

        header('Location: /register/check-email');
    }

    public function showCheckEmail(): void
    {
        Response::html(View::render('auth/register-check-email'));
    }

    public function verifyEmail(Request $request): void
    {
        $token = $request->query('token');
        $tokenRow = $token !== '' ? (new EmailVerificationRepository())->findValidByTokenHash(hash('sha256', $token)) : null;

        if ($tokenRow === null) {
            Response::html(View::render('auth/verify-email-invalid'), 400);
            return;
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findById((int) $tokenRow['user_id']);

        if ($user === null) {
            Response::html(View::render('auth/verify-email-invalid'), 400);
            return;
        }

        (new EmailVerificationRepository())->markUsed((int) $tokenRow['id']);
        $userRepo->markEmailVerified((int) $user['id']);

        (new AuditLogRepository())->log((int) $user['id'], null, 'email.verified', 'user', (int) $user['id'], $request->ip());

        // Verifying proves the same two things a normal login does
        // (correct password, at registration time — and now, control of
        // the email address), so this signs them straight in rather than
        // sending them back to type their password again.
        (new AuthController())->completeLogin($user, $request, new HouseholdRepository(), new AuditLogRepository());
    }

    public function showResendForm(): void
    {
        Response::html(View::render('auth/resend-verification', [
            'csrfToken' => Csrf::token(),
            'notice' => $_SESSION['_flash_notice'] ?? null,
        ]));

        unset($_SESSION['_flash_notice']);
    }

    public function resend(Request $request): void
    {
        $email = trim(mb_strtolower($request->post('email')));
        $ip = $request->ip();

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /verify-email/resend');
            return;
        }

        if (RateLimiter::tooManyRegistrationAttempts($ip)) {
            header('Location: /verify-email/resend');
            return;
        }

        RateLimiter::recordRegistrationAttempt($ip);

        $user = (new UserRepository())->findByEmail($email);
        if ($user !== null && $user['email_verified_at'] === null) {
            $this->issueAndSendVerification((int) $user['id'], $user['name'], $email);
        }

        // Same "always show the same neutral outcome" reasoning as
        // password reset — doesn't reveal whether that email exists or
        // is already verified.
        $_SESSION['_flash_notice'] = 'If that email has a pending, unverified account, a new verification link has been sent.';
        header('Location: /verify-email/resend');
    }

    private function issueAndSendVerification(int $userId, string $name, string $email): void
    {
        $verificationRepo = new EmailVerificationRepository();
        $verificationRepo->invalidateAllForUser($userId);

        $token = bin2hex(random_bytes(32));
        $verificationRepo->create($userId, hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 24 * 3600));

        $verifyUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/verify-email?token=' . $token;
        $emailContent = self::buildVerificationEmail($name, $verifyUrl);

        $sent = Mailer::send(
            $email,
            $name,
            $emailContent['subject'],
            $emailContent['html'],
            $emailContent['text'],
            $_ENV['MAIL_NOREPLY_FROM_ADDRESS'] ?? null,
            $_ENV['MAIL_NOREPLY_FROM_NAME'] ?? null
        );

        if (!$sent) {
            // Same "logged instead of sent" fallback as everywhere else
            // outbound email is used in this app.
            Logger::info('Email verification link generated (email not sent — see Mailer log above)', [
                'user_id' => $userId,
                'email' => $email,
                'verify_url' => $verifyUrl,
            ]);
        }
    }

    /**
     * @return array{subject: string, html: string, text: string}
     */
    private static function buildVerificationEmail(string $name, string $verifyUrl): array
    {
        $firstName = trim(explode(' ', $name, 2)[0]);
        $subject = 'Confirm your email for MyCFO+';

        // $firstName is what the person just typed into the registration
        // form themselves — escaped before landing in the HTML body
        // regardless, same reasoning (and same convention) as every
        // other email builder in this app that interpolates a
        // user-controlled string.
        $htmlFirstName = View::e($firstName);

        $html = <<<HTML
            <div style="background:#f5f5f4;padding:32px 16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
              <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e7e5e4;">
                <div style="padding:32px 28px;">
                  <div style="font-weight:700;font-size:16px;color:#1c1917;margin-bottom:28px;">MyCFO<span style="color:#e2694b;">+</span></div>
                  <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;color:#1c1917;">Confirm your email</h1>
                  <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#57534e;">
                    Hey {$htmlFirstName} — one last step. Confirm this is really your email address and
                    you'll be signed straight in.
                  </p>
                  <div style="margin-bottom:24px;">
                    <a href="{$verifyUrl}" style="display:inline-block;background:#e2694b;color:#ffffff;font-weight:600;font-size:15px;padding:13px 26px;border-radius:10px;text-decoration:none;">Confirm my email &rarr;</a>
                  </div>
                  <p style="margin:0;font-size:13px;line-height:1.6;color:#a39c96;">
                    Button not working? Paste this into your browser:<br>
                    <span style="word-break:break-all;color:#78716c;">{$verifyUrl}</span><br>
                    This link expires in 24 hours. If you didn't create a MyCFO+ account, you can
                    safely ignore this email.
                  </p>
                </div>
              </div>
            </div>
            HTML;

        $text = "Confirm your email\n\n"
            . "Hey {$firstName} — one last step. Confirm this is really your email address and "
            . "you'll be signed straight in.\n\n"
            . "Confirm: {$verifyUrl}\n\n"
            . "This link expires in 24 hours. If you didn't create a MyCFO+ account, you can safely "
            . "ignore this email.";

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
