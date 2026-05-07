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
            'INSERT INTO restaurants (name, address, phone, admin_id, description, cuisine, image_url, opening_hours)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            htmlspecialchars($data['name'],    ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['address'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['phone'],   ENT_QUOTES, 'UTF-8'),
            $adminId,
            htmlspecialchars($data['description']   ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($data['cuisine']       ?? '', ENT_QUOTES, 'UTF-8'),
            $data['image_url'] ?? null,
            htmlspecialchars($data['opening_hours'] ?? '', ENT_QUOTES, 'UTF-8'),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data, int $adminId): bool
    {
        $fields = [];
        $values = [];

        $text = ['name', 'address', 'phone', 'description', 'cuisine', 'opening_hours'];
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
