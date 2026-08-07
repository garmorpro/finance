<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\AuditLogRepository;
use App\Repositories\HouseholdRepository;
use App\Repositories\UserRepository;
use App\Repositories\WebAuthnCredentialRepository;
use App\Services\WebAuthnService;
use App\Support\Csrf;
use App\Support\UserAgent;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkey registration (Settings → Security, authenticated) and
 * sign-in (the login page, unauthenticated) ceremonies. Kept separate
 * from AuthController/ProfileController since these are JSON-API-style
 * endpoints (options out, browser response in) rather than the
 * form-post-and-redirect pattern the rest of the app uses.
 *
 * The options object generated in each *Options() action is stashed in
 * session and consumed by the matching *Verify()/register() action —
 * WebAuthn's challenge is single-use and must be checked against
 * exactly what was issued, not reconstructed from the request.
 */
final class WebAuthnController
{
    public function registerOptions(): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $user = (new UserRepository())->findById($userId);

        if ($user === null) {
            Response::json(['error' => 'User not found.'], 404);
            return;
        }

        $options = (new WebAuthnService())->registrationOptions($userId, $user['email'], $user['name']);
        $_SESSION['webauthn_registration_options'] = $options;

        Response::json(['options' => $options, 'csrf_token' => Csrf::token()]);
    }

    public function register(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $options = $_SESSION['webauthn_registration_options'] ?? null;
        if (!$options instanceof PublicKeyCredentialCreationOptions) {
            Response::json(['error' => 'No passkey registration in progress. Please try again.'], 422);
            return;
        }

        $credential = json_decode($request->post('credential'), true);
        if (!is_array($credential)) {
            Response::json(['error' => 'Invalid passkey response.'], 422);
            return;
        }

        $userId = (int) AuthMiddleware::userId();
        $deviceName = UserAgent::describe($_SERVER['HTTP_USER_AGENT'] ?? null);

        try {
            (new WebAuthnService())->verifyRegistration($credential, $options, $deviceName);
        } catch (\Throwable $e) {
            Response::json(['error' => "Couldn't save that passkey. Please try again."], 422);
            return;
        } finally {
            unset($_SESSION['webauthn_registration_options']);
        }

        (new AuditLogRepository())->log(
            $userId,
            AuthMiddleware::householdId(),
            'webauthn.registered',
            'user',
            $userId,
            $request->ip(),
            ['device_name' => $deviceName]
        );

        Response::json(['success' => true, 'device_name' => $deviceName]);
    }

    public function destroy(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $userId = (int) AuthMiddleware::userId();
        $credentialId = (int) $request->param('id');

        if (!Csrf::verify($request->post('csrf_token'))) {
            $_SESSION['_flash_error'] = 'Your session expired. Please try again.';
            header('Location: /settings/security');
            return;
        }

        $deleted = (new WebAuthnCredentialRepository())->deleteForUser($credentialId, $userId);

        if ($deleted) {
            (new AuditLogRepository())->log(
                $userId,
                AuthMiddleware::householdId(),
                'webauthn.removed',
                'user',
                $userId,
                $request->ip()
            );
            $_SESSION['_flash_notice'] = 'Passkey removed.';
        }

        header('Location: /settings/security');
    }

    /**
     * Public — this is the login page's "Sign in with a passkey" flow,
     * requested before anyone is authenticated.
     */
    public function loginOptions(): void
    {
        if (!empty($_SESSION['user_id'])) {
            Response::json(['error' => 'Already signed in.'], 400);
            return;
        }

        $options = (new WebAuthnService())->authenticationOptions();
        $_SESSION['webauthn_login_options'] = $options;

        Response::json(['options' => $options, 'csrf_token' => Csrf::token()]);
    }

    public function loginVerify(Request $request): void
    {
        if (!empty($_SESSION['user_id'])) {
            Response::json(['error' => 'Already signed in.'], 400);
            return;
        }

        if (!Csrf::verify($request->post('csrf_token'))) {
            Response::json(['error' => 'Your session expired. Please refresh and try again.'], 419);
            return;
        }

        $options = $_SESSION['webauthn_login_options'] ?? null;
        if (!$options instanceof PublicKeyCredentialRequestOptions) {
            Response::json(['error' => 'No passkey sign-in in progress. Please try again.'], 422);
            return;
        }

        $credential = json_decode($request->post('credential'), true);
        if (!is_array($credential)) {
            Response::json(['error' => 'Invalid passkey response.'], 422);
            return;
        }

        unset($_SESSION['webauthn_login_options']);

        try {
            $result = (new WebAuthnService())->verifyAuthentication($credential, $options);
        } catch (\Throwable $e) {
            (new AuditLogRepository())->log(null, null, 'login.webauthn_failed', 'user', null, $request->ip());
            Response::json(['error' => "That passkey wasn't recognized. Please try again or use your password."], 422);
            return;
        }

        $user = (new UserRepository())->findById($result['userId']);

        if ($user === null || (int) $user['is_active'] !== 1) {
            Response::json(['error' => "That passkey wasn't recognized. Please try again or use your password."], 422);
            return;
        }

        (new UserRepository())->updateLastLogin((int) $user['id']);

        // A passkey inherently combines "something you have" (the
        // device) with "something you are/know" (biometric or device
        // PIN) — industry-standard treatment is that it satisfies MFA
        // on its own. webauthn_skip_two_factor (default on) lets a user
        // opt back into a separate TOTP prompt even after a passkey if
        // they want the extra step; off by default so this actually
        // delivers the "no typing on my phone" outcome that was asked
        // for.
        if ($user['two_factor_enabled_at'] !== null && (int) $user['webauthn_skip_two_factor'] !== 1) {
            session_regenerate_id(true);
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            $_SESSION['pending_2fa_email'] = $user['email'];
            Response::json(['redirect' => '/login/verify']);
            return;
        }

        // completeLogin() also sets a "Location: /" header as a leftover
        // of it being written for the password flow's real redirect —
        // harmless here since fetch() never follows headers on a 200
        // JSON response, but the actual navigation this flow relies on
        // is the redirect field below, read by webauthn.js.
        (new AuthController())->completeLogin($user, $request, new HouseholdRepository(), new AuditLogRepository());

        Response::json(['redirect' => '/']);
    }
}
