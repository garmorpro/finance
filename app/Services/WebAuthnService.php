<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WebAuthnCredentialRepository;
use Cose\Algorithm\Manager as CoseAlgorithmManager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * All of this app's web-auth/webauthn-lib usage lives in this one class
 * deliberately, so if a method signature doesn't match the exact 4.x
 * version `composer update` resolves, there's exactly one file to fix
 * rather than library calls scattered across a controller.
 *
 * Design decisions baked in here, matching the schema laid down in the
 * WebAuthn foundation commit:
 * - Attestation is always requested as "none" — this app has no fleet
 *   policy that cares which authenticator model was used, so there's
 *   nothing to gain from verifying an attestation chain, only user
 *   friction and a privacy cost (attestation can fingerprint hardware).
 * - `residentKey: required` + `userVerification: required` — a
 *   discoverable/resident credential is what lets the login page offer
 *   "Sign in with a passkey" without asking for an email first (the
 *   browser's own passkey picker shows every credential registered for
 *   this site), and userVerification=required means the platform must
 *   actually enforce Face ID/Touch ID/PIN, not just presence.
 * - The relying party ID is the app's own host (from APP_URL) with no
 *   scheme/port — WebAuthn ties every credential to this exact string,
 *   so it must never change once passkeys exist, or every registered
 *   passkey stops validating.
 */
final class WebAuthnService
{
    private WebAuthnCredentialRepository $credentials;

    public function __construct(?WebAuthnCredentialRepository $credentials = null)
    {
        $this->credentials = $credentials ?? new WebAuthnCredentialRepository();
    }

    private function rpId(): string
    {
        // The browser's own Host header is the source of truth for "what
        // origin is this actually being served from" — the one thing
        // WebAuthn's rp.id must match. APP_URL is only a fallback for the
        // rare case a request somehow arrives with no Host header at
        // all; it was wrongly given priority before, and on at least
        // this server $_ENV['APP_URL'] wasn't resolving to anything
        // usable, silently falling through to a hardcoded "localhost".
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            // Host can include a port ("example.com:8443") — rp.id must
            // be a bare hostname, no port or scheme.
            return explode(':', $host)[0];
        }

        $fallback = parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST);

        return is_string($fallback) && $fallback !== '' ? $fallback : 'localhost';
    }

    private function rpEntity(): PublicKeyCredentialRpEntity
    {
        // Confirmed against this library's actual output (not just
        // assumed from spec field order, which turned out backwards
        // from the constructor's real parameter order): create(name, id).
        return PublicKeyCredentialRpEntity::create('MyCFO+', $this->rpId());
    }

    /**
     * Registration ceremony, step 1 — the options JS passes to
     * navigator.credentials.create(). Excludes credentials the user
     * already has registered so the same authenticator can't be added
     * twice, and never asks for attestation (see class doc comment).
     */
    public function registrationOptions(int $userId, string $email, string $displayName): PublicKeyCredentialCreationOptions
    {
        // Same create(name, id, displayName) order as the RP entity —
        // name is the human-readable identifier shown in the platform's
        // passkey picker (the email), id is the opaque handle stored
        // alongside the credential (this app's own user id).
        $userEntity = PublicKeyCredentialUserEntity::create($email, (string) $userId, $displayName);

        $existing = $this->credentials->findAllForUserEntity($userEntity);
        $excludeCredentials = array_map(
            fn (PublicKeyCredentialSource $source): PublicKeyCredentialDescriptor => $source->getPublicKeyCredentialDescriptor(),
            $existing
        );

        $authenticatorSelection = AuthenticatorSelectionCriteria::create()
            ->setUserVerification(AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED)
            ->setResidentKey(AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED);

        return PublicKeyCredentialCreationOptions::create(
            $this->rpEntity(),
            $userEntity,
            random_bytes(32),
            [
                PublicKeyCredentialParameters::create('public-key', ES256::ID),
                PublicKeyCredentialParameters::create('public-key', RS256::ID),
            ],
            $authenticatorSelection,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $excludeCredentials,
        );
    }

    /**
     * Registration ceremony, step 2 — verifies the browser's response
     * against the options from step 1 (the caller is responsible for
     * having stored those in session and passing the same object back)
     * and, once valid, saves the new credential with a device label.
     *
     * @param array<string, mixed> $credentialResponse Decoded JSON body from navigator.credentials.create()
     *
     * @throws \Throwable on any verification failure — the caller
     *         decides how to present that to the user, this method
     *         never silently treats a failed check as success.
     */
    public function verifyRegistration(
        array $credentialResponse,
        PublicKeyCredentialCreationOptions $expectedOptions,
        string $deviceName
    ): void {
        $publicKeyCredential = $this->loader()->loadArray($credentialResponse);
        $response = $publicKeyCredential->response;

        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Expected an attestation response for a registration ceremony.');
        }

        $csmFactory = new CeremonyStepManagerFactory();
        $validator = AuthenticatorAttestationResponseValidator::create($csmFactory->creationCeremony());

        $source = $validator->check($response, $expectedOptions, $this->rpId());
        $source->otherUI = ['device_name' => $deviceName !== '' ? $deviceName : 'Passkey'];

        $this->credentials->saveCredentialSource($source);
    }

    /**
     * Authentication ceremony, step 1 — options for a *usernameless*
     * sign-in (no allowCredentials list), relying entirely on the
     * resident/discoverable credentials registration required. The
     * browser's own passkey picker shows whichever passkeys exist for
     * this rpId; the assertion response tells us afterward which user
     * it was (see verifyAuthentication()).
     */
    public function authenticationOptions(): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId(),
            [],
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        );
    }

    /**
     * Authentication ceremony, step 2 — verifies the assertion and
     * returns which user just authenticated. Throws on any failure,
     * same contract as verifyRegistration().
     *
     * @param array<string, mixed> $credentialResponse Decoded JSON body from navigator.credentials.get()
     *
     * @return array{userId: int, credentialRowId: ?int}
     */
    public function verifyAuthentication(array $credentialResponse, PublicKeyCredentialRequestOptions $expectedOptions): array
    {
        $publicKeyCredential = $this->loader()->loadArray($credentialResponse);
        $response = $publicKeyCredential->response;

        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Expected an assertion response for an authentication ceremony.');
        }

        $credentialIdRaw = $publicKeyCredential->rawId;
        $source = $this->credentials->findOneByCredentialId($credentialIdRaw);

        if ($source === null) {
            throw new \RuntimeException('Unrecognized passkey.');
        }

        $csmFactory = new CeremonyStepManagerFactory();
        $validator = AuthenticatorAssertionResponseValidator::create($csmFactory->requestCeremony());

        // userHandle from the platform must match the stored owner —
        // check() itself verifies the signature/counter/rpId, but this
        // app also wants the plain user id back to establish a session.
        $source = $validator->check(
            $source,
            $response,
            $expectedOptions,
            $this->rpId(),
            $response->userHandle,
        );

        return [
            'userId' => (int) $source->userHandle,
            'credentialRowId' => null,
        ];
    }

    private function loader(): PublicKeyCredentialLoader
    {
        return PublicKeyCredentialLoader::create();
    }
}
