<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;

class AdminAttendanceGalleryController
{
    public function list(): void
    {
        if (empty($_SESSION['admin_id'])) { header('Location: ?r=admin_login'); return; }
        $pdo = Database::pdo();
        $date = trim((string)($_GET['date'] ?? ''));
        $agency = trim((string)($_GET['agency'] ?? ''));
        $where = [];
        $bind = [];
        if ($date !== '') { $where[] = 'a.attendance_date = ?'; $bind[] = $date; }
        if ($agency !== '') { $where[] = 'p.agency LIKE ?'; $bind[] = "%{$agency}%"; }
        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 24;
        $offset = ($page - 1) * $per;
        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS a.id,a.signature_path,a.attendance_date,a.time_in,p.first_name,p.last_name,p.agency FROM attendance a JOIN participants p ON p.id=a.participant_id $sqlWhere ORDER BY a.id DESC LIMIT $per OFFSET $offset");
        $stmt->execute($bind);
        $items = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT FOUND_ROWS() AS t')->fetch()['t'];
        $pages = max(1, (int)ceil($total / $per));
        $data = compact('items','date','agency','page','pages');
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_attendance_gallery.php';
    }
}