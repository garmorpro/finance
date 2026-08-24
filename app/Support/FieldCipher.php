<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Application-level encryption for specific "identifying/descriptive
 * text" database columns (account names, institution names, notes,
 * goal names/descriptions, recurring item names/notes as of this
 * writing) — NOT for financial amounts, which stay plain DECIMAL
 * columns because budget/net-worth math runs as real SQL aggregation.
 * See docs/security.md's "Encryption at rest" section for the full
 * design and, importantly, its limits: this protects against someone
 * with database-only access (phpMyAdmin, a mysql shell, a stolen DB-only
 * backup) — it does NOT protect against a compromised app server, which
 * holds ENCRYPTION_KEY and needs to read this data to display it.
 *
 * sodium_crypto_secretbox (XSalsa20-Poly1305, authenticated) — built
 * into PHP's core libsodium extension, no Composer dependency. Every
 * value gets its own random nonce, stored immediately before the
 * ciphertext in one binary blob: [24-byte nonce][ciphertext+16-byte MAC].
 * A wrong key or corrupted/tampered data makes decrypt() throw rather
 * than silently return garbled bytes into what would otherwise look
 * like a real account name.
 */
final class FieldCipher
{
    public static function isConfigured(): bool
    {
        return self::resolveKey() !== null;
    }

    /**
     * Prefers the encrypted value, falling back to the old plaintext
     * column when a row hasn't been through bin/encrypt-existing-text-fields.php
     * yet. The one recurring shape every repository that reads an
     * encrypted column needs — including places that only read a JOINed
     * account's name (TransactionRepository, ImportRepository,
     * GoalRepository, RecurringItemRepository, ReportingService), not
     * just AccountRepository's own rows.
     */
    public static function decryptOrFallback(?string $encrypted, ?string $plaintext): ?string
    {
        return $encrypted !== null ? self::decrypt($encrypted) : $plaintext;
    }

    /**
     * @throws \RuntimeException if ENCRYPTION_KEY isn't configured —
     *     deliberately fatal rather than silently storing plaintext
     *     when a caller believes this is encrypting it.
     */
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null) {
            return null;
        }

        $key = self::requireKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return $nonce . $ciphertext;
    }

    /**
     * @throws \RuntimeException if ENCRYPTION_KEY isn't configured, the
     *     stored value is too short to be valid, or authentication fails
     *     (wrong key, or the bytes were corrupted/tampered with) — never
     *     returns anything other than the real original plaintext.
     */
    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null) {
            return null;
        }

        $key = self::requireKey();

        if (strlen($stored) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new \RuntimeException('FieldCipher::decrypt() received a value too short to be valid ciphertext.');
        }

        $nonce = substr($stored, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($stored, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            throw new \RuntimeException('FieldCipher::decrypt() failed authentication — wrong ENCRYPTION_KEY, or the stored value was corrupted or tampered with.');
        }

        return $plaintext;
    }

    private static function requireKey(): string
    {
        $key = self::resolveKey();

        if ($key === null) {
            throw new \RuntimeException('ENCRYPTION_KEY is not configured in .env — see .env.example for how to generate one.');
        }

        return $key;
    }

    /**
     * Not cached — re-reads $_ENV on every call. Deliberate: this keeps
     * tests able to swap ENCRYPTION_KEY between cases without a reset
     * hook, and base64_decode() of a 44-character string is cheap enough
     * that there's no real performance reason to cache it.
     */
    private static function resolveKey(): ?string
    {
        $encoded = $_ENV['ENCRYPTION_KEY'] ?? null;
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            Logger::error('ENCRYPTION_KEY is set but is not a valid base64-encoded ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . '-byte key.');
            return null;
        }

        return $decoded;
    }
}
