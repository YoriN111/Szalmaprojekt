<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Auth;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
        $_ENV['JWT_EXPIRY'] = '3600';
    }

    public function test_encode_returns_jwt_string(): void
    {
        $token = Auth::encode(['sub' => 1, 'role' => 'customer']);

        $this->assertIsString($token);
        // JWT has exactly 3 parts separated by dots
        $this->assertCount(3, explode('.', $token));
    }

    public function test_decode_returns_original_payload(): void
    {
        $token   = Auth::encode(['sub' => 5, 'role' => 'admin']);
        $payload = Auth::decode($token);

        $this->assertSame(5, $payload->sub);
        $this->assertSame('admin', $payload->role);
    }

    public function test_decode_throws_on_invalid_token(): void
    {
        $this->expectException(\Exception::class);
        Auth::decode('not.a.valid.token');
    }

    public function test_token_contains_expiry(): void
    {
        $before = time();
        $token  = Auth::encode(['sub' => 1, 'role' => 'customer']);
        $after  = time();

        $payload = Auth::decode($token);

        $this->assertGreaterThanOrEqual($before + 3600, $payload->exp);
        $this->assertLessThanOrEqual($after + 3600, $payload->exp);
    }
}
