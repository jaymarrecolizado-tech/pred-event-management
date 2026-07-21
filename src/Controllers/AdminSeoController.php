<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;

class AdminSeoController
{
    public function dashboard(): void
    {
        if (!AuthService::check()) {
            header('Location: ?r=admin_login');
            return;
        }
        $pdo = Database::pdo();
        $selectedDate = trim((string)($_GET['date'] ?? ''));
        if ($selectedDate === '') {
            $selectedDate = date('Y-m-d');
        }
        $summary = $this->buildSummary($pdo, $selectedDate);
        extract($summary, EXTR_SKIP);
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_seo_dashboard.php';
    }

    public function summaryJson(): void
    {
        if (!AuthService::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'unauthorized']);
            return;
        }
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $pdo = Database::pdo();
        $selectedDate = trim((string)($_GET['date'] ?? ''));
        if ($selectedDate === '') {
            $selectedDate = date('Y-m-d');
        }
        echo json_encode($this->buildSummary($pdo, $selectedDate));
    }

    public function searchJson(): void
    {
        if (!AuthService::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'unauthorized']);
            return;
        }
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $q = trim((string)($_GET['q'] ?? ''));
        $scope = trim((string)($_GET['scope'] ?? 'all'));
        $selectedDate = trim((string)($_GET['date'] ?? ''));
        if ($selectedDate === '') {
            $selectedDate = date('Y-m-d');
        }
        if (strlen($q) < 2) {
            echo json_encode(['results' => [], 'q' => $q, 'scope' => $scope]);
            return;
        }

        $pdo = Database::pdo();
        $event = $pdo->query('SELECT id FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch() ?: null;
        $eventId = $event ? (int)$event['id'] : null;

        $joinOn = 'a.participant_id = p.id AND a.attendance_date = ?';
        $bind = [$selectedDate];
        if ($eventId !== null) {
            $joinOn .= ' AND (a.event_id = ? OR a.event_id IS NULL)';
            $bind[] = $eventId;
        }

        $like = '%' . $q . '%';
        $where = '(p.first_name LIKE ? OR p.last_name LIKE ? OR p.agency LIKE ? OR p.designation LIKE ?
                  OR CONCAT(p.first_name, \' \', p.last_name) LIKE ?
                  OR CONCAT(p.last_name, \' \', p.first_name) LIKE ?)';
        $bind = array_merge($bind, [$like, $like, $like, $like, $like, $like]);
        if ($scope === 'vip') {
            $where .= ' AND p.is_vip = 1';
        }

        $stmt = $pdo->prepare(
            "SELECT p.id, p.first_name, p.last_name, p.agency, p.sector, p.designation, p.is_vip,
                    a.id AS attendance_id, a.signature_path, a.status AS attendance_status, a.time_in
             FROM participants p
             LEFT JOIN attendance a ON {$joinOn}
             WHERE {$where}
             ORDER BY p.is_vip DESC, p.last_name ASC, p.first_name ASC
             LIMIT 40"
        );
        $stmt->execute($bind);
        $rows = $stmt->fetchAll() ?: [];

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => (int)$row['id'],
                'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
                'agency' => (string)($row['agency'] ?? ''),
                'sector' => (string)($row['sector'] ?? ''),
                'designation' => (string)($row['designation'] ?? ''),
                'is_vip' => (int)($row['is_vip'] ?? 0) === 1,
                'guest_status' => $this->resolveGuestStatus($row),
                'time_in' => $this->formatClockTime($row['time_in'] ?? null),
            ];
        }

        echo json_encode([
            'results' => $results,
            'q' => $q,
            'scope' => $scope === 'vip' ? 'vip' : 'all',
            'selectedDate' => $selectedDate,
            'count' => count($results),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSummary(\PDO $pdo, string $selectedDate): array
    {
        $event = $pdo->query('SELECT id, name, created_at FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch() ?: null;
        $eventId = $event ? (int)$event['id'] : null;

        $kpi = $this->computeKpis($pdo, $selectedDate, $eventId);
        $vips = $this->guestRows($pdo, $selectedDate, $eventId, true);
        $guests = $this->guestRows($pdo, $selectedDate, $eventId, false, 300);
        $guestTotal = $this->guestTotal($pdo, false);
        $agencyRollup = $this->agencyRollup($vips);
        $recentVip = array_values(array_filter($vips, static fn(array $r): bool => $r['guest_status'] === 'present' && !empty($r['time_in'])));
        usort($recentVip, static function (array $a, array $b): int {
            return strcmp((string)($b['time_in'] ?? ''), (string)($a['time_in'] ?? ''));
        });
        $recentVip = array_slice($recentVip, 0, 12);

        $attention = [];
        foreach ($vips as $vip) {
            if ($vip['guest_status'] === 'in_vicinity') {
                $attention[] = $vip;
            }
        }

        $vipCounts = $this->statusCounts($vips);
        $guestCounts = $this->statusCountsFromDb($pdo, $selectedDate, $eventId, false);
        // Keep listed total accurate when table is truncated.
        $guestCounts['listed'] = count($guests);
        $guestCounts['truncated'] = $guestTotal > count($guests);

        return [
            'selectedDate' => $selectedDate,
            'event' => $event ? [
                'id' => (int)$event['id'],
                'name' => (string)$event['name'],
            ] : null,
            'kpi' => $kpi,
            'vipCounts' => $vipCounts,
            'vips' => $vips,
            'guestCounts' => $guestCounts,
            'guests' => $guests,
            'agencyRollup' => $agencyRollup,
            'recentVip' => $recentVip,
            'attention' => array_slice($attention, 0, 20),
            'refreshedAt' => date('c'),
            'timestamp' => time(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function computeKpis(\PDO $pdo, string $selectedDate, ?int $eventId): array
    {
        $scopeSql = "COALESCE(a.signature_path, '') <> '' AND COALESCE(a.status, 'present') = 'present'";
        $scopeParams = [];
        if ($eventId !== null) {
            $scopeSql .= ' AND (a.event_id = ? OR a.event_id IS NULL)';
            $scopeParams[] = $eventId;
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT a.participant_id) FROM attendance a WHERE a.attendance_date = ? AND {$scopeSql}"
        );
        $stmt->execute(array_merge([$selectedDate], $scopeParams));
        $dateCount = (int)$stmt->fetchColumn();

        $totalRegistered = (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();

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
        $peakHourText = $peakHour
            ? sprintf('%s–%s (%d sign-ins)', $this->formatHourLabel((int)$peakHour['hour']), $this->formatHourLabel(((int)$peakHour['hour'] + 1) % 24), (int)$peakHour['cnt'])
            : 'N/A';

        return [
            'dateCount' => $dateCount,
            'totalRegistered' => $totalRegistered,
            'attendanceRate' => $attendanceRate,
            'recentCount' => $recentCount,
            'peakHourText' => $peakHourText,
            'vicinityCount' => $vicinityCount,
            'absentCount' => $absentCount,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function guestRows(\PDO $pdo, string $selectedDate, ?int $eventId, bool $vipOnly, ?int $limit = null): array
    {
        $joinOn = 'a.participant_id = p.id AND a.attendance_date = ?';
        $bind = [$selectedDate];
        if ($eventId !== null) {
            $joinOn .= ' AND (a.event_id = ? OR a.event_id IS NULL)';
            $bind[] = $eventId;
        }

        $vipSql = $vipOnly ? 'p.is_vip = 1' : 'p.is_vip = 0';
        $limitSql = $limit !== null ? ' LIMIT ' . max(1, min(500, $limit)) : '';

        $stmt = $pdo->prepare(
            "SELECT p.id, p.first_name, p.last_name, p.agency, p.sector, p.designation, p.is_vip,
                    a.id AS attendance_id, a.signature_path, a.status AS attendance_status, a.time_in
             FROM participants p
             LEFT JOIN attendance a ON {$joinOn}
             WHERE {$vipSql}
             ORDER BY
                CASE
                    WHEN a.status = 'absent' THEN 3
                    WHEN a.id IS NOT NULL AND COALESCE(a.signature_path, '') <> '' THEN 1
                    ELSE 2
                END ASC,
                p.last_name ASC,
                p.first_name ASC
             {$limitSql}"
        );
        $stmt->execute($bind);
        $rows = $stmt->fetchAll() ?: [];

        $out = [];
        foreach ($rows as $row) {
            $status = $this->resolveGuestStatus($row);
            $out[] = [
                'id' => (int)$row['id'],
                'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
                'agency' => (string)($row['agency'] ?? ''),
                'sector' => (string)($row['sector'] ?? ''),
                'designation' => (string)($row['designation'] ?? ''),
                'is_vip' => (int)($row['is_vip'] ?? 0) === 1,
                'guest_status' => $status,
                'time_in' => $status === 'present' ? $this->formatClockTime($row['time_in'] ?? null) : null,
            ];
        }
        return $out;
    }

    private function guestTotal(\PDO $pdo, bool $vipOnly): int
    {
        $sql = $vipOnly
            ? 'SELECT COUNT(*) FROM participants WHERE is_vip = 1'
            : 'SELECT COUNT(*) FROM participants WHERE is_vip = 0';
        return (int)$pdo->query($sql)->fetchColumn();
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{total:int,present:int,in_vicinity:int,absent:int}
     */
    private function statusCounts(array $rows): array
    {
        $counts = [
            'total' => count($rows),
            'present' => 0,
            'in_vicinity' => 0,
            'absent' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string)($row['guest_status'] ?? 'in_vicinity');
            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
        }
        return $counts;
    }

    /**
     * @return array{total:int,present:int,in_vicinity:int,absent:int}
     */
    private function statusCountsFromDb(\PDO $pdo, string $selectedDate, ?int $eventId, bool $vipOnly): array
    {
        $vipSql = $vipOnly ? 'p.is_vip = 1' : 'p.is_vip = 0';
        $total = $this->guestTotal($pdo, $vipOnly);

        $joinOn = 'a.participant_id = p.id AND a.attendance_date = ?';
        $bind = [$selectedDate];
        if ($eventId !== null) {
            $joinOn .= ' AND (a.event_id = ? OR a.event_id IS NULL)';
            $bind[] = $eventId;
        }

        $presentSql = "SELECT COUNT(DISTINCT p.id)
            FROM participants p
            INNER JOIN attendance a ON {$joinOn}
            WHERE {$vipSql}
              AND COALESCE(a.signature_path, '') <> ''
              AND COALESCE(a.status, 'present') = 'present'";
        $presentStmt = $pdo->prepare($presentSql);
        $presentStmt->execute($bind);
        $present = (int)$presentStmt->fetchColumn();

        $absentSql = "SELECT COUNT(DISTINCT p.id)
            FROM participants p
            INNER JOIN attendance a ON {$joinOn}
            WHERE {$vipSql} AND a.status = 'absent'";
        $absentStmt = $pdo->prepare($absentSql);
        $absentStmt->execute($bind);
        $absent = (int)$absentStmt->fetchColumn();

        return [
            'total' => $total,
            'present' => $present,
            'in_vicinity' => max(0, $total - $present - $absent),
            'absent' => $absent,
        ];
    }

    /**
     * Format stored TIME / datetime as a wall-clock check-in time (never relative).
     */
    private function formatClockTime(mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }
        $raw = trim((string)$time);
        if ($raw === '') {
            return null;
        }

        // Accept "H:i:s", "H:i", or full datetime strings from PDO/MySQL.
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
            $hour = (int)$m[1];
            $minute = (int)$m[2];
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                return $raw;
            }
            return date('g:i A', mktime($hour, $minute, 0));
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('g:i A', $ts);
    }

    private function formatHourLabel(int $hour): string
    {
        $hour = (($hour % 24) + 24) % 24;
        return date('g A', mktime($hour, 0, 0));
    }

    /**
     * @param list<array<string,mixed>> $vips
     * @return list<array<string,mixed>>
     */
    private function agencyRollup(array $vips): array
    {
        $map = [];
        foreach ($vips as $vip) {
            $agency = trim((string)($vip['agency'] ?? '')) ?: 'Unspecified';
            if (!isset($map[$agency])) {
                $map[$agency] = [
                    'agency' => $agency,
                    'present' => 0,
                    'in_vicinity' => 0,
                    'absent' => 0,
                    'total' => 0,
                ];
            }
            $status = (string)$vip['guest_status'];
            if (!isset($map[$agency][$status])) {
                $map[$agency][$status] = 0;
            }
            $map[$agency][$status]++;
            $map[$agency]['total']++;
        }
        $list = array_values($map);
        usort($list, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);
        return array_slice($list, 0, 15);
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
        // Match attendance KPI: signature present (id may be 0 — empty(0) is true in PHP).
        if (trim((string)($row['signature_path'] ?? '')) !== '') {
            return 'present';
        }
        return 'in_vicinity';
    }
}
