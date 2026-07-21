<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\SignatureService;

class AdminAttendanceController
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
        $date = trim((string)($_GET['date'] ?? ''));
        $agency = trim((string)($_GET['agency'] ?? ''));
        $name = trim((string)($_GET['name'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $offset = ($page - 1) * $per;

        $selectedDate = $date !== '' ? $date : date('Y-m-d');

        // Simple date join (no event filter) so signed rows always show for the selected date.
        // Correlated ORDER BY/LIMIT subqueries have crashed Attendance on some MariaDB hosts.
        $joinOn = 'a.participant_id = p.id AND a.attendance_date = ?';
        $bind = [$selectedDate];

        $where = [];
        if ($agency !== '') { $where[] = 'p.agency LIKE ?'; $bind[] = "%{$agency}%"; }
        if ($name !== '') { $where[] = '(p.first_name LIKE ? OR p.last_name LIKE ?)'; $bind[] = "%{$name}%"; $bind[] = "%{$name}%"; }
        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        try {
            $stmt = $pdo->prepare(
                "SELECT SQL_CALC_FOUND_ROWS
                    p.id AS participant_id,
                    p.uuid,
                    p.first_name,
                    p.last_name,
                    p.agency,
                    p.is_vip,
                    a.id AS attendance_id,
                    a.attendance_date,
                    a.time_in,
                    a.signature_path,
                    a.status AS attendance_status
                 FROM participants p
                 LEFT JOIN attendance a ON {$joinOn}
                 {$sqlWhere}
                 ORDER BY
                    CASE WHEN p.is_vip = 1 THEN 0 ELSE 1 END ASC,
                    CASE
                        WHEN a.status = 'absent' THEN 3
                        WHEN a.id IS NOT NULL AND COALESCE(a.signature_path, '') <> '' THEN 1
                        ELSE 2
                    END ASC,
                    p.last_name ASC,
                    p.first_name ASC
                 LIMIT {$per} OFFSET {$offset}"
            );
            $stmt->execute($bind);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('admin_attendance list failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'Attendance page failed to load. Please contact the administrator.';
            return;
        }
        foreach ($rows as &$row) {
            $row['guest_status'] = $this->resolveGuestStatus($row);
        }
        unset($row);

        $total = (int)$pdo->query('SELECT FOUND_ROWS() AS t')->fetch()['t'];
        $pages = max(1, (int)ceil($total / $per));

        $kpi = $this->computeKpis($pdo, $selectedDate);

        $date = $selectedDate;
        $data = array_merge(
            compact('rows', 'page', 'pages', 'date', 'agency', 'name', 'total', 'selectedDate'),
            [
                'selectedDateCount' => $kpi['dateCount'],
                'totalRegistered' => $kpi['totalRegistered'],
                'attendanceRate' => $kpi['attendanceRate'],
                'recentCount' => $kpi['recentCount'],
                'peakHourText' => $kpi['peakHourText'],
                'vicinityCount' => $kpi['vicinityCount'],
                'absentCount' => $kpi['absentCount'],
            ]
        );
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_attendance.php';
    }

    public function kpiJson(): void
    {
        if (!$this->requireAdmin()) return;
        header('Content-Type: application/json');
        $pdo = Database::pdo();
        $selectedDate = isset($_GET['date']) && trim($_GET['date']) !== '' ? trim($_GET['date']) : date('Y-m-d');
        echo json_encode($this->computeKpis($pdo, $selectedDate));
    }

    public function kpiStream(): void
    {
        // Long-lived SSE holds PHP session locks and exhausts Apache/WAMP workers.
        // Prefer short JSON polling via kpiJson(); keep this route as a safe no-op redirect.
        if (!$this->requireAdmin()) return;
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $pdo = Database::pdo();
        $selectedDate = isset($_GET['date']) && trim($_GET['date']) !== '' ? trim($_GET['date']) : date('Y-m-d');
        echo json_encode($this->computeKpis($pdo, $selectedDate));
    }

    private function getActiveEventId(\PDO $pdo): ?int
    {
        $event = $pdo->query('SELECT id FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch();
        return $event ? (int)$event['id'] : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveGuestStatus(array $row): string
    {
        $status = (string)($row['attendance_status'] ?? $row['status'] ?? '');
        if ($status === 'absent') {
            return 'absent';
        }
        // Present = signed-in with a signature. Do not use !empty(id): MySQL can
        // store attendance.id = 0, and empty(0) is true in PHP (hides Present + preview).
        if (trim((string)($row['signature_path'] ?? '')) !== '') {
            return 'present';
        }
        return 'in_vicinity';
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function presentAttendanceScope(\PDO $pdo, string $alias = 'a'): array
    {
        $sql = "COALESCE({$alias}.signature_path, '') <> '' AND COALESCE({$alias}.status, 'present') = 'present'";
        $params = [];
        $eventId = $this->getActiveEventId($pdo);
        if ($eventId !== null) {
            $sql .= " AND ({$alias}.event_id = ? OR {$alias}.event_id IS NULL)";
            $params[] = $eventId;
        }
        return [$sql, $params];
    }

    private function getGuestStatusForDate(\PDO $pdo, int $participantId, string $date): string
    {
        $eventId = $this->getActiveEventId($pdo);
        $sql = 'SELECT id, signature_path, status FROM attendance WHERE participant_id = ? AND attendance_date = ?';
        $bind = [$participantId, $date];
        if ($eventId !== null) {
            $sql .= ' AND (event_id = ? OR event_id IS NULL)';
            $bind[] = $eventId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        $row = $stmt->fetch();
        if (!$row) {
            return 'in_vicinity';
        }
        return $this->resolveGuestStatus([
            'id' => $row['id'],
            'signature_path' => $row['signature_path'],
            'attendance_status' => $row['status'] ?? 'present',
        ]);
    }

    private function computeKpis(\PDO $pdo, string $selectedDate): array
    {
        [$scopeSql, $scopeParams] = $this->presentAttendanceScope($pdo);

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT a.participant_id) FROM attendance a WHERE a.attendance_date = ? AND {$scopeSql}"
        );
        $stmt->execute(array_merge([$selectedDate], $scopeParams));
        $dateCount = (int)$stmt->fetchColumn();

        $totalRegistered = (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();

        $eventId = $this->getActiveEventId($pdo);
        $absentSql = "SELECT COUNT(DISTINCT a.participant_id) FROM attendance a WHERE a.attendance_date = ? AND a.status = 'absent'";
        $absentBind = [$selectedDate];
        if ($eventId !== null) {
            $absentSql .= ' AND (a.event_id = ? OR a.event_id IS NULL)';
            $absentBind[] = $eventId;
        }
        $absentStmt = $pdo->prepare($absentSql);
        $absentStmt->execute($absentBind);
        $absentCount = (int)$absentStmt->fetchColumn();

        $vicinityCount = max(0, $totalRegistered - $dateCount - $absentCount);
        // Assumed in-vicinity guests count as accounted for unless explicitly absent.
        $accountedFor = max(0, $totalRegistered - $absentCount);
        $attendanceRate = $totalRegistered > 0 ? round(($accountedFor / $totalRegistered) * 100, 1) : 0;

        $recentCount = 0;
        if ($selectedDate === date('Y-m-d')) {
            $lastHourTime = date('H:i:s', strtotime('-1 hour'));
            $stmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT a.participant_id) FROM attendance a WHERE a.attendance_date = ? AND a.time_in >= ? AND {$scopeSql}"
            );
            $stmt->execute(array_merge([$selectedDate, $lastHourTime], $scopeParams));
            $recentCount = (int)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            "SELECT HOUR(a.time_in) as hour, COUNT(DISTINCT a.participant_id) as cnt FROM attendance a WHERE a.attendance_date = ? AND {$scopeSql} GROUP BY HOUR(a.time_in) ORDER BY cnt DESC LIMIT 1"
        );
        $stmt->execute(array_merge([$selectedDate], $scopeParams));
        $peakHour = $stmt->fetch();
        $peakHourText = $peakHour ? sprintf('%02d:00 (%d sign-ins)', (int)$peakHour['hour'], (int)$peakHour['cnt']) : 'N/A';

        return [
            'dateCount' => $dateCount,
            'totalRegistered' => $totalRegistered,
            'attendanceRate' => $attendanceRate,
            'recentCount' => $recentCount,
            'peakHourText' => $peakHourText,
            'vicinityCount' => $vicinityCount,
            'absentCount' => $absentCount,
            'selectedDate' => $selectedDate,
            'timestamp' => time(),
        ];
    }

    public function searchParticipants(): void
    {
        if (!$this->requireAdmin()) return;
        header('Content-Type: application/json');

        $query = trim((string)($_GET['q'] ?? ''));
        if (strlen($query) < 2) {
            echo json_encode(['results' => []]);
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT id, uuid, first_name, last_name, middle_name, agency, email, is_vip FROM participants WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? ORDER BY is_vip DESC, last_name, first_name LIMIT 20");
        $searchTerm = "%{$query}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $results = $stmt->fetchAll();

        $attendanceDate = isset($_GET['date']) && trim((string)$_GET['date']) !== ''
            ? trim((string)$_GET['date'])
            : date('Y-m-d');

        foreach ($results as &$result) {
            $status = $this->getGuestStatusForDate($pdo, (int)$result['id'], $attendanceDate);
            $result['guest_status'] = $status;
            $result['already_marked'] = $status === 'present';
        }

        echo json_encode(['results' => $results]);
    }

    public function markAbsent(): void
    {
        if (!$this->requireAdmin()) return;
        header('Content-Type: application/json');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('csrf_check') || !csrf_check($csrf)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            return;
        }

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid']);
            return;
        }

        $participantId = (int)($payload['participant_id'] ?? 0);
        $attendanceDate = trim((string)($payload['date'] ?? date('Y-m-d')));
        if ($participantId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'invalid']);
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, uuid FROM participants WHERE id = ?');
        $stmt->execute([$participantId]);
        $participant = $stmt->fetch();
        if (!$participant) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            return;
        }

        $eventId = $this->getActiveEventId($pdo);
        $existing = $this->findAttendanceRow($pdo, $participantId, $attendanceDate, $eventId);
        if ($existing && trim((string)($existing['signature_path'] ?? '')) !== '' && ($existing['status'] ?? 'present') === 'present') {
            echo json_encode(['ok' => false, 'error' => 'already_present']);
            return;
        }

        if ($existing) {
            $upd = $pdo->prepare("UPDATE attendance SET status = 'absent', signature_path = NULL, time_in = ? WHERE id = ?");
            $upd->execute([date('H:i:s'), (int)$existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id, status) VALUES (?,?,?,?,?,'absent')");
            $ins->execute([$participantId, $attendanceDate, date('H:i:s'), null, $eventId]);
        }

        echo json_encode(['ok' => true, 'message' => 'Guest marked absent']);
    }

    public function clearAbsent(): void
    {
        if (!$this->requireAdmin()) return;
        header('Content-Type: application/json');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('csrf_check') || !csrf_check($csrf)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            return;
        }

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid']);
            return;
        }

        $participantId = (int)($payload['participant_id'] ?? 0);
        $attendanceDate = trim((string)($payload['date'] ?? date('Y-m-d')));
        if ($participantId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'invalid']);
            return;
        }

        $pdo = Database::pdo();
        $eventId = $this->getActiveEventId($pdo);
        $existing = $this->findAttendanceRow($pdo, $participantId, $attendanceDate, $eventId);
        if ($existing && ($existing['status'] ?? '') === 'absent') {
            $del = $pdo->prepare('DELETE FROM attendance WHERE id = ?');
            $del->execute([(int)$existing['id']]);
        }

        echo json_encode(['ok' => true, 'message' => 'Guest returned to in vicinity']);
    }

    /**
     * @return array<string,mixed>|false
     */
    private function findAttendanceRow(\PDO $pdo, int $participantId, string $date, ?int $eventId)
    {
        $sql = 'SELECT id, signature_path, status FROM attendance WHERE participant_id = ? AND attendance_date = ?';
        $bind = [$participantId, $date];
        if ($eventId !== null) {
            $sql .= ' AND (event_id = ? OR event_id IS NULL)';
            $bind[] = $eventId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetch();
    }

    public function manualAttendance(): void
    {
        if (!$this->requireAdmin()) return;
        header('Content-Type: application/json');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('csrf_check') || !csrf_check($csrf)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            return;
        }

        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid']);
            return;
        }

        $participantId = (int)($payload['participant_id'] ?? 0);
        $signature = (string)($payload['signature'] ?? '');
        $attendanceDate = trim((string)($payload['date'] ?? date('Y-m-d')));

        if ($participantId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'missing_participant']);
            return;
        }

        $pdo = Database::pdo();

        $stmt = $pdo->prepare('SELECT id, uuid FROM participants WHERE id = ?');
        $stmt->execute([$participantId]);
        $participant = $stmt->fetch();
        if (!$participant) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            return;
        }

        if ($signature === '') {
            $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        }

        $event = $pdo->query('SELECT id, enforce_single_time_in FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch();
        $eventId = $event ? (int)$event['id'] : null;
        $enforce = $event ? (int)$event['enforce_single_time_in'] === 1 : false;

        $existing = $this->findAttendanceRow($pdo, $participantId, $attendanceDate, $eventId);
        if ($existing && ($existing['status'] ?? 'present') === 'present' && trim((string)($existing['signature_path'] ?? '')) !== '') {
            if ($enforce) {
                echo json_encode(['ok' => false, 'error' => 'already_marked']);
                return;
            }
        }

        try {
            $path = SignatureService::saveBase64($participant['uuid'], $signature);
        } catch (\Throwable $e) {
            error_log('manualAttendance signature save failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'signature_save_failed']);
            return;
        }
        $time = date('H:i:s');

        if ($existing) {
            $upd = $pdo->prepare("UPDATE attendance SET status = 'present', signature_path = ?, time_in = ?, event_id = ? WHERE id = ?");
            $upd->execute([$path, $time, $eventId, (int)$existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id, status) VALUES (?,?,?,?,?,'present')");
            $ins->execute([$participantId, $attendanceDate, $time, $path, $eventId]);
        }

        echo json_encode(['ok' => true, 'message' => 'Attendance marked successfully']);
    }
}
