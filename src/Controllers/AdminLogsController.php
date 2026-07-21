<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;

class AdminLogsController
{
    public function list(): void
    {
        if (empty($_SESSION['admin_id'])) { header('Location: ?r=admin_login'); return; }
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id, admin_id, action, detail, created_at FROM action_logs ORDER BY id DESC LIMIT 200')->fetchAll();
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_logs.php';
    }
}