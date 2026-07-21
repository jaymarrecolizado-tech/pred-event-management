<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;

class AdminEventsController
{
    private function requireAdmin(): bool
    {
        if (empty($_SESSION['admin_id'])) { header('Location: ?r=admin_login'); return false; }
        return true;
    }

    public function list(): void
    {
        if (!$this->requireAdmin()) return;
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id,name,enforce_single_time_in,active,created_at FROM events ORDER BY id DESC')->fetchAll();
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_events.php';
    }

    public function create(): void
    {
        if (!$this->requireAdmin()) return;
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) { http_response_code(400); echo 'Invalid CSRF'; return; }
        $name = trim((string)($_POST['name'] ?? ''));
        $enforce = isset($_POST['enforce']) ? 1 : 0;
        if ($name === '') { http_response_code(422); echo 'Missing name'; return; }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO events (name, enforce_single_time_in, active) VALUES (?,?,0)');
        $stmt->execute([$name, $enforce]);
        header('Location: ?r=admin_events');
    }

    public function setActive(): void
    {
        if (!$this->requireAdmin()) return;
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) { http_response_code(400); echo 'Invalid CSRF'; return; }
        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $pdo->exec('UPDATE events SET active=0');
        $s = $pdo->prepare('UPDATE events SET active=1 WHERE id=?');
        $s->execute([$id]);
        $pdo->commit();
        header('Location: ?r=admin_events');
    }
}