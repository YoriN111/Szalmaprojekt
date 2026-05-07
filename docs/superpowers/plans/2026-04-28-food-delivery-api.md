# Food Delivery API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a multi-restaurant food delivery REST API with JWT auth and three user roles (customer, admin, driver) on PHP vanilla + MySQL + Composer.

**Architecture:** Front controller pattern — all requests routed through `index.php` → `Router` → Controller → Model. AuthMiddleware called inside each controller action that requires auth. Access logging on every request.

**Tech Stack:** PHP 8.1+, MySQL, Composer, firebase/php-jwt ^6, mobiledetect/mobiledetectlib ^3, fakerphp/faker ^1, vlucas/phpdotenv ^5, phpunit/phpunit ^11 (dev)

---

## File Map

| File | Responsibility |
|------|---------------|
| `composer.json` | Dependencies + PSR-4 autoload (`App\` → `src/`) |
| `.env` | DB creds, JWT secret (not committed) |
| `.env.example` | Template for .env |
| `.htaccess` | Rewrite all requests to index.php |
| `phpunit.xml` | PHPUnit config |
| `index.php` | Bootstrap dotenv, register routes, dispatch, log access |
| `src/Database.php` | PDO singleton |
| `src/Response.php` | Static JSON output helpers |
| `src/Router.php` | URI + method matcher with `{param}` support |
| `src/Auth.php` | JWT encode/decode via firebase/php-jwt |
| `src/Middleware/AuthMiddleware.php` | Extract Bearer token, verify, return payload or 401/403 |
| `src/Models/User.php` | CRUD on `users` table |
| `src/Models/Restaurant.php` | CRUD on `restaurants` table |
| `src/Models/MenuItem.php` | CRUD on `menu_items` table |
| `src/Models/Order.php` | CRUD on `orders` + `order_items` tables |
| `src/Controllers/AuthController.php` | register, login |
| `src/Controllers/RestaurantController.php` | CRUD restaurants |
| `src/Controllers/MenuController.php` | CRUD menu items |
| `src/Controllers/OrderController.php` | Place, list, update status, cancel orders |
| `src/Controllers/UserController.php` | List, show, update, delete users |
| `src/Services/DeviceDetector.php` | Detect device/browser/OS via MobileDetect |
| `src/Services/IpLookup.php` | Geo + ISP lookup via ip-api.com |
| `database/migrations.sql` | All CREATE TABLE statements |
| `database/seeder.php` | Faker-based seed data |
| `docs/postman_collection.json` | All endpoints with example bodies |
| `tests/Unit/ResponseTest.php` | Unit tests for Response |
| `tests/Unit/RouterTest.php` | Unit tests for Router |
| `tests/Unit/AuthTest.php` | Unit tests for Auth |

---

## Task 1: Project Scaffold

**Files:**
- Create: `composer.json`
- Create: `.env`
- Create: `.env.example`
- Create: `.htaccess`
- Create: `phpunit.xml`

- [ ] **Step 1: Create `composer.json`**

```json
{
    "require": {
        "firebase/php-jwt": "^6.0",
        "mobiledetect/mobiledetectlib": "^3.0",
        "fakerphp/faker": "^1.0",
        "vlucas/phpdotenv": "^5.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 2: Create `.env.example`**

```
DB_HOST=localhost
DB_NAME=szalmaprojekt
DB_USER=root
DB_PASS=
JWT_SECRET=change-me-to-a-long-random-string
JWT_EXPIRY=3600
```

- [ ] **Step 3: Create `.env`** (copy from .env.example, set your XAMPP values)

```
DB_HOST=localhost
DB_NAME=szalmaprojekt
DB_USER=root
DB_PASS=
JWT_SECRET=super-secret-key-dev-only
JWT_EXPIRY=3600
```

- [ ] **Step 4: Create `.htaccess`**

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

- [ ] **Step 5: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 6: Install dependencies**

```bash
composer install
```

Expected: `vendor/` directory created, autoload generated.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock .env.example .htaccess phpunit.xml
git commit -m "chore: project scaffold with composer and phpunit"
```

---

## Task 2: Database Migrations

**Files:**
- Create: `database/migrations.sql`

- [ ] **Step 1: Create `database/migrations.sql`**

```sql
CREATE DATABASE IF NOT EXISTS szalmaprojekt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE szalmaprojekt;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer','admin','driver') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE restaurants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    admin_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(8,2) NOT NULL,
    available TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    driver_id INT NULL,
    status ENUM('pending','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

CREATE TABLE access_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    ip_address VARCHAR(45),
    device_type VARCHAR(20),
    browser VARCHAR(100),
    os VARCHAR(100),
    ip_country VARCHAR(100),
    ip_city VARCHAR(100),
    ip_isp VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

- [ ] **Step 2: Run migrations in phpMyAdmin or MySQL CLI**

```bash
mysql -u root -p < database/migrations.sql
```

Expected: `szalmaprojekt` database with 6 tables.

- [ ] **Step 3: Verify tables exist**

```bash
mysql -u root -p szalmaprojekt -e "SHOW TABLES;"
```

Expected output:
```
access_logs
menu_items
order_items
orders
restaurants
users
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations.sql
git commit -m "feat: add database migrations"
```

---

## Task 3: Response Helper

**Files:**
- Create: `src/Response.php`
- Create: `tests/Unit/ResponseTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ResponseTest.php`:

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Response;

class ResponseTest extends TestCase
{
    public function test_json_outputs_correct_structure(): void
    {
        ob_start();
        try {
            Response::json(['id' => 1], 200, 'OK');
        } catch (\Exception $e) {
            // Response calls exit — catch via output buffer
        }
        $output = ob_get_clean();
        $decoded = json_decode($output, true);

        $this->assertSame(200, $decoded['status']);
        $this->assertSame('OK', $decoded['message']);
        $this->assertSame(['id' => 1], $decoded['data']);
    }

    public function test_error_outputs_null_data(): void
    {
        ob_start();
        try {
            Response::error('Not found', 404);
        } catch (\Exception $e) {}
        $output = ob_get_clean();
        $decoded = json_decode($output, true);

        $this->assertSame(404, $decoded['status']);
        $this->assertSame('Not found', $decoded['message']);
        $this->assertNull($decoded['data']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/ResponseTest.php --verbose
```

Expected: `FAIL` — class `App\Response` not found.

- [ ] **Step 3: Create `src/Response.php`**

```php
<?php
namespace App;

class Response
{
    public static function json(mixed $data = null, int $status = 200, string $message = 'OK'): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(null, $status, $message);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Unit/ResponseTest.php --verbose
```

Expected: `OK (2 tests, 2 assertions)`

- [ ] **Step 5: Commit**

```bash
git add src/Response.php tests/Unit/ResponseTest.php
git commit -m "feat: add Response helper with unit tests"
```

---

## Task 4: Router

**Files:**
- Create: `src/Router.php`
- Create: `tests/Unit/RouterTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/RouterTest.php`:

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Router;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function test_matches_static_route(): void
    {
        $called = false;
        $this->router->add('GET', '/api/restaurants', function (array $req) use (&$called) {
            $called = true;
        });

        ob_start();
        try {
            $this->router->dispatch('GET', '/api/restaurants');
        } catch (\Exception $e) {}
        ob_end_clean();

        $this->assertTrue($called);
    }

    public function test_matches_route_with_param(): void
    {
        $capturedId = null;
        $this->router->add('GET', '/api/restaurants/{id}', function (array $req) use (&$capturedId) {
            $capturedId = $req['params']['id'];
        });

        $this->router->dispatch('GET', '/api/restaurants/42');

        $this->assertSame('42', $capturedId);
    }

    public function test_does_not_match_wrong_method(): void
    {
        $called = false;
        $this->router->add('POST', '/api/restaurants', function (array $req) use (&$called) {
            $called = true;
        });

        ob_start();
        try {
            $this->router->dispatch('GET', '/api/restaurants');
        } catch (\Exception $e) {}
        ob_end_clean();

        $this->assertFalse($called);
    }

    public function test_returns_404_for_unknown_route(): void
    {
        ob_start();
        try {
            $this->router->dispatch('GET', '/api/unknown');
        } catch (\Exception $e) {}
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(404, $decoded['status']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit tests/Unit/RouterTest.php --verbose
```

Expected: `FAIL` — class `App\Router` not found.

- [ ] **Step 3: Create `src/Router.php`**

```php
<?php
namespace App;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'handler' => $handler,
            'pattern' => $this->pathToPattern($path),
        ];
    }

    private function pathToPattern(string $path): string
    {
        $escaped = preg_quote($path, '#');
        $pattern = preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>[^/]+)', $escaped);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = strtok(parse_url($uri, PHP_URL_PATH), '?');
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request = [
                'params' => $params,
                'body'   => json_decode(file_get_contents('php://input') ?: '{}', true) ?? [],
                'query'  => $_GET,
            ];

            ($route['handler'])($request);
            return;
        }

        Response::error('Not Found', 404);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
vendor/bin/phpunit tests/Unit/RouterTest.php --verbose
```

Expected: `OK (4 tests, 4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add src/Router.php tests/Unit/RouterTest.php
git commit -m "feat: add Router with param support and unit tests"
```

---

## Task 5: Auth (JWT)

**Files:**
- Create: `src/Auth.php`
- Create: `tests/Unit/AuthTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/AuthTest.php`:

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Auth;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'test-secret-key';
        $_ENV['JWT_EXPIRY'] = '3600';
    }

    public function test_encode_returns_string(): void
    {
        $token = Auth::encode(['sub' => 1, 'role' => 'customer']);
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
    }

    public function test_decode_returns_original_payload(): void
    {
        $token = Auth::encode(['sub' => 5, 'role' => 'admin']);
        $payload = Auth::decode($token);

        $this->assertSame(5, $payload->sub);
        $this->assertSame('admin', $payload->role);
    }

    public function test_decode_throws_on_invalid_token(): void
    {
        $this->expectException(\Exception::class);
        Auth::decode('not.a.valid.token');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
vendor/bin/phpunit tests/Unit/AuthTest.php --verbose
```

Expected: `FAIL` — class `App\Auth` not found.

- [ ] **Step 3: Create `src/Auth.php`**

```php
<?php
namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private static function secret(): string
    {
        return $_ENV['JWT_SECRET'];
    }

    public static function encode(array $payload): string
    {
        $payload['exp'] = time() + (int)($_ENV['JWT_EXPIRY'] ?? 3600);
        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function decode(string $token): object
    {
        return JWT::decode($token, new Key(self::secret(), 'HS256'));
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
vendor/bin/phpunit tests/Unit/AuthTest.php --verbose
```

Expected: `OK (3 tests, 4 assertions)`

- [ ] **Step 5: Run full test suite**

```bash
vendor/bin/phpunit --verbose
```

Expected: `OK (9 tests, 10 assertions)`

- [ ] **Step 6: Commit**

```bash
git add src/Auth.php tests/Unit/AuthTest.php
git commit -m "feat: add JWT Auth helper with unit tests"
```

---

## Task 6: Database Helper

**Files:**
- Create: `src/Database.php`

- [ ] **Step 1: Create `src/Database.php`**

```php
<?php
namespace App;

use PDO;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'];
            $name = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];

            self::$instance = new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        return self::$instance;
    }

    public static function setInstance(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Database.php
git commit -m "feat: add PDO Database singleton"
```

---

## Task 7: AuthMiddleware

**Files:**
- Create: `src/Middleware/AuthMiddleware.php`

- [ ] **Step 1: Create `src/Middleware/AuthMiddleware.php`**

```php
<?php
namespace App\Middleware;

use App\Auth;
use App\Response;

class AuthMiddleware
{
    /**
     * Validates Bearer token and role. Returns JWT payload on success.
     * Calls Response::error() (which exits) on failure.
     */
    public static function handle(array $request, array $allowedRoles = []): object
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

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
```

- [ ] **Step 2: Commit**

```bash
git add src/Middleware/AuthMiddleware.php
git commit -m "feat: add AuthMiddleware for JWT role verification"
```

---

## Task 8: Models

**Files:**
- Create: `src/Models/User.php`
- Create: `src/Models/Restaurant.php`
- Create: `src/Models/MenuItem.php`
- Create: `src/Models/Order.php`

- [ ] **Step 1: Create `src/Models/User.php`**

```php
<?php
namespace App\Models;

use App\Database;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT id, name, email, role, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findAll(): array
    {
        return Database::getInstance()
            ->query('SELECT id, name, email, role, created_at FROM users ORDER BY id')
            ->fetchAll();
    }

    public static function create(array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8'),
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['role'] ?? 'customer',
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $values[] = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $values[] = htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['password'])) {
            $fields[] = 'password_hash = ?';
            $values[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if (!$fields) {
            return;
        }

        $values[] = $id;
        Database::getInstance()
            ->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')
            ->execute($values);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()
            ->prepare('DELETE FROM users WHERE id = ?')
            ->execute([$id]);
    }
}
```

- [ ] **Step 2: Create `src/Models/Restaurant.php`**

```php
<?php
namespace App\Models;

use App\Database;

class Restaurant
{
    public static function findAll(): array
    {
        return Database::getInstance()
            ->query('SELECT * FROM restaurants ORDER BY id')
            ->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM restaurants WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $adminId): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO restaurants (name, address, phone, admin_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['address'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['phone'], ENT_QUOTES, 'UTF-8'),
            $adminId,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data, int $adminId): bool
    {
        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $values[] = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['address'])) {
            $fields[] = 'address = ?';
            $values[] = htmlspecialchars($data['address'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['phone'])) {
            $fields[] = 'phone = ?';
            $values[] = htmlspecialchars($data['phone'], ENT_QUOTES, 'UTF-8');
        }
        if (!$fields) {
            return false;
        }

        $values[] = $id;
        $values[] = $adminId;
        $stmt = Database::getInstance()->prepare(
            'UPDATE restaurants SET ' . implode(', ', $fields) . ' WHERE id = ? AND admin_id = ?'
        );
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id, int $adminId): bool
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM restaurants WHERE id = ? AND admin_id = ?'
        );
        $stmt->execute([$id, $adminId]);
        return $stmt->rowCount() > 0;
    }
}
```

- [ ] **Step 3: Create `src/Models/MenuItem.php`**

```php
<?php
namespace App\Models;

use App\Database;

class MenuItem
{
    public static function findByRestaurant(int $restaurantId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM menu_items WHERE restaurant_id = ? ORDER BY id'
        );
        $stmt->execute([$restaurantId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM menu_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $restaurantId): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO menu_items (restaurant_id, name, description, price, available) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $restaurantId,
            htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            (float)$data['price'],
            isset($data['available']) ? (int)$data['available'] : 1,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $restaurantId, array $data): bool
    {
        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $values[] = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $values[] = htmlspecialchars($data['description'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['price'])) {
            $fields[] = 'price = ?';
            $values[] = (float)$data['price'];
        }
        if (isset($data['available'])) {
            $fields[] = 'available = ?';
            $values[] = (int)$data['available'];
        }
        if (!$fields) {
            return false;
        }

        $values[] = $id;
        $values[] = $restaurantId;
        $stmt = Database::getInstance()->prepare(
            'UPDATE menu_items SET ' . implode(', ', $fields) . ' WHERE id = ? AND restaurant_id = ?'
        );
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id, int $restaurantId): bool
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM menu_items WHERE id = ? AND restaurant_id = ?'
        );
        $stmt->execute([$id, $restaurantId]);
        return $stmt->rowCount() > 0;
    }
}
```

- [ ] **Step 4: Create `src/Models/Order.php`**

```php
<?php
namespace App\Models;

use App\Database;
use App\Response;

class Order
{
    public static function findAll(): array
    {
        return Database::getInstance()
            ->query('SELECT * FROM orders ORDER BY created_at DESC')
            ->fetchAll();
    }

    public static function findByCustomer(int $customerId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $items = $db->prepare(
            'SELECT oi.*, mi.name AS item_name
             FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             WHERE oi.order_id = ?'
        );
        $items->execute([$id]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    public static function create(array $data, int $customerId): int
    {
        $db = Database::getInstance();

        $total = 0.0;
        $lineItems = [];

        foreach ($data['items'] as $item) {
            $menuItem = MenuItem::findById((int)$item['menu_item_id']);
            if (!$menuItem || !$menuItem['available']) {
                Response::error('Menu item ' . $item['menu_item_id'] . ' unavailable', 400);
            }
            $qty          = (int)$item['quantity'];
            $total       += $menuItem['price'] * $qty;
            $lineItems[]  = ['menu_item' => $menuItem, 'quantity' => $qty];
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO orders (customer_id, restaurant_id, status, total_price) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$customerId, (int)$data['restaurant_id'], 'pending', $total]);
            $orderId = (int)$db->lastInsertId();

            foreach ($lineItems as $line) {
                $db->prepare(
                    'INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
                )->execute([
                    $orderId,
                    $line['menu_item']['id'],
                    $line['quantity'],
                    $line['menu_item']['price'],
                ]);
            }

            $db->commit();
            return $orderId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE orders SET status = ? WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function cancel(int $id, int $customerId): bool
    {
        $stmt = Database::getInstance()->prepare(
            "UPDATE orders SET status = 'cancelled' WHERE id = ? AND customer_id = ? AND status = 'pending'"
        );
        $stmt->execute([$id, $customerId]);
        return $stmt->rowCount() > 0;
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Models/
git commit -m "feat: add User, Restaurant, MenuItem, Order models"
```

---

## Task 9: Services (DeviceDetector + IpLookup)

**Files:**
- Create: `src/Services/DeviceDetector.php`
- Create: `src/Services/IpLookup.php`

- [ ] **Step 1: Create `src/Services/DeviceDetector.php`**

```php
<?php
namespace App\Services;

use Detection\MobileDetect;

class DeviceDetector
{
    public static function detect(): array
    {
        $ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $detect  = new MobileDetect();

        if ($detect->isMobile()) {
            $type = 'mobile';
        } elseif ($detect->isTablet()) {
            $type = 'tablet';
        } else {
            $type = 'desktop';
        }

        $browser = 'Unknown';
        if (str_contains($ua, 'Edg'))     { $browser = 'Edge'; }
        elseif (str_contains($ua, 'OPR')) { $browser = 'Opera'; }
        elseif (str_contains($ua, 'Firefox')) { $browser = 'Firefox'; }
        elseif (str_contains($ua, 'Chrome'))  { $browser = 'Chrome'; }
        elseif (str_contains($ua, 'Safari'))  { $browser = 'Safari'; }

        $os = 'Unknown';
        if (str_contains($ua, 'Windows'))     { $os = 'Windows'; }
        elseif (str_contains($ua, 'Android')) { $os = 'Android'; }
        elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) { $os = 'iOS'; }
        elseif (str_contains($ua, 'Mac'))     { $os = 'macOS'; }
        elseif (str_contains($ua, 'Linux'))   { $os = 'Linux'; }

        return [
            'device_type' => $type,
            'browser'     => $browser,
            'os'          => $os,
        ];
    }
}
```

- [ ] **Step 2: Create `src/Services/IpLookup.php`**

```php
<?php
namespace App\Services;

class IpLookup
{
    private static array $privateRanges = ['127.', '10.', '192.168.', '172.16.', '::1'];

    public static function lookup(string $ip): array
    {
        foreach (self::$privateRanges as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return ['country' => 'Local', 'city' => 'Local', 'isp' => 'Local'];
            }
        }

        $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
        $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city,isp,status", false, $ctx);

        if (!$json) {
            return ['country' => '', 'city' => '', 'isp' => ''];
        }

        $data = json_decode($json, true);
        if (($data['status'] ?? '') !== 'success') {
            return ['country' => '', 'city' => '', 'isp' => ''];
        }

        return [
            'country' => $data['country'] ?? '',
            'city'    => $data['city']    ?? '',
            'isp'     => $data['isp']     ?? '',
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Services/
git commit -m "feat: add DeviceDetector and IpLookup services"
```

---

## Task 10: AuthController

**Files:**
- Create: `src/Controllers/AuthController.php`

- [ ] **Step 1: Create `src/Controllers/AuthController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Response;
use App\Models\User;

class AuthController
{
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
        $user = User::findById($id);
        Response::json($user, 201, 'Created');
    }

    public function login(array $request): void
    {
        $body = $request['body'];

        if (empty($body['email']) || empty($body['password'])) {
            Response::error('Email and password are required', 422);
        }

        $user = User::findByEmail($body['email']);
        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }

        $token = Auth::encode(['sub' => $user['id'], 'role' => $user['role']]);

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
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Controllers/AuthController.php
git commit -m "feat: add AuthController (register + login)"
```

---

## Task 11: RestaurantController + MenuController

**Files:**
- Create: `src/Controllers/RestaurantController.php`
- Create: `src/Controllers/MenuController.php`

- [ ] **Step 1: Create `src/Controllers/RestaurantController.php`**

```php
<?php
namespace App\Controllers;

use App\Response;
use App\Models\Restaurant;
use App\Middleware\AuthMiddleware;

class RestaurantController
{
    public function index(array $request): void
    {
        Response::json(Restaurant::findAll());
    }

    public function show(array $request): void
    {
        $r = Restaurant::findById((int)$request['params']['id']);
        if (!$r) {
            Response::error('Restaurant not found', 404);
        }
        Response::json($r);
    }

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

    public function destroy(array $request): void
    {
        $user    = AuthMiddleware::handle($request, ['admin']);
        $deleted = Restaurant::delete((int)$request['params']['id'], (int)$user->sub);

        if (!$deleted) {
            Response::error('Restaurant not found or you do not own it', 404);
        }
        Response::json(null, 200, 'Deleted');
    }
}
```

- [ ] **Step 2: Create `src/Controllers/MenuController.php`**

```php
<?php
namespace App\Controllers;

use App\Response;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Middleware\AuthMiddleware;

class MenuController
{
    public function index(array $request): void
    {
        $items = MenuItem::findByRestaurant((int)$request['params']['id']);
        Response::json($items);
    }

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
        Response::json(null, 200, 'Deleted');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Controllers/RestaurantController.php src/Controllers/MenuController.php
git commit -m "feat: add RestaurantController and MenuController"
```

---

## Task 12: OrderController

**Files:**
- Create: `src/Controllers/OrderController.php`

- [ ] **Step 1: Create `src/Controllers/OrderController.php`**

```php
<?php
namespace App\Controllers;

use App\Response;
use App\Models\Order;
use App\Middleware\AuthMiddleware;

class OrderController
{
    public function index(array $request): void
    {
        $user = AuthMiddleware::handle($request, ['customer', 'admin']);

        $orders = $user->role === 'customer'
            ? Order::findByCustomer((int)$user->sub)
            : Order::findAll();

        Response::json($orders);
    }

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

    public function updateStatus(array $request): void
    {
        $user   = AuthMiddleware::handle($request, ['admin', 'driver']);
        $body   = $request['body'];
        $valid  = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

        if (empty($body['status']) || !in_array($body['status'], $valid, true)) {
            Response::error('Invalid status value', 422);
        }

        $updated = Order::updateStatus((int)$request['params']['id'], $body['status']);
        if (!$updated) {
            Response::error('Order not found', 404);
        }

        Response::json(Order::findById((int)$request['params']['id']));
    }

    public function destroy(array $request): void
    {
        $user      = AuthMiddleware::handle($request, ['customer']);
        $cancelled = Order::cancel((int)$request['params']['id'], (int)$user->sub);

        if (!$cancelled) {
            Response::error('Cannot cancel — order not found, not yours, or not in pending status', 400);
        }
        Response::json(null, 200, 'Cancelled');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Controllers/OrderController.php
git commit -m "feat: add OrderController"
```

---

## Task 13: UserController

**Files:**
- Create: `src/Controllers/UserController.php`

- [ ] **Step 1: Create `src/Controllers/UserController.php`**

```php
<?php
namespace App\Controllers;

use App\Response;
use App\Models\User;
use App\Middleware\AuthMiddleware;

class UserController
{
    public function index(array $request): void
    {
        AuthMiddleware::handle($request, ['admin']);
        Response::json(User::findAll());
    }

    public function show(array $request): void
    {
        AuthMiddleware::handle($request);
        $user = User::findById((int)$request['params']['id']);
        if (!$user) {
            Response::error('User not found', 404);
        }
        Response::json($user);
    }

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

    public function destroy(array $request): void
    {
        AuthMiddleware::handle($request, ['admin']);
        $targetId = (int)$request['params']['id'];
        $user     = User::findById($targetId);

        if (!$user) {
            Response::error('User not found', 404);
        }
        User::delete($targetId);
        Response::json(null, 200, 'Deleted');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Controllers/UserController.php
git commit -m "feat: add UserController"
```

---

## Task 14: Front Controller (index.php)

**Files:**
- Create: `index.php`

- [ ] **Step 1: Create `index.php`**

```php
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

// ── Bootstrap ────────────────────────────────────────────────────────────────
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

// ── Strip base path (e.g. /Szalmaprojekt) ────────────────────────────────────
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
$router->add('POST', '/api/auth/register', [$auth, 'register']);
$router->add('POST', '/api/auth/login',    [$auth, 'login']);

// Restaurants
$router->add('GET',    '/api/restaurants',     [$restaurant, 'index']);
$router->add('GET',    '/api/restaurants/{id}', [$restaurant, 'show']);
$router->add('POST',   '/api/restaurants',     [$restaurant, 'store']);
$router->add('PUT',    '/api/restaurants/{id}', [$restaurant, 'update']);
$router->add('DELETE', '/api/restaurants/{id}', [$restaurant, 'destroy']);

// Menu items
$router->add('GET',    '/api/restaurants/{id}/menu',          [$menu, 'index']);
$router->add('POST',   '/api/restaurants/{id}/menu',          [$menu, 'store']);
$router->add('PUT',    '/api/restaurants/{id}/menu/{mid}',    [$menu, 'update']);
$router->add('DELETE', '/api/restaurants/{id}/menu/{mid}',    [$menu, 'destroy']);

// Orders
$router->add('GET',    '/api/orders',              [$order, 'index']);
$router->add('GET',    '/api/orders/{id}',         [$order, 'show']);
$router->add('POST',   '/api/orders',              [$order, 'store']);
$router->add('PUT',    '/api/orders/{id}/status',  [$order, 'updateStatus']);
$router->add('DELETE', '/api/orders/{id}',         [$order, 'destroy']);

// Users
$router->add('GET',    '/api/users',     [$user, 'index']);
$router->add('GET',    '/api/users/{id}', [$user, 'show']);
$router->add('PUT',    '/api/users/{id}', [$user, 'update']);
$router->add('DELETE', '/api/users/{id}', [$user, 'destroy']);

// ── Dispatch ──────────────────────────────────────────────────────────────────
$router->dispatch($_SERVER['REQUEST_METHOD'], $uri);
```

- [ ] **Step 2: Smoke-test the API is alive**

Visit in browser or curl:
```bash
curl http://localhost/Szalmaprojekt/api/restaurants
```

Expected:
```json
{"status":200,"message":"OK","data":[]}
```

- [ ] **Step 3: Commit**

```bash
git add index.php
git commit -m "feat: wire all routes in front controller"
```

---

## Task 15: Database Seeder

**Files:**
- Create: `database/seeder.php`

- [ ] **Step 1: Create `database/seeder.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Faker\Factory;
use App\Database;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db    = Database::getInstance();
$faker = Factory::create();

echo "Seeding...\n";

// ── Clear existing data (respect FK order) ────────────────────────────────────
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['access_logs', 'order_items', 'orders', 'menu_items', 'restaurants', 'users'] as $t) {
    $db->exec("TRUNCATE TABLE {$t}");
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');

// ── Users ─────────────────────────────────────────────────────────────────────
$adminIds    = [];
$customerIds = [];
$driverIds   = [];

// 3 admins
for ($i = 0; $i < 3; $i++) {
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $faker->name(),
        'admin' . ($i + 1) . '@example.com',
        password_hash('password', PASSWORD_BCRYPT),
        'admin',
    ]);
    $adminIds[] = (int)$db->lastInsertId();
}

// 10 customers
for ($i = 0; $i < 10; $i++) {
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $faker->name(),
        'customer' . ($i + 1) . '@example.com',
        password_hash('password', PASSWORD_BCRYPT),
        'customer',
    ]);
    $customerIds[] = (int)$db->lastInsertId();
}

// 2 drivers
for ($i = 0; $i < 2; $i++) {
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $faker->name(),
        'driver' . ($i + 1) . '@example.com',
        password_hash('password', PASSWORD_BCRYPT),
        'driver',
    ]);
    $driverIds[] = (int)$db->lastInsertId();
}

echo "Users: " . (count($adminIds) + count($customerIds) + count($driverIds)) . "\n";

// ── Restaurants (5 total, admins 1+2 own 2 each, admin 3 owns 1) ──────────────
$restaurantIds = [];
$assignments   = [
    $adminIds[0], $adminIds[0],
    $adminIds[1], $adminIds[1],
    $adminIds[2],
];
foreach ($assignments as $adminId) {
    $stmt = $db->prepare(
        'INSERT INTO restaurants (name, address, phone, admin_id) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $faker->company() . ' Restaurant',
        $faker->streetAddress(),
        $faker->phoneNumber(),
        $adminId,
    ]);
    $restaurantIds[] = (int)$db->lastInsertId();
}

echo "Restaurants: " . count($restaurantIds) . "\n";

// ── Menu items (4 per restaurant) ─────────────────────────────────────────────
$menuItemIds = [];
foreach ($restaurantIds as $rid) {
    for ($i = 0; $i < 4; $i++) {
        $stmt = $db->prepare(
            'INSERT INTO menu_items (restaurant_id, name, description, price, available) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $rid,
            $faker->words(3, true),
            $faker->sentence(),
            $faker->randomFloat(2, 3, 25),
            1,
        ]);
        $menuItemIds[$rid][] = (int)$db->lastInsertId();
    }
}

echo "Menu items: " . (count($restaurantIds) * 4) . "\n";

// ── Orders (15 orders with items) ─────────────────────────────────────────────
$statuses = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

for ($i = 0; $i < 15; $i++) {
    $rid        = $restaurantIds[array_rand($restaurantIds)];
    $customerId = $customerIds[array_rand($customerIds)];
    $status     = $statuses[array_rand($statuses)];

    // Pick 1–3 items from this restaurant's menu
    $available  = $menuItemIds[$rid];
    $count      = min(rand(1, 3), count($available));
    $chosen     = array_rand(array_flip($available), $count);
    if (!is_array($chosen)) {
        $chosen = [$chosen];
    }

    $total = 0.0;
    $lines = [];
    foreach ($chosen as $mid) {
        $row   = $db->prepare('SELECT price FROM menu_items WHERE id = ?');
        $row->execute([$mid]);
        $price = (float)$row->fetchColumn();
        $qty   = rand(1, 3);
        $total += $price * $qty;
        $lines[] = ['mid' => $mid, 'qty' => $qty, 'price' => $price];
    }

    $stmt = $db->prepare(
        'INSERT INTO orders (customer_id, restaurant_id, driver_id, status, total_price) VALUES (?, ?, ?, ?, ?)'
    );
    $driverId = in_array($status, ['out_for_delivery', 'delivered'], true)
        ? $driverIds[array_rand($driverIds)]
        : null;
    $stmt->execute([$customerId, $rid, $driverId, $status, $total]);
    $orderId = (int)$db->lastInsertId();

    foreach ($lines as $line) {
        $db->prepare(
            'INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
        )->execute([$orderId, $line['mid'], $line['qty'], $line['price']]);
    }
}

echo "Orders: 15\n";
echo "Done. Login credentials: password = 'password' for all seeded users.\n";
echo "Admin emails:    admin1@example.com ... admin3@example.com\n";
echo "Customer emails: customer1@example.com ... customer10@example.com\n";
echo "Driver emails:   driver1@example.com, driver2@example.com\n";
```

- [ ] **Step 2: Run the seeder**

```bash
php database/seeder.php
```

Expected:
```
Seeding...
Users: 15
Restaurants: 5
Menu items: 20
Orders: 15
Done. Login credentials: password = 'password' for all seeded users.
```

- [ ] **Step 3: Commit**

```bash
git add database/seeder.php
git commit -m "feat: add Faker database seeder"
```

---

## Task 16: End-to-End Smoke Test

Verify the full flow before writing the Postman collection.

- [ ] **Step 1: Register a customer**

```bash
curl -s -X POST http://localhost/Szalmaprojekt/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@test.com","password":"secret123","role":"customer"}'
```

Expected: `{"status":201,"message":"Created","data":{...}}`

- [ ] **Step 2: Login and capture token**

```bash
curl -s -X POST http://localhost/Szalmaprojekt/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin1@example.com","password":"password"}'
```

Copy the `token` value. Use it as `TOKEN` below.

- [ ] **Step 3: List restaurants (public)**

```bash
curl -s http://localhost/Szalmaprojekt/api/restaurants
```

Expected: `{"status":200,...,"data":[...5 restaurants...]}` 

- [ ] **Step 4: Place an order (use customer token)**

First login as customer1@example.com. Then:

```bash
curl -s -X POST http://localhost/Szalmaprojekt/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"restaurant_id":1,"items":[{"menu_item_id":1,"quantity":2}]}'
```

Expected: `{"status":201,"message":"Created","data":{...}}`

- [ ] **Step 5: Run unit tests one final time**

```bash
vendor/bin/phpunit --verbose
```

Expected: `OK (9 tests, 10 assertions)`

---

## Task 17: Postman Collection

**Files:**
- Create: `docs/postman_collection.json`

- [ ] **Step 1: Create `docs/postman_collection.json`**

```json
{
    "info": {
        "name": "Food Delivery API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        { "key": "base_url", "value": "http://localhost/Szalmaprojekt" },
        { "key": "token",    "value": "" }
    ],
    "item": [
        {
            "name": "Auth",
            "item": [
                {
                    "name": "Register",
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/api/auth/register",
                        "header": [{ "key": "Content-Type", "value": "application/json" }],
                        "body": {
                            "mode": "raw",
                            "raw": "{\"name\":\"John Doe\",\"email\":\"john@example.com\",\"password\":\"secret123\",\"role\":\"customer\"}"
                        }
                    }
                },
                {
                    "name": "Login",
                    "event": [{
                        "listen": "test",
                        "script": {
                            "exec": ["var r = pm.response.json(); if(r.data && r.data.token){ pm.collectionVariables.set('token', r.data.token); }"]
                        }
                    }],
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/api/auth/login",
                        "header": [{ "key": "Content-Type", "value": "application/json" }],
                        "body": {
                            "mode": "raw",
                            "raw": "{\"email\":\"admin1@example.com\",\"password\":\"password\"}"
                        }
                    }
                }
            ]
        },
        {
            "name": "Restaurants",
            "item": [
                {
                    "name": "List Restaurants",
                    "request": { "method": "GET", "url": "{{base_url}}/api/restaurants" }
                },
                {
                    "name": "Get Restaurant",
                    "request": { "method": "GET", "url": "{{base_url}}/api/restaurants/1" }
                },
                {
                    "name": "Create Restaurant",
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/api/restaurants",
                        "header": [
                            { "key": "Content-Type", "value": "application/json" },
                            { "key": "Authorization", "value": "Bearer {{token}}" }
                        ],
                        "body": {
                            "mode": "raw",
                            "raw": "{\"name\":\"Pizza Palace\",\"address\":\"123 Main St\",\"phone\":\"+1234567890\"}"
                        }
                    }
                },
                {
                    "name": "Update Restaurant",
                    "request": {
                        "method": "PUT",
                        "url": "{{base_url}}/api/restaurants/1",
                        "header": [
                            { "key": "Content-Type", "value": "application/json" },
                            { "key": "Authorization", "value": "Bearer {{token}}" }
                        ],
                        "body": { "mode": "raw", "raw": "{\"phone\":\"+9876543210\"}" }
                    }
                },
                {
                    "name": "Delete Restaurant",
                    "request": {
                        "method": "DELETE",
                        "url": "{{base_url}}/api/restaurants/1",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                }
            ]
        },
        {
            "name": "Menu Items",
            "item": [
                {
                    "name": "List Menu",
                    "request": { "method": "GET", "url": "{{base_url}}/api/restaurants/1/menu" }
                },
                {
                    "name": "Add Menu Item",
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/api/restaurants/1/menu",
                        "header": [
                            { "key": "Content-Type", "value": "application/json" },
                            { "key": "Authorization", "value": "Bearer {{token}}" }
                        ],
                        "body": {
                            "mode": "raw",
                            "raw": "{\"name\":\"Margherita\",\"description\":\"Classic tomato and mozzarella\",\"price\":9.99}"
                        }
                    }
                },
                {
                    "name": "Update Menu Item",
                    "request": {
                        "method": "PUT",
                        "url": "{{base_url}}/api/restaurants/1/menu/1",
                        "header": [
                            { "key": "Content-Type", "value": "application/json" },
                            { "key": "Authorization", "value": "Bearer {{token}}" }
                        ],
                        "body": { "mode": "raw", "raw": "{\"price\":11.50,\"available\":1}" }
                    }
                },
                {
                    "name": "Delete Menu Item",
                    "request": {
                        "method": "DELETE",
                        "url": "{{base_url}}/api/restaurants/1/menu/1",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                }
            ]
        },
        {
            "name": "Orders",
            "item": [
                {
                    "name": "List Orders",
                    "request": {
                        "method": "GET",
                        "url": "{{base_url}}/api/orders",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                },
                {
                    "name": "Get Order",
                    "request": {
                        "method": "GET",
                        "url": "{{base_url}}/api/orders/1",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                },
                {
                    "name": "Place Order",
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/api/orders",
                        "header": [
                            { "key": "Content-Type", "value": "application/json" },
                            { "key": "Authorization", "value": "Bearer {{token}}" }
                        ],
                        "body": {
                            "mode": "raw",
                            "raw": "{\"restaurant_id\":1,\"items\":[{\"menu_item_id\":1,\"quantity\":2},{\"menu_item_id\":2,\"quantity\":1}]}"
                        }
                    }
                },
                {
                    "name": "Update Order Status",
                    "request": {
                        "method": "PUT",
                        "url": "{{base_url}}/api/orders/1/status",
                        "header": [
                            { "key": "Content-Type", "value": "application/json" },
                            { "key": "Authorization", "value": "Bearer {{token}}" }
                        ],
                        "body": { "mode": "raw", "raw": "{\"status\":\"preparing\"}" }
                    }
                },
                {
                    "name": "Cancel Order",
                    "request": {
                        "method": "DELETE",
                        "url": "{{base_url}}/api/orders/1",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                }
            ]
        },
        {
            "name": "Users",
            "item": [
                {
                    "name": "List Users",
                    "request": {
                        "method": "GET",
                        "url": "{{base_url}}/api/users",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                },
                {
                    "name": "Get User",
                    "request": {
                        "method": "GET",
                        "url": "{{base_url}}/api/users/1",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                },
                {
                    "name": "Update User",
                    "request": {
                        "method": "PUT",
                        "url": "{{base_url}}/api/users/1",
                        "header": [
                            { "key": "Content-Type", "value": "application/json" },
                            { "key": "Authorization", "value": "Bearer {{token}}" }
                        ],
                        "body": { "mode": "raw", "raw": "{\"name\":\"Updated Name\"}" }
                    }
                },
                {
                    "name": "Delete User",
                    "request": {
                        "method": "DELETE",
                        "url": "{{base_url}}/api/users/1",
                        "header": [{ "key": "Authorization", "value": "Bearer {{token}}" }]
                    }
                }
            ]
        }
    ]
}
```

- [ ] **Step 2: Commit**

```bash
git add docs/postman_collection.json
git commit -m "feat: add Postman collection for all endpoints"
```

---

## Self-Review Against Spec

| Spec Section | Covered | Task |
|---|---|---|
| Auth register/login | ✅ | Task 10 |
| JWT (firebase/php-jwt, 1h expiry, role in payload) | ✅ | Task 5, 10 |
| Restaurant CRUD | ✅ | Task 11 |
| Menu item CRUD | ✅ | Task 11 |
| Order CRUD + status update + cancel-pending-only | ✅ | Task 12 |
| User CRUD (admin-only delete/list, self-edit) | ✅ | Task 13 |
| PDO prepared statements everywhere | ✅ | Task 8 |
| bcrypt passwords | ✅ | Task 8 |
| `htmlspecialchars()` on all string outputs | ✅ | Task 8 |
| Passwords never returned in API responses | ✅ | Task 8 (User::findById selects specific cols) |
| Access logging on every request | ✅ | Task 14 |
| DeviceDetector (type/browser/OS) | ✅ | Task 9 |
| IpLookup (country/city/ISP via ip-api.com) | ✅ | Task 9 |
| Database migrations with all 6 tables | ✅ | Task 2 |
| Seeder: 3 admin, 10 customer, 2 driver, 5 restaurant, 20 menu items, 15 orders | ✅ | Task 15 |
| Postman collection | ✅ | Task 17 |
| All responses `{status, message, data}` | ✅ | Task 3 |
