<?php
namespace App\Models;

use App\Database;
use App\Response;

class Order
{
    public static function findAll(): array
    {
        return Database::getInstance()
            ->query(
                'SELECT o.*,
                        r.name    AS restaurant_name,
                        u.name    AS customer_name
                 FROM orders o
                 JOIN restaurants r ON r.id = o.restaurant_id
                 JOIN users      u ON u.id = o.customer_id
                 ORDER BY o.created_at DESC'
            )
            ->fetchAll();
    }

    public static function findByCustomer(int $customerId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT o.*, r.name AS restaurant_name
             FROM orders o
             JOIN restaurants r ON r.id = o.restaurant_id
             WHERE o.customer_id = ?
             ORDER BY o.created_at DESC'
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function findByDriver(): array
    {
        return Database::getInstance()
            ->query(
                "SELECT o.*,
                        r.name    AS restaurant_name,
                        r.address AS restaurant_address,
                        r.phone   AS restaurant_phone,
                        u.name    AS customer_name,
                        u.email   AS customer_email
                 FROM orders o
                 JOIN restaurants r ON r.id = o.restaurant_id
                 JOIN users      u ON u.id = o.customer_id
                 WHERE o.status IN ('preparing','out_for_delivery')
                 ORDER BY o.created_at DESC"
            )
            ->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT o.*,
                    r.name    AS restaurant_name,
                    r.address AS restaurant_address,
                    r.phone   AS restaurant_phone,
                    u.name    AS customer_name,
                    u.email   AS customer_email
             FROM orders o
             JOIN restaurants r ON r.id = o.restaurant_id
             JOIN users      u ON u.id = o.customer_id
             WHERE o.id = ?'
        );
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

        $total     = 0.0;
        $lineItems = [];

        foreach ($data['items'] as $item) {
            $menuItem = MenuItem::findById((int)$item['menu_item_id']);
            if (!$menuItem || !$menuItem['available']) {
                Response::error('Menu item ' . $item['menu_item_id'] . ' is unavailable', 400);
            }
            $qty          = (int)$item['quantity'];
            $total       += (float)$menuItem['price'] * $qty;
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
