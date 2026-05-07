<?php
namespace App\Controllers;

use App\Response;
use App\Models\Order;
use App\Middleware\AuthMiddleware;
use OpenApi\Attributes as OA;

class OrderController
{
    #[OA\Get(
        path: '/api/orders',
        operationId: 'listOrders',
        summary: 'List orders (filtered by role)',
        description: 'Customers see own orders. Drivers see all active orders. Admins see everything.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of orders',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status',  type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string',  example: 'OK'),
                        new OA\Property(property: 'data',    type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(array $request): void
    {
        $user = AuthMiddleware::handle($request, ['customer', 'admin', 'driver']);

        if ($user->role === 'customer') {
            $orders = Order::findByCustomer((int)$user->sub);
        } elseif ($user->role === 'driver') {
            $orders = Order::findByDriver();
        } else {
            $orders = Order::findAll();
        }

        Response::json($orders);
    }

    #[OA\Get(
        path: '/api/orders/{id}',
        operationId: 'getOrder',
        summary: 'Get a single order',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order data', content: new OA\JsonContent(ref: '#/components/schemas/Order')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Not your order'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(array $request): void
    {
        $user  = AuthMiddleware::handle($request, ['customer', 'admin', 'driver']);
        $order = Order::findById((int)$request['params']['id']);

        if (!$order) {
            Response::error('Order not found', 404);
        }
        if ($user->role === 'customer' && (int)$order['customer_id'] !== (int)$user->sub) {
            Response::error('Forbidden', 403);
        }

        Response::json($order);
    }

    #[OA\Post(
        path: '/api/orders',
        operationId: 'createOrder',
        summary: 'Place a new order (customer only)',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['restaurant_id', 'items'],
                properties: [
                    new OA\Property(property: 'restaurant_id', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'menu_item_id', type: 'integer', example: 3),
                                new OA\Property(property: 'quantity',     type: 'integer', example: 2),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order placed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Customer role required'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(array $request): void
    {
        $user = AuthMiddleware::handle($request, ['customer']);
        $body = $request['body'];

        if (empty($body['restaurant_id']) || empty($body['items']) || !is_array($body['items'])) {
            Response::error('Fields restaurant_id and items[] are required', 422);
        }

        try {
            $id = Order::create($body, (int)$user->sub);
            Response::json(Order::findById($id), 201, 'Created');
        } catch (\Throwable $e) {
            Response::error('Order could not be placed: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Put(
        path: '/api/orders/{id}/status',
        operationId: 'updateOrderStatus',
        summary: 'Update order status (admin or driver)',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending','preparing','out_for_delivery','delivered','cancelled'], example: 'preparing'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status updated'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Admin or driver role required'),
            new OA\Response(response: 404, description: 'Order not found'),
            new OA\Response(response: 422, description: 'Invalid status value'),
        ]
    )]
    public function updateStatus(array $request): void
    {
        AuthMiddleware::handle($request, ['admin', 'driver']);
        $body  = $request['body'];
        $valid = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

        if (empty($body['status']) || !in_array($body['status'], $valid, true)) {
            Response::error('Invalid status value', 422);
        }

        $updated = Order::updateStatus((int)$request['params']['id'], $body['status']);
        if (!$updated) {
            Response::error('Order not found', 404);
        }

        Response::json(Order::findById((int)$request['params']['id']));
    }

    #[OA\Delete(
        path: '/api/orders/{id}',
        operationId: 'cancelOrder',
        summary: 'Cancel a pending order (customer, own order)',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Order cancelled'),
            new OA\Response(response: 400, description: 'Cannot cancel — not found, not yours, or not pending'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Customer role required'),
        ]
    )]
    public function destroy(array $request): void
    {
        $user      = AuthMiddleware::handle($request, ['customer']);
        $cancelled = Order::cancel((int)$request['params']['id'], (int)$user->sub);

        if (!$cancelled) {
            Response::error(
                'Cannot cancel — order not found, not yours, or not in pending status',
                400
            );
        }
        Response::noContent();
    }
}
