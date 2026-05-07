<?php
namespace App\Controllers;

use App\Response;
use App\Models\User;
use App\Middleware\AuthMiddleware;
use OpenApi\Attributes as OA;

class UserController
{
    #[OA\Get(
        path: '/api/users',
        operationId: 'listUsers',
        summary: 'List all users (admin only)',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status',  type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string',  example: 'OK'),
                        new OA\Property(property: 'data',    type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Admin role required'),
        ]
    )]
    public function index(array $request): void
    {
        AuthMiddleware::handle($request, ['admin']);
        Response::json(User::findAll());
    }

    #[OA\Get(
        path: '/api/users/{id}',
        operationId: 'getUser',
        summary: 'Get a user (own profile or admin)',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User data', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Can only view own profile'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(array $request): void
    {
        $caller   = AuthMiddleware::handle($request);
        $targetId = (int)$request['params']['id'];

        if ($caller->role !== 'admin' && (int)$caller->sub !== $targetId) {
            Response::error('Forbidden', 403);
        }

        $user = User::findById($targetId);
        if (!$user) {
            Response::error('User not found', 404);
        }
        Response::json($user);
    }

    #[OA\Put(
        path: '/api/users/{id}',
        operationId: 'updateUser',
        summary: 'Update a user (own profile or admin)',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',     type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email',    type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Can only update own profile'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(array $request): void
    {
        $caller   = AuthMiddleware::handle($request);
        $targetId = (int)$request['params']['id'];

        if ($caller->role !== 'admin' && (int)$caller->sub !== $targetId) {
            Response::error('Forbidden', 403);
        }

        User::update($targetId, $request['body']);
        $user = User::findById($targetId);
        if (!$user) {
            Response::error('User not found', 404);
        }
        Response::json($user);
    }

    #[OA\Delete(
        path: '/api/users/{id}',
        operationId: 'deleteUser',
        summary: 'Delete a user (admin only)',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Admin role required'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(array $request): void
    {
        AuthMiddleware::handle($request, ['admin']);
        $targetId = (int)$request['params']['id'];
        $user     = User::findById($targetId);

        if (!$user) {
            Response::error('User not found', 404);
        }
        User::delete($targetId);
        Response::noContent();
    }
}
