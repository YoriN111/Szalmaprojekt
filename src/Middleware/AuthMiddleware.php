<?php
namespace App\Middleware;

use App\Auth;
use App\Response;

class AuthMiddleware
{
    /**
     * Validates Bearer token and role. Returns JWT payload on success.
     * Calls Response::error() (exits) on failure — never returns null.
     */
    public static function handle(array $request, array $allowedRoles = []): object
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            Response::error('Unauthorized — missing or malformed Authorization header', 401);
        }

        try {
            $payload = Auth::decode($m[1]);
        } catch (\Exception $e) {
            Response::error('Unauthorized — invalid or expired token', 401);
        }

        if ($allowedRoles && !in_array($payload->role, $allowedRoles, true)) {
            Response::error('Forbidden — insufficient role', 403);
        }

        return $payload;
    }
}
