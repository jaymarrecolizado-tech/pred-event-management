<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\SignatureService;

class AttendanceController
{
    public function submit(): void
    {
        header('Content-Type: application/json');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ok = \App\Services\RateLimiter::allow('attendance_submit:'.$ip, 20, 60);
        if (!$ok) { http_response_code(429); echo json_encode(['error'=>'rate_limited']); return; }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('csrf_check') || !csrf_check($csrf)) { http_response_code(400); echo json_encode(['error'=>'csrf']); return; }
        $staffOk = !empty($_SESSION['staff']) || \App\Services\AuthService::hasRole(
            \App\Services\AuthService::ROLE_ADMIN,
            \App\Services\AuthService::ROLE_CHECKER
        );
        if (!$staffOk) { http_response_code(403); echo json_encode(['error'=>'forbidden']); return; }

        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);
        if (!is_array($payload)) { http_response_code(400); echo json_encode(['error'=>'invalid']); return; }

        $uuid = trim((string)($payload['uuid'] ?? ''));
        $sig = (string)($payload['signature'] ?? '');
        if ($uuid === '' || $sig === '') { http_response_code(422); echo json_encode(['error'=>'missing']); return; }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM participants WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'not_found']); return; }

        $event = $pdo->query('SELECT id, enforce_single_time_in FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch();
        $eventId = $event ? (int)$event['id'] : null;
        $enforce = $event ? (int)$event['enforce_single_time_in'] === 1 : false;

        try {
            $path = SignatureService::saveBase64($uuid, $sig);
        } catch (\Throwable $e) {
            error_log('attendance submit signature save failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'signature_save_failed']);
            return;
        }
        $date = date('Y-m-d');
        $time = date('H:i:s');
        if ($enforce) {
            $chk = $pdo->prepare('SELECT id FROM attendance WHERE participant_id=? AND attendance_date=?' . ($eventId ? ' AND event_id=?' : ''));
            $bind = $eventId ? [(int)$row['id'],$date,$eventId] : [(int)$row['id'],$date];
            $chk->execute($bind);
            if ($chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'already_marked']); return; }
        }
        $ins = $pdo->prepare("INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id, status) VALUES (?,?,?,?,?,'present')");
        $ins->execute([(int)$row['id'], $date, $time, $path, $eventId]);
        echo json_encode(['ok'=>true]);
    }

    public function submitJsonForTest(array $payload, string $csrf): array
    {
        if (!function_exists('csrf_check') || !csrf_check($csrf)) return ['error'=>'csrf'];
        $staffOk = !empty($_SESSION['staff']) || \App\Services\AuthService::hasRole(
            \App\Services\AuthService::ROLE_ADMIN,
            \App\Services\AuthService::ROLE_CHECKER
        );
        if (!$staffOk) return ['error'=>'forbidden'];
        $uuid = trim((string)($payload['uuid'] ?? ''));
        $sig = (string)($payload['signature'] ?? '');
        if ($uuid === '' || $sig === '') return ['error'=>'missing'];
        $pdo = \App\Services\Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM participants WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch();
        if (!$row) return ['error'=>'not_found'];
        $event = $pdo->query('SELECT id, enforce_single_time_in FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch();
        $eventId = $event ? (int)$event['id'] : null;
        $enforce = $event ? (int)$event['enforce_single_time_in'] === 1 : false;
        $path = \App\Services\SignatureService::saveBase64($uuid, $sig);
        $date = date('Y-m-d');
        $time = date('H:i:s');
        if ($enforce) {
            $chk = $pdo->prepare('SELECT id FROM attendance WHERE participant_id=? AND attendance_date=?' . ($eventId ? ' AND event_id=?' : ''));
            $bind = $eventId ? [(int)$row['id'],$date,$eventId] : [(int)$row['id'],$date];
            $chk->execute($bind);
            if ($chk->fetch()) return ['ok'=>false,'error'=>'already_marked'];
        }
        $ins = $pdo->prepare("INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id, status) VALUES (?,?,?,?,?,'present')");
        $ins->execute([(int)$row['id'], $date, $time, $path, $eventId]);
        return ['ok'=>true];
    }
}