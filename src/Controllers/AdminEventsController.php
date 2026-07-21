<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use PDO;

class AdminEventsController
{
    private function requireAdmin(): bool
    {
        if (empty($_SESSION['admin_id'])) {
            header('Location: ?r=admin_login');
            return false;
        }
        return true;
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private function requireCsrf(): bool
    {
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check((string)$_POST['csrf'])) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return false;
        }
        return true;
    }

    public function list(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $pdo = Database::pdo();
        $hasCoaBatches = false;
        try {
            $hasCoaBatches = (bool)$pdo->query("SHOW TABLES LIKE 'coa_send_batches'")->fetch();
        } catch (\Throwable $e) {
            $hasCoaBatches = false;
        }

        $coaSelect = $hasCoaBatches
            ? '(SELECT COUNT(*) FROM coa_send_batches b WHERE b.event_id = e.id) AS coa_batch_count'
            : '0 AS coa_batch_count';

        $rows = $pdo->query(
            "SELECT e.id, e.name, e.enforce_single_time_in, e.active, e.created_at,
                    (SELECT COUNT(*) FROM attendance a WHERE a.event_id = e.id) AS attendance_count,
                    {$coaSelect}
             FROM events e
             ORDER BY e.active DESC, e.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_events.php';
    }

    public function create(): void
    {
        if (!$this->requireAdmin() || !$this->requireCsrf()) {
            return;
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $enforce = isset($_POST['enforce']) ? 1 : 0;
        if ($name === '') {
            $this->flash('danger', 'Event name is required.');
            header('Location: ?r=admin_events');
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO events (name, enforce_single_time_in, active) VALUES (?,?,0)');
        $stmt->execute([$name, $enforce]);
        $this->flash('success', 'Event created. Use “Set Active” when you are ready to use it.');
        header('Location: ?r=admin_events');
    }

    public function setActive(): void
    {
        if (!$this->requireAdmin() || !$this->requireCsrf()) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('danger', 'Invalid event.');
            header('Location: ?r=admin_events');
            return;
        }
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id, name FROM events WHERE id = ?');
        $exists->execute([$id]);
        $row = $exists->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->flash('danger', 'Event not found.');
            header('Location: ?r=admin_events');
            return;
        }

        $pdo->beginTransaction();
        $pdo->exec('UPDATE events SET active = 0');
        $s = $pdo->prepare('UPDATE events SET active = 1 WHERE id = ?');
        $s->execute([$id]);
        $pdo->commit();

        $this->flash('success', '“' . $row['name'] . '” is now the active event.');
        header('Location: ?r=admin_events');
    }

    public function deactivate(): void
    {
        if (!$this->requireAdmin() || !$this->requireCsrf()) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('danger', 'Invalid event.');
            header('Location: ?r=admin_events');
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE events SET active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $this->flash('success', 'Event deactivated. Set another event active before taking attendance.');
        } else {
            $this->flash('danger', 'Event not found.');
        }
        header('Location: ?r=admin_events');
    }

    public function delete(): void
    {
        if (!$this->requireAdmin() || !$this->requireCsrf()) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('danger', 'Invalid event.');
            header('Location: ?r=admin_events');
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, name, active FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->flash('danger', 'Event not found.');
            header('Location: ?r=admin_events');
            return;
        }

        if ((int)$row['active'] === 1) {
            $this->flash('danger', 'Deactivate the event first before deleting it.');
            header('Location: ?r=admin_events');
            return;
        }

        $usage = $this->usageCounts($pdo, $id);
        if ($usage['attendance'] > 0 || $usage['coa'] > 0) {
            $this->flash(
                'danger',
                'Cannot delete “' . $row['name'] . '” — it has '
                . $usage['attendance'] . ' attendance record(s) and '
                . $usage['coa'] . ' CoA batch(es). Deactivate it instead.'
            );
            header('Location: ?r=admin_events');
            return;
        }

        $del = $pdo->prepare('DELETE FROM events WHERE id = ? AND active = 0');
        $del->execute([$id]);
        $this->flash('success', 'Deleted “' . $row['name'] . '”.');
        header('Location: ?r=admin_events');
    }

    /**
     * @return array{attendance:int,coa:int}
     */
    private function usageCounts(PDO $pdo, int $eventId): array
    {
        $att = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE event_id = ?');
        $att->execute([$eventId]);
        $coa = 0;
        try {
            $c = $pdo->prepare('SELECT COUNT(*) FROM coa_send_batches WHERE event_id = ?');
            $c->execute([$eventId]);
            $coa = (int)$c->fetchColumn();
        } catch (\Throwable $e) {
            $coa = 0;
        }
        return [
            'attendance' => (int)$att->fetchColumn(),
            'coa' => $coa,
        ];
    }
}
