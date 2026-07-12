<?php

declare(strict_types=1);

namespace App\Support;

/**
 * RFC 6238 TOTP (the algorithm behind Google Authenticator, Authy, 1Password,
 * etc.) implemented directly rather than pulling in a Composer package —
 * it's straightforward HMAC-SHA1 math, well within what's reasonable to
 * own rather than add a dependency for. No QR code is generated (that
 * would need an image-generation dependency this app doesn't otherwise
 * need); the setup page shows the secret as text for manual entry
 * instead, which every mainstream authenticator app supports.
 */
final class Totp
{
    private const PERIOD_SECONDS = 30;
    private const DIGITS = 6;
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * The otpauth:// URI format authenticator apps recognize — useful if
     * a QR-code generator is ever added later, otherwise unused.
     */
    public static function provisioningUri(string $secret, string $accountLabel, string $issuer = 'Finance'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountLabel),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD_SECONDS
        );
    }

    /**
     * Checks the current 30-second window plus one on either side, to
     * tolerate normal clock drift between the server and the user's
     * phone without meaningfully weakening the code's short lifetime.
     */
    public static function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = time();
        for ($window = -1; $window <= 1; $window++) {
            if (hash_equals(self::generateCode($secret, $now + ($window * self::PERIOD_SECONDS)), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function generateCode(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, self::PERIOD_SECONDS);
        $binaryCounter = pack('N*', 0, $counter);

        $hash = hash_hmac('sha1', $binaryCounter, self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;

        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $binaryString = '';
        foreach (str_split($bytes) as $byte) {
            $binaryString .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binaryString, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $secret));

        $binaryString = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos(self::BASE32_ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($binaryString, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }
}
