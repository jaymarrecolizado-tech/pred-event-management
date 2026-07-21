<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class Logger
{
    public static function log(?int $adminId, string $action, array $detail = []): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO action_logs (admin_id, action, detail) VALUES (?,?,?)');
        $stmt->execute([$adminId, $action, json_encode($detail)]);
    }
}