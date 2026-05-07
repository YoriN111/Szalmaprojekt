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

for ($i = 1; $i <= 3; $i++) {
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role, email_verified_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $faker->name(),
        "admin{$i}@example.com",
        password_hash('password', PASSWORD_BCRYPT),
        'admin',
    ]);
    $adminIds[] = (int)$db->lastInsertId();
}

for ($i = 1; $i <= 10; $i++) {
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role, email_verified_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $faker->name(),
        "customer{$i}@example.com",
        password_hash('password', PASSWORD_BCRYPT),
        'customer',
    ]);
    $customerIds[] = (int)$db->lastInsertId();
}

for ($i = 1; $i <= 2; $i++) {
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role, email_verified_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $faker->name(),
        "driver{$i}@example.com",
        password_hash('password', PASSWORD_BCRYPT),
        'driver',
    ]);
    $driverIds[] = (int)$db->lastInsertId();
}

echo "Users: " . (count($adminIds) + count($customerIds) + count($driverIds)) . "\n";

// ── Restaurants ───────────────────────────────────────────────────────────────
$restaurantIds = [];
// admin1 owns 2, admin2 owns 2, admin3 owns 1
$assignments = [
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

// ── Orders (15) ───────────────────────────────────────────────────────────────
$statuses = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

for ($i = 0; $i < 15; $i++) {
    $rid        = $restaurantIds[array_rand($restaurantIds)];
    $customerId = $customerIds[array_rand($customerIds)];
    $status     = $statuses[array_rand($statuses)];

    $available = $menuItemIds[$rid];
    $count     = min(rand(1, 3), count($available));
    $chosen    = (array)array_rand(array_flip($available), $count);

    $total = 0.0;
    $lines = [];
    foreach ($chosen as $mid) {
        $row = $db->prepare('SELECT price FROM menu_items WHERE id = ?');
        $row->execute([$mid]);
        $price = (float)$row->fetchColumn();
        $qty   = rand(1, 3);
        $total += $price * $qty;
        $lines[] = ['mid' => $mid, 'qty' => $qty, 'price' => $price];
    }

    $driverId = in_array($status, ['out_for_delivery', 'delivered'], true)
        ? $driverIds[array_rand($driverIds)]
        : null;

    $stmt = $db->prepare(
        'INSERT INTO orders (customer_id, restaurant_id, driver_id, status, total_price) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$customerId, $rid, $driverId, $status, $total]);
    $orderId = (int)$db->lastInsertId();

    foreach ($lines as $line) {
        $db->prepare(
            'INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
        )->execute([$orderId, $line['mid'], $line['qty'], $line['price']]);
    }
}

echo "Orders: 15\n";
echo "Done!\n\n";
echo "Credentials (password = 'password' for all):\n";
echo "  Admins:    admin1@example.com ... admin3@example.com\n";
echo "  Customers: customer1@example.com ... customer10@example.com\n";
echo "  Drivers:   driver1@example.com, driver2@example.com\n";
