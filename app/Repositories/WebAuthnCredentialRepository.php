<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Bridges web-auth/webauthn-lib's own repository interface to this app's
 * `webauthn_credentials` table, plus the extra methods the app itself
 * needs (listing/naming/removing passkeys from Settings → Security) that
 * aren't part of that interface.
 *
 * The library's interface methods (`findOneByCredentialId`,
 * `findAllForUserEntity`, `saveCredentialSource`) work with a raw binary
 * credential ID — stored here as base64 in `credential_id` for
 * portability, looked up by a SHA-256 hash column (`credential_id_hash`)
 * since MySQL can't put a plain UNIQUE index on a TEXT column without an
 * awkward prefix length, and the binary content varies in size.
 *
 * `PublicKeyCredentialUserEntity::$id` (the WebAuthn "user handle") is
 * just this app's own integer user id, cast to a string — it doesn't
 * need to be a separate random identifier the way some implementations
 * use, since it isn't secret and this app already has a stable, unique
 * per-user id.
 *
 * Attestation is always requested as "none" (see WebAuthnService), so
 * every stored credential's trust path is reconstructed as
 * EmptyTrustPath rather than persisting attestation details this app
 * never verifies.
 */
final class WebAuthnCredentialRepository implements PublicKeyCredentialSourceRepository
{
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM webauthn_credentials WHERE credential_id_hash = :hash'
        );
        $stmt->execute(['hash' => hash('sha256', $publicKeyCredentialId)]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return PublicKeyCredentialSource[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM webauthn_credentials WHERE user_id = :user_id ORDER BY created_at'
        );
        $stmt->execute(['user_id' => (int) $publicKeyCredentialUserEntity->id]);

        return array_map(fn (array $row): PublicKeyCredentialSource => $this->hydrate($row), $stmt->fetchAll());
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $credentialId = base64_encode($publicKeyCredentialSource->publicKeyCredentialId);
        $hash = hash('sha256', $publicKeyCredentialSource->publicKeyCredentialId);
        $now = gmdate('Y-m-d H:i:s');

        $existing = $this->findOneByCredentialId($publicKeyCredentialSource->publicKeyCredentialId);

        if ($existing !== null) {
            // Only the sign counter should ever change after registration
            // (bumped on every successful authentication, checked against
            // replay by the library) — everything else about a credential
            // is fixed at registration time.
            $stmt = Connection::get()->prepare(
                'UPDATE webauthn_credentials SET sign_count = :sign_count, last_used_at = :last_used_at WHERE credential_id_hash = :hash'
            );
            $stmt->execute([
                'sign_count' => $publicKeyCredentialSource->counter,
                'last_used_at' => $now,
                'hash' => $hash,
            ]);
            return;
        }

        $stmt = Connection::get()->prepare(
            'INSERT INTO webauthn_credentials (user_id, credential_id, credential_id_hash, public_key, aaguid, transports, sign_count, device_name, created_at)
             VALUES (:user_id, :credential_id, :hash, :public_key, :aaguid, :transports, :sign_count, :device_name, :created_at)'
        );
        $stmt->execute([
            'user_id' => (int) $publicKeyCredentialSource->userHandle,
            'credential_id' => $credentialId,
            'hash' => $hash,
            'public_key' => base64_encode($publicKeyCredentialSource->credentialPublicKey),
            'aaguid' => (string) $publicKeyCredentialSource->aaguid,
            'transports' => json_encode($publicKeyCredentialSource->transports),
            'sign_count' => $publicKeyCredentialSource->counter,
            // A registration ceremony's caller (WebAuthnController) sets
            // this on the object before saving — see
            // WebAuthnService::register(). Falls back to a generic label
            // only if that step is ever skipped.
            'device_name' => $publicKeyCredentialSource->otherUI['device_name'] ?? 'Passkey',
            'created_at' => $now,
        ]);
    }

    /**
     * For Settings → Security's passkey list — household-app-specific,
     * not part of the library's own interface.
     *
     * @return list<array{id: int, device_name: string, created_at: string, last_used_at: ?string}>
     */
    public function listForUser(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, device_name, created_at, last_used_at FROM webauthn_credentials WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function deleteForUser(int $credentialRowId, int $userId): bool
    {
        $stmt = Connection::get()->prepare(
            'DELETE FROM webauthn_credentials WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $credentialRowId, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function countForUser(int $userId): int
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    private function hydrate(array $row): PublicKeyCredentialSource
    {
        return PublicKeyCredentialSource::create(
            base64_decode($row['credential_id'], true),
            'public-key',
            json_decode($row['transports'], true) ?? [],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString($row['aaguid']),
            base64_decode($row['public_key'], true),
            (string) $row['user_id'],
            (int) $row['sign_count'],
        );
    }
}
