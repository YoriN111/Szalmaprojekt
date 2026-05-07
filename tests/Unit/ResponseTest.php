<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Response;

class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        Response::enableTestingMode();
    }

    protected function tearDown(): void
    {
        Response::disableTestingMode();
    }

    private function capture(callable $fn): array
    {
        ob_start();
        try {
            $fn();
        } catch (\RuntimeException) {}
        return json_decode(ob_get_clean(), true);
    }

    public function test_json_outputs_correct_structure(): void
    {
        $decoded = $this->capture(fn() => Response::json(['id' => 1], 200, 'OK'));

        $this->assertSame(200, $decoded['status']);
        $this->assertSame('OK', $decoded['message']);
        $this->assertSame(['id' => 1], $decoded['data']);
    }

    public function test_error_outputs_null_data(): void
    {
        $decoded = $this->capture(fn() => Response::error('Not found', 404));

        $this->assertSame(404, $decoded['status']);
        $this->assertSame('Not found', $decoded['message']);
        $this->assertNull($decoded['data']);
    }
}
