<?php
namespace App\Services;

use App\Database;

class RateLimiter
{
    public static function hit(string $ip, string $action): void
    {
        try {
            $pdo = Database::getInstance();

            // Prune rows outside the maximum window we'd ever query (15 min default)
            $pdo->prepare(
                'DELETE FROM rate_limits WHERE ip = ? AND action = ? AND created_at < DATE_SUB(NOW(), INTERVAL 900 SECOND)'
            )->execute([$ip, $action]);

            $pdo->prepare(
                'INSERT INTO rate_limits (ip, action) VALUES (?, ?)'
            )->execute([$ip, $action]);
        } catch (\Throwable) {
        }
    }

    public static function tooMany(string $ip, string $action, int $max = 5, int $windowSeconds = 900): bool
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT COUNT(*) FROM rate_limits
                  WHERE ip = ? AND action = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
            );
            $stmt->execute([$ip, $action, $windowSeconds]);
            return (int)$stmt->fetchColumn() >= $max;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function clear(string $ip, string $action): void
    {
        try {
            Database::getInstance()->prepare(
                'DELETE FROM rate_limits WHERE ip = ? AND action = ?'
            )->execute([$ip, $action]);
        } catch (\Throwable) {
        }
    }
}
