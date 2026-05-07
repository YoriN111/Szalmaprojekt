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

    public static function findByVerifyToken(string $token): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM users WHERE email_verify_token = ?'
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function findByResetToken(string $token): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()'
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function setEmailVerifyToken(int $id, string $token): void
    {
        Database::getInstance()
            ->prepare('UPDATE users SET email_verify_token = ? WHERE id = ?')
            ->execute([$token, $id]);
    }

    public static function markEmailVerified(int $id): void
    {
        Database::getInstance()
            ->prepare('UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL WHERE id = ?')
            ->execute([$id]);
    }

    public static function setResetToken(int $id, string $token): void
    {
        Database::getInstance()
            ->prepare('UPDATE users SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')
            ->execute([$token, $id]);
    }

    public static function clearResetToken(int $id): void
    {
        Database::getInstance()
            ->prepare('UPDATE users SET reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?')
            ->execute([$id]);
    }
}
