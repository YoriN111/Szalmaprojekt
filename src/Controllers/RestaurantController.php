<?php
namespace App\Controllers;

use App\Response;
use App\Models\Restaurant;
use App\Middleware\AuthMiddleware;
use OpenApi\Attributes as OA;

class RestaurantController
{
    #[OA\Get(
        path: '/api/restaurants',
        operationId: 'listRestaurants',
        summary: 'List all restaurants',
        tags: ['Restaurants'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of restaurants',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status',  type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string',  example: 'OK'),
                        new OA\Property(property: 'data',    type: 'array', items: new OA\Items(ref: '#/components/schemas/Restaurant')),
                    ]
                )
            ),
        ]
    )]
    public function index(array $request): void
    {
        Response::json(Restaurant::findAll());
    }

    #[OA\Get(
        path: '/api/restaurants/{id}',
        operationId: 'getRestaurant',
        summary: 'Get a single restaurant',
        tags: ['Restaurants'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Restaurant data', content: new OA\JsonContent(ref: '#/components/schemas/Restaurant')),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(array $request): void
    {
        $r = Restaurant::findById((int)$request['params']['id']);
        if (!$r) {
            Response::error('Restaurant not found', 404);
        }
        Response::json($r);
    }

    #[OA\Post(
        path: '/api/restaurants',
        operationId: 'createRestaurant',
        summary: 'Create a restaurant (admin only)',
        tags: ['Restaurants'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'address', 'phone'],
                properties: [
                    new OA\Property(property: 'name',    type: 'string', example: 'Pizza Palace'),
                    new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                    new OA\Property(property: 'phone',   type: 'string', example: '+36 1 234 5678'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Restaurant created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Admin role required'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(array $request): void
    {
        $user = AuthMiddleware::handle($request, ['admin']);
        $body = $request['body'];

        foreach (['name', 'address', 'phone'] as $f) {
            if (empty($body[$f])) {
                Response::error("Field '{$f}' is required", 422);
            }
        }

        $id = Restaurant::create($body, (int)$user->sub);
        Response::json(Restaurant::findById($id), 201, 'Created');
    }

    #[OA\Put(
        path: '/api/restaurants/{id}',
        operationId: 'updateRestaurant',
        summary: 'Update own restaurant (admin only)',
        tags: ['Restaurants'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',    type: 'string', example: 'Pizza Palace'),
                    new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                    new OA\Property(property: 'phone',   type: 'string', example: '+36 1 234 5678'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Restaurant updated'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Admin role required'),
            new OA\Response(response: 404, description: 'Not found or not owner'),
        ]
    )]
    public function update(array $request): void
    {
        $user    = AuthMiddleware::handle($request, ['admin']);
        $updated = Restaurant::update(
            (int)$request['params']['id'],
            $request['body'],
            (int)$user->sub
        );

        if (!$updated) {
            Response::error('Restaurant not found or you do not own it', 404);
        }
        Response::json(Restaurant::findById((int)$request['params']['id']));
    }

    #[OA\Delete(
        path: '/api/restaurants/{id}',
        operationId: 'deleteRestaurant',
        summary: 'Delete own restaurant (admin only)',
        tags: ['Restaurants'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Admin role required'),
            new OA\Response(response: 404, description: 'Not found or not owner'),
        ]
    )]
    public function destroy(array $request): void
    {
        $user    = AuthMiddleware::handle($request, ['admin']);
        $deleted = Restaurant::delete((int)$request['params']['id'], (int)$user->sub);

        if (!$deleted) {
            Response::error('Restaurant not found or you do not own it', 404);
        }
        Response::noContent();
    }
}
