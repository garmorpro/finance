<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health';
    }

    public function test_it_dispatches_a_matching_route_to_its_handler(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/health', function () use (&$called): void {
            $called = true;
        });

        ob_start();
        $router->dispatch(new \App\Http\Request());
        ob_end_clean();

        $this->assertTrue($called);
    }

    public function test_it_returns_404_json_for_an_unmatched_route(): void
    {
        $_SERVER['REQUEST_URI'] = '/does-not-exist';

        $router = new Router();

        ob_start();
        $router->dispatch(new \App\Http\Request());
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertSame('Not found', $decoded['error']);
    }
}
