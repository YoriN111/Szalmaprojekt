<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Router;
use App\Response;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        Response::enableTestingMode();
    }

    protected function tearDown(): void
    {
        Response::disableTestingMode();
    }

    public function test_matches_static_route(): void
    {
        $called = false;
        $this->router->add('GET', '/api/restaurants', function (array $req) use (&$called) {
            $called = true;
        });

        $this->router->dispatch('GET', '/api/restaurants');

        $this->assertTrue($called);
    }

    public function test_matches_route_with_param(): void
    {
        $capturedId = null;
        $this->router->add('GET', '/api/restaurants/{id}', function (array $req) use (&$capturedId) {
            $capturedId = $req['params']['id'];
        });

        $this->router->dispatch('GET', '/api/restaurants/42');

        $this->assertSame('42', $capturedId);
    }

    public function test_does_not_match_wrong_method(): void
    {
        $called = false;
        $this->router->add('POST', '/api/restaurants', function (array $req) use (&$called) {
            $called = true;
        });

        ob_start();
        try {
            $this->router->dispatch('GET', '/api/restaurants');
        } catch (\RuntimeException) {}
        ob_end_clean();

        $this->assertFalse($called);
    }

    public function test_returns_404_for_unknown_route(): void
    {
        ob_start();
        try {
            $this->router->dispatch('GET', '/api/unknown');
        } catch (\RuntimeException) {}
        $output = ob_get_clean();
        $decoded = json_decode($output, true);

        $this->assertSame(404, $decoded['status']);
    }

    public function test_strips_query_string_from_uri(): void
    {
        $called = false;
        $this->router->add('GET', '/api/restaurants', function (array $req) use (&$called) {
            $called = true;
        });

        $this->router->dispatch('GET', '/api/restaurants?page=2');

        $this->assertTrue($called);
    }
}
