<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Router;
use App\Database;
use App\Services\DeviceDetector;
use App\Services\IpLookup;
use App\Controllers\AuthController;
use App\Controllers\RestaurantController;
use App\Controllers\MenuController;
use App\Controllers\OrderController;
use App\Controllers\UserController;
use App\Controllers\UploadController;
use OpenApi\Generator;

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Content-Security-Policy: default-src \'none\'');
header_remove('X-Powered-By');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Access logging ────────────────────────────────────────────────────────────
try {
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $device = DeviceDetector::detect();
    $geo    = IpLookup::lookup($ip);

    Database::getInstance()->prepare(
        'INSERT INTO access_logs
         (user_id, ip_address, device_type, browser, os, ip_country, ip_city, ip_isp)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        null,
        $ip,
        $device['device_type'],
        $device['browser'],
        $device['os'],
        $geo['country'],
        $geo['city'],
        $geo['isp'],
    ]);
} catch (\Throwable) {
    // Never fail a request because of logging
}

// ── Strip base path (handles /Szalmaprojekt prefix in XAMPP) ─────────────────
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$uri      = $_SERVER['REQUEST_URI'];
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
$uri = strtok($uri ?: '/', '?');

// ── Routes ────────────────────────────────────────────────────────────────────
$router     = new Router();
$auth       = new AuthController();
$restaurant = new RestaurantController();
$menu       = new MenuController();
$order      = new OrderController();
$user       = new UserController();

// Auth
$router->add('POST', '/api/auth/register',       [$auth, 'register']);
$router->add('POST', '/api/auth/login',           [$auth, 'login']);
$router->add('GET',  '/api/auth/verify-email',    [$auth, 'verifyEmail']);
$router->add('POST', '/api/auth/forgot-password', [$auth, 'forgotPassword']);
$router->add('POST', '/api/auth/reset-password',  [$auth, 'resetPassword']);

// Restaurants
$router->add('GET',    '/api/restaurants',      [$restaurant, 'index']);
$router->add('GET',    '/api/restaurants/{id}', [$restaurant, 'show']);
$router->add('POST',   '/api/restaurants',      [$restaurant, 'store']);
$router->add('PUT',    '/api/restaurants/{id}', [$restaurant, 'update']);
$router->add('DELETE', '/api/restaurants/{id}', [$restaurant, 'destroy']);

// Menu items
$router->add('GET',    '/api/restaurants/{id}/menu',       [$menu, 'index']);
$router->add('POST',   '/api/restaurants/{id}/menu',       [$menu, 'store']);
$router->add('PUT',    '/api/restaurants/{id}/menu/{mid}', [$menu, 'update']);
$router->add('DELETE', '/api/restaurants/{id}/menu/{mid}', [$menu, 'destroy']);

// Orders
$router->add('GET',    '/api/orders',             [$order, 'index']);
$router->add('GET',    '/api/orders/{id}',        [$order, 'show']);
$router->add('POST',   '/api/orders',             [$order, 'store']);
$router->add('PUT',    '/api/orders/{id}/status', [$order, 'updateStatus']);
$router->add('DELETE', '/api/orders/{id}',        [$order, 'destroy']);

// Users
$router->add('GET',    '/api/users',      [$user, 'index']);
$router->add('GET',    '/api/users/{id}', [$user, 'show']);
$router->add('PUT',    '/api/users/{id}', [$user, 'update']);
$router->add('DELETE', '/api/users/{id}', [$user, 'destroy']);

// Upload
$upload = new UploadController();
$router->add('POST', '/api/upload', [$upload, 'upload']);

// Swagger
$router->add('GET', '/api/swagger.json', function (array $request) {
    $openapi = Generator::scan([__DIR__ . '/src']);
    header('Content-Type: application/json');
    echo $openapi->toJson();
    exit;
});

// ── Dispatch ──────────────────────────────────────────────────────────────────
$router->dispatch($_SERVER['REQUEST_METHOD'], $uri);
