<?php
declare(strict_types=1);

namespace App\Services;

class Logger
{
    public static function log(?int $adminId, string $action, array $detail = []): void
    {
        try {
            $pdo = Database::pdo();
            if (!self::tableReady($pdo)) {
                error_log('Logger skipped (action_logs missing): ' . $action);
                return;
            }
            $stmt = $pdo->prepare('INSERT INTO action_logs (admin_id, action, detail) VALUES (?,?,?)');
            $stmt->execute([$adminId, $action, json_encode($detail)]);
        } catch (\Throwable $e) {
            error_log('Logger failed: ' . $e->getMessage() . ' action=' . $action);
        }
    }

    private static function tableReady(\PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'action_logs'");
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
