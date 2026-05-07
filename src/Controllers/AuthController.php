<?php
namespace App\Controllers;

use App\Auth;
use App\Response;
use App\Models\User;
use App\Services\Mailer;
use App\Services\DeviceDetector;
use App\Services\RateLimiter;
use OpenApi\Attributes as OA;

class AuthController
{
    #[OA\Post(
        path: '/api/auth/register',
        operationId: 'register',
        summary: 'Register a new user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name',     type: 'string',  example: 'John Doe'),
                    new OA\Property(property: 'email',    type: 'string',  format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string',  format: 'password', example: 'secret123'),
                    new OA\Property(property: 'role',     type: 'string',  enum: ['customer', 'driver'], example: 'customer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created — verification email sent'),
            new OA\Response(response: 409, description: 'Email already registered'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function register(array $request): void
    {
        $body = $request['body'];

        foreach (['name', 'email', 'password'] as $field) {
            if (empty($body[$field])) {
                Response::error("Field '{$field}' is required", 422);
            }
        }
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', 422);
        }
        if (strlen($body['password']) < 6) {
            Response::error('Password must be at least 6 characters', 422);
        }
        if (User::findByEmail($body['email'])) {
            Response::error('Email already registered', 409);
        }

        $allowedRoles = ['customer', 'driver'];
        $role = in_array($body['role'] ?? 'customer', $allowedRoles, true)
            ? $body['role']
            : 'customer';

        $id   = User::create([
            'name'     => $body['name'],
            'email'    => $body['email'],
            'password' => $body['password'],
            'role'     => $role,
        ]);

        $token = bin2hex(random_bytes(32));
        User::setEmailVerifyToken($id, $token);

        $link = rtrim($_ENV['APP_URL'] ?? '', '/') . '/api/auth/verify-email?token=' . $token;

        try {
            Mailer::sendEmailVerification($body['email'], $body['name'], $link);
        } catch (\Throwable) {
        }

        $user = User::findById($id);
        Response::json(['user' => $user, 'message' => 'Check your email to verify your account.'], 201, 'Created');
    }

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'login',
        summary: 'Login and receive a JWT token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email',    type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful — returns JWT token'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 403, description: 'Email not verified'),
            new OA\Response(response: 422, description: 'Missing fields'),
            new OA\Response(response: 429, description: 'Too many login attempts'),
        ]
    )]
    public function login(array $request): void
    {
        $body = $request['body'];
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (empty($body['email']) || empty($body['password'])) {
            Response::error('Email and password are required', 422);
        }

        if (RateLimiter::tooMany($ip, 'login')) {
            Response::error('Too many login attempts. Try again later.', 429);
        }

        $user = User::findByEmail($body['email']);
        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            RateLimiter::hit($ip, 'login');
            Response::error('Invalid credentials', 401);
        }

        if (empty($user['email_verified_at'])) {
            RateLimiter::hit($ip, 'login');
            Response::error('Please verify your email address before logging in.', 403);
        }

        RateLimiter::clear($ip, 'login');

        $token = Auth::encode(['sub' => $user['id'], 'role' => $user['role']]);

        try {
            $device = DeviceDetector::detect();
            Mailer::sendLoginNotification($user['email'], $user['name'], $ip, $device);
        } catch (\Throwable) {
        }

        Response::json([
            'token' => $token,
            'user'  => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/auth/verify-email',
        operationId: 'verifyEmail',
        summary: 'Verify email address via token from email link',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Email verified'),
            new OA\Response(response: 400, description: 'Invalid or expired token'),
            new OA\Response(response: 422, description: 'Token missing'),
        ]
    )]
    public function verifyEmail(array $request): void
    {
        $token = $request['query']['token'] ?? '';
        if (!$token) {
            Response::error('Token is required', 422);
        }

        $user = User::findByVerifyToken($token);
        if (!$user) {
            Response::error('Invalid or expired verification token', 400);
        }

        User::markEmailVerified($user['id']);
        Response::json(['message' => 'Email verified. You can now log in.']);
    }

    #[OA\Post(
        path: '/api/auth/forgot-password',
        operationId: 'forgotPassword',
        summary: 'Request a password reset link',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reset link sent (always same response to prevent enumeration)'),
            new OA\Response(response: 422, description: 'Invalid email format'),
        ]
    )]
    public function forgotPassword(array $request): void
    {
        $email = $request['body']['email'] ?? '';
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Valid email is required', 422);
        }

        RateLimiter::hit($ip, 'forgot');

        if (RateLimiter::tooMany($ip, 'forgot')) {
            Response::json(['message' => 'If that email is registered, a reset link has been sent.']);
        }

        $user = User::findByEmail($email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            User::setResetToken($user['id'], $token);
            $link = rtrim($_ENV['APP_URL'] ?? '', '/') . '/api/auth/reset-password?token=' . $token;

            try {
                Mailer::sendPasswordReset($user['email'], $user['name'], $link);
            } catch (\Throwable) {
            }
        }

        Response::json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    #[OA\Post(
        path: '/api/auth/reset-password',
        operationId: 'resetPassword',
        summary: 'Reset password using token from email',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password'],
                properties: [
                    new OA\Property(property: 'token',    type: 'string', example: 'abc123...'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset successfully'),
            new OA\Response(response: 400, description: 'Invalid or expired token'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function resetPassword(array $request): void
    {
        $token    = $request['body']['token']    ?? '';
        $password = $request['body']['password'] ?? '';

        if (!$token) {
            Response::error('Token is required', 422);
        }
        if (strlen($password) < 6) {
            Response::error('Password must be at least 6 characters', 422);
        }

        $user = User::findByResetToken($token);
        if (!$user) {
            Response::error('Invalid or expired reset token', 400);
        }

        User::update($user['id'], ['password' => $password]);
        User::clearResetToken($user['id']);

        Response::json(['message' => 'Password reset successfully. You can now log in.']);
    }
}
