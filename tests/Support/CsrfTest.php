<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_it_generates_a_stable_token_within_a_session(): void
    {
        $first = Csrf::token();
        $second = Csrf::token();

        $this->assertSame($first, $second);
    }

    public function test_it_verifies_a_matching_token(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::verify($token));
    }

    public function test_it_rejects_a_missing_or_wrong_token(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::verify(null));
        $this->assertFalse(Csrf::verify('wrong-token'));
    }
}
