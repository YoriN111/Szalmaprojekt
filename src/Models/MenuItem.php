<?php
namespace App\Models;

use App\Database;

class MenuItem
{
    public static function findByRestaurant(int $restaurantId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM menu_items WHERE restaurant_id = ? ORDER BY category, id'
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
            'INSERT INTO menu_items (restaurant_id, name, description, image_url, category, price, available)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $restaurantId,
            htmlspecialchars($data['name'],        ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            $data['image_url'] ?? null,
            htmlspecialchars($data['category']    ?? '', ENT_QUOTES, 'UTF-8'),
            (float)$data['price'],
            isset($data['available']) ? (int)$data['available'] : 1,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $restaurantId, array $data): bool
    {
        $fields = [];
        $values = [];

        $text = ['name', 'description', 'category'];
        foreach ($text as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = ?";
                $values[] = htmlspecialchars($data[$col] ?? '', ENT_QUOTES, 'UTF-8');
            }
        }
        if (array_key_exists('image_url', $data)) {
            $fields[] = 'image_url = ?';
            $values[] = $data['image_url'];
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
