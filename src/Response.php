<?php
namespace App;

class Response
{
    private static bool $testing = false;

    public static function enableTestingMode(): void
    {
        self::$testing = true;
    }

    public static function disableTestingMode(): void
    {
        self::$testing = false;
    }

    public static function json(mixed $data = null, int $status = 200, string $message = 'OK'): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ]);

        // In test mode throw instead of exit so PHPUnit process stays alive
        if (self::$testing) {
            throw new \RuntimeException('Response sent', $status);
        }
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(null, $status, $message);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        if (self::$testing) {
            throw new \RuntimeException('Response sent', 204);
        }
        exit;
    }
}
