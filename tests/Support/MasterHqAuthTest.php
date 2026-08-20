<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Request;
use App\Support\MasterHqAuth;
use PHPUnit\Framework\TestCase;

final class MasterHqAuthTest extends TestCase
{
    private ?string $originalToken;

    protected function setUp(): void
    {
        $this->originalToken = $_ENV['MASTER_HQ_API_TOKEN'] ?? null;
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        if ($this->originalToken === null) {
            unset($_ENV['MASTER_HQ_API_TOKEN']);
        } else {
            $_ENV['MASTER_HQ_API_TOKEN'] = $this->originalToken;
        }
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function test_correct_token_is_authorized(): void
    {
        $_ENV['MASTER_HQ_API_TOKEN'] = 'correct-horse-battery-staple';

        $this->assertTrue(MasterHqAuth::isAuthorized('correct-horse-battery-staple'));
    }

    public function test_wrong_token_is_not_authorized(): void
    {
        $_ENV['MASTER_HQ_API_TOKEN'] = 'correct-horse-battery-staple';

        $this->assertFalse(MasterHqAuth::isAuthorized('some-other-token'));
    }

    public function test_missing_token_is_not_authorized(): void
    {
        $_ENV['MASTER_HQ_API_TOKEN'] = 'correct-horse-battery-staple';

        $this->assertFalse(MasterHqAuth::isAuthorized(null));
        $this->assertFalse(MasterHqAuth::isAuthorized(''));
    }

    public function test_unconfigured_token_fails_closed_even_against_an_empty_provided_token(): void
    {
        unset($_ENV['MASTER_HQ_API_TOKEN']);

        // Both sides empty must never compare "equal" and pass — an
        // unconfigured endpoint must reject everyone, not accept anyone
        // who also sends nothing.
        $this->assertFalse(MasterHqAuth::isAuthorized(''));
        $this->assertFalse(MasterHqAuth::isAuthorized(null));
        $this->assertFalse(MasterHqAuth::isAuthorized('anything'));
    }

    public function test_bearer_token_is_extracted_from_the_authorization_header(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123';

        $this->assertSame('abc123', MasterHqAuth::bearerToken(new Request()));
    }

    public function test_bearer_token_is_null_when_header_is_missing(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $this->assertNull(MasterHqAuth::bearerToken(new Request()));
    }

    public function test_bearer_token_is_null_for_a_non_bearer_scheme(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic abc123';

        $this->assertNull(MasterHqAuth::bearerToken(new Request()));
    }

    public function test_end_to_end_authorized_request_succeeds(): void
    {
        $_ENV['MASTER_HQ_API_TOKEN'] = 'correct-horse-battery-staple';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer correct-horse-battery-staple';

        $this->assertTrue(MasterHqAuth::isAuthorized(MasterHqAuth::bearerToken(new Request())));
    }

    public function test_end_to_end_unauthorized_request_fails(): void
    {
        $_ENV['MASTER_HQ_API_TOKEN'] = 'correct-horse-battery-staple';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong-token';

        $this->assertFalse(MasterHqAuth::isAuthorized(MasterHqAuth::bearerToken(new Request())));
    }
}
