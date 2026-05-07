<?php
namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Szalma Food Delivery API',
    description: 'REST API for a food delivery platform. Protected endpoints require a Bearer JWT token obtained from POST /api/auth/login.'
)]
#[OA\Server(url: 'http://localhost/Szalmaprojekt', description: 'Local XAMPP')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id',         type: 'integer', example: 1),
        new OA\Property(property: 'name',        type: 'string',  example: 'John Doe'),
        new OA\Property(property: 'email',       type: 'string',  format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'role',        type: 'string',  enum: ['customer', 'admin', 'driver'], example: 'customer'),
        new OA\Property(property: 'created_at',  type: 'string',  format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Restaurant',
    properties: [
        new OA\Property(property: 'id',         type: 'integer', example: 1),
        new OA\Property(property: 'name',        type: 'string',  example: 'Pizza Palace'),
        new OA\Property(property: 'address',     type: 'string',  example: '123 Main St'),
        new OA\Property(property: 'phone',       type: 'string',  example: '+36 1 234 5678'),
        new OA\Property(property: 'admin_id',    type: 'integer', example: 2),
        new OA\Property(property: 'created_at',  type: 'string',  format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'MenuItem',
    properties: [
        new OA\Property(property: 'id',            type: 'integer', example: 1),
        new OA\Property(property: 'restaurant_id', type: 'integer', example: 1),
        new OA\Property(property: 'name',          type: 'string',  example: 'Margherita Pizza'),
        new OA\Property(property: 'description',   type: 'string',  example: 'Classic tomato and mozzarella'),
        new OA\Property(property: 'price',         type: 'number',  format: 'float', example: 12.50),
        new OA\Property(property: 'available',     type: 'boolean', example: true),
    ]
)]
#[OA\Schema(
    schema: 'Order',
    properties: [
        new OA\Property(property: 'id',            type: 'integer', example: 1),
        new OA\Property(property: 'customer_id',   type: 'integer', example: 3),
        new OA\Property(property: 'restaurant_id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_id',     type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'status',        type: 'string',  enum: ['pending','preparing','out_for_delivery','delivered','cancelled'], example: 'pending'),
        new OA\Property(property: 'total_price',   type: 'number',  format: 'float', example: 25.00),
        new OA\Property(property: 'created_at',    type: 'string',  format: 'date-time'),
        new OA\Property(property: 'updated_at',    type: 'string',  format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Error',
    properties: [
        new OA\Property(property: 'status',  type: 'integer', example: 400),
        new OA\Property(property: 'message', type: 'string',  example: 'Error description'),
        new OA\Property(property: 'data',    nullable: true,  example: null),
    ]
)]
class OpenApi {}
