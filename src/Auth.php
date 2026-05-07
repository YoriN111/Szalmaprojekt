<?php
namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private static function secret(): string
    {
        return $_ENV['JWT_SECRET'];
    }

    public static function encode(array $payload): string
    {
        $payload['exp'] = time() + (int)($_ENV['JWT_EXPIRY'] ?? 3600);
        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function decode(string $token): object
    {
        return JWT::decode($token, new Key(self::secret(), 'HS256'));
    }
}
