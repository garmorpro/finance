<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\FieldCipher;
use PHPUnit\Framework\TestCase;

final class FieldCipherTest extends TestCase
{
    private ?string $originalKey;

    protected function setUp(): void
    {
        $this->originalKey = $_ENV['ENCRYPTION_KEY'] ?? null;
        $_ENV['ENCRYPTION_KEY'] = base64_encode(sodium_crypto_secretbox_keygen());
    }

    protected function tearDown(): void
    {
        if ($this->originalKey === null) {
            unset($_ENV['ENCRYPTION_KEY']);
        } else {
            $_ENV['ENCRYPTION_KEY'] = $this->originalKey;
        }
    }

    public function test_round_trips_a_normal_string(): void
    {
        $encrypted = FieldCipher::encrypt('Bank of America');

        $this->assertNotNull($encrypted);
        $this->assertNotSame('Bank of America', $encrypted);
        $this->assertSame('Bank of America', FieldCipher::decrypt($encrypted));
    }

    public function test_null_passes_through_both_directions(): void
    {
        $this->assertNull(FieldCipher::encrypt(null));
        $this->assertNull(FieldCipher::decrypt(null));
    }

    public function test_empty_string_is_preserved_distinctly_from_null(): void
    {
        $encrypted = FieldCipher::encrypt('');

        $this->assertNotNull($encrypted);
        $this->assertSame('', FieldCipher::decrypt($encrypted));
    }

    public function test_round_trips_multibyte_and_emoji(): void
    {
        $value = "Café账户 💰 mixed";
        $this->assertSame($value, FieldCipher::decrypt(FieldCipher::encrypt($value)));
    }

    public function test_round_trips_long_text_with_binary_looking_bytes(): void
    {
        $value = str_repeat("Notes with special chars: \x00\xff\n\t. ", 50);
        $this->assertSame($value, FieldCipher::decrypt(FieldCipher::encrypt($value)));
    }

    public function test_encrypting_the_same_value_twice_produces_different_ciphertext(): void
    {
        // Each call gets a fresh random nonce — same plaintext must never
        // produce the same stored bytes twice.
        $a = FieldCipher::encrypt('same value');
        $b = FieldCipher::encrypt('same value');

        $this->assertNotSame($a, $b);
        $this->assertSame('same value', FieldCipher::decrypt($a));
        $this->assertSame('same value', FieldCipher::decrypt($b));
    }

    public function test_decrypting_with_the_wrong_key_throws_rather_than_returning_garbage(): void
    {
        $encrypted = FieldCipher::encrypt('sensitive value');

        $_ENV['ENCRYPTION_KEY'] = base64_encode(sodium_crypto_secretbox_keygen());

        $this->expectException(\RuntimeException::class);
        FieldCipher::decrypt($encrypted);
    }

    public function test_decrypting_tampered_ciphertext_throws(): void
    {
        $encrypted = FieldCipher::encrypt('sensitive value');
        $tampered = $encrypted;
        $tampered[30] = $tampered[30] === "\x00" ? "\x01" : "\x00";

        $this->expectException(\RuntimeException::class);
        FieldCipher::decrypt($tampered);
    }

    public function test_decrypting_a_too_short_value_throws_instead_of_reading_out_of_bounds(): void
    {
        $this->expectException(\RuntimeException::class);
        FieldCipher::decrypt('short');
    }

    public function test_encrypt_throws_when_no_key_is_configured(): void
    {
        unset($_ENV['ENCRYPTION_KEY']);

        $this->assertFalse(FieldCipher::isConfigured());

        $this->expectException(\RuntimeException::class);
        FieldCipher::encrypt('anything');
    }

    public function test_decrypt_throws_when_no_key_is_configured(): void
    {
        $encrypted = FieldCipher::encrypt('anything');
        unset($_ENV['ENCRYPTION_KEY']);

        $this->expectException(\RuntimeException::class);
        FieldCipher::decrypt($encrypted);
    }

    public function test_is_configured_rejects_a_malformed_key(): void
    {
        $_ENV['ENCRYPTION_KEY'] = 'not-a-valid-base64-32-byte-key';

        $this->assertFalse(FieldCipher::isConfigured());
    }
}
