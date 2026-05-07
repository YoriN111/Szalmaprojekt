<?php
namespace App\Controllers;

use App\Response;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Middleware\AuthMiddleware;
use OpenApi\Attributes as OA;

class MenuController
{
    #[OA\Get(
        path: '/api/restaurants/{id}/menu',
        operationId: 'listMenuItems',
        summary: 'List menu items for a restaurant',
        tags: ['Menu'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Restaurant ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of menu items',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status',  type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string',  example: 'OK'),
                        new OA\Property(property: 'data',    type: 'array', items: new OA\Items(ref: '#/components/schemas/MenuItem')),
                    ]
                )
            ),
        ]
    )]
    public function index(array $request): void
    {
        $items = MenuItem::findByRestaurant((int)$request['params']['id']);
        Response::json($items);
    }

    #[OA\Post(
        path: '/api/restaurants/{id}/menu',
        operationId: 'createMenuItem',
        summary: 'Add a menu item (admin, own restaurant)',
        tags: ['Menu'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Restaurant ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price'],
                properties: [
                    new OA\Property(property: 'name',        type: 'string', example: 'Margherita Pizza'),
                    new OA\Property(property: 'description', type: 'string', example: 'Tomato and mozzarella'),
                    new OA\Property(property: 'price',       type: 'number', format: 'float', example: 12.50),
                    new OA\Property(property: 'available',   type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Menu item created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Not owner of restaurant'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(array $request): void
    {
        $user = AuthMiddleware::handle($request, ['admin']);
        $body = $request['body'];

        foreach (['name', 'price'] as $f) {
            if (empty($body[$f])) {
                Response::error("Field '{$f}' is required", 422);
            }
        }

        $r = Restaurant::findById((int)$request['params']['id']);
        if (!$r || (int)$r['admin_id'] !== (int)$user->sub) {
            Response::error('Forbidden — you do not own this restaurant', 403);
        }

        $id = MenuItem::create($body, (int)$request['params']['id']);
        Response::json(MenuItem::findById($id), 201, 'Created');
    }

    #[OA\Put(
        path: '/api/restaurants/{id}/menu/{mid}',
        operationId: 'updateMenuItem',
        summary: 'Update a menu item (admin, own restaurant)',
        tags: ['Menu'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id',  in: 'path', required: true, description: 'Restaurant ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'mid', in: 'path', required: true, description: 'Menu item ID',  schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',        type: 'string',  example: 'Margherita Pizza'),
                    new OA\Property(property: 'description', type: 'string',  example: 'Tomato and mozzarella'),
                    new OA\Property(property: 'price',       type: 'number',  format: 'float', example: 12.50),
                    new OA\Property(property: 'available',   type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Menu item updated'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Not owner of restaurant'),
            new OA\Response(response: 404, description: 'Menu item not found'),
        ]
    )]
    public function update(array $request): void
    {
        $user = AuthMiddleware::handle($request, ['admin']);

        $r = Restaurant::findById((int)$request['params']['id']);
        if (!$r || (int)$r['admin_id'] !== (int)$user->sub) {
            Response::error('Forbidden — you do not own this restaurant', 403);
        }

        $updated = MenuItem::update(
            (int)$request['params']['mid'],
            (int)$request['params']['id'],
            $request['body']
        );

        if (!$updated) {
            Response::error('Menu item not found', 404);
        }
        Response::json(MenuItem::findById((int)$request['params']['mid']));
    }

    #[OA\Delete(
        path: '/api/restaurants/{id}/menu/{mid}',
        operationId: 'deleteMenuItem',
        summary: 'Delete a menu item (admin, own restaurant)',
        tags: ['Menu'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id',  in: 'path', required: true, description: 'Restaurant ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'mid', in: 'path', required: true, description: 'Menu item ID',  schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Not owner of restaurant'),
            new OA\Response(response: 404, description: 'Menu item not found'),
        ]
    )]
    public function destroy(array $request): void
    {
        $user = AuthMiddleware::handle($request, ['admin']);

        $r = Restaurant::findById((int)$request['params']['id']);
        if (!$r || (int)$r['admin_id'] !== (int)$user->sub) {
            Response::error('Forbidden — you do not own this restaurant', 403);
        }

        $deleted = MenuItem::delete(
            (int)$request['params']['mid'],
            (int)$request['params']['id']
        );

        if (!$deleted) {
            Response::error('Menu item not found', 404);
        }
        Response::noContent();
    }
}
