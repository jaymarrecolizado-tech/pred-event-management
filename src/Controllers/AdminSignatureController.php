<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\SignatureService;
use App\Services\RateLimiter;
use App\Services\Logger;

class AdminSignatureController
{
    private function requireAdmin(): bool
    {
        if (empty($_SESSION['admin_id'])) { http_response_code(403); echo json_encode(['error'=>'forbidden']); return false; }
        return true;
    }

    public function replace(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin()) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::allow('sig_replace:' . $ip, 10, 60)) { http_response_code(429); echo json_encode(['error'=>'rate_limited']); return; }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('csrf_check') || !csrf_check($csrf)) { http_response_code(400); echo json_encode(['error'=>'csrf']); return; }
        $payload = json_decode(file_get_contents('php://input'), true);
        $aid = (int)($payload['aid'] ?? 0);
        $sig = (string)($payload['signature'] ?? '');
        if ($aid <= 0 || $sig === '') { http_response_code(422); echo json_encode(['error'=>'missing']); return; }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT a.id,a.signature_path,p.uuid FROM attendance a JOIN participants p ON p.id=a.participant_id WHERE a.id=?');
        $stmt->execute([$aid]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'not_found']); return; }
        $path = SignatureService::saveBase64((string)$row['uuid'], $sig);
        $up = $pdo->prepare('UPDATE attendance SET signature_path=? WHERE id=?');
        $up->execute([$path, $aid]);
        Logger::log($_SESSION['admin_id'] ?? null, 'signature_replace', ['aid'=>$aid,'uuid'=>$row['uuid'],'ip'=>$ip]);
        echo json_encode(['ok'=>true]);
    }

    public function addNew(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin()) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::allow('sig_new:' . $ip, 10, 60)) { http_response_code(429); echo json_encode(['error'=>'rate_limited']); return; }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('csrf_check') || !csrf_check($csrf)) { http_response_code(400); echo json_encode(['error'=>'csrf']); return; }
        $payload = json_decode(file_get_contents('php://input'), true);
        $uuid = trim((string)($payload['uuid'] ?? ''));
        $date = trim((string)($payload['date'] ?? date('Y-m-d')));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { http_response_code(422); echo json_encode(['error'=>'invalid_date']); return; }
        $sig = (string)($payload['signature'] ?? '');
        if ($uuid === '' || $sig === '') { http_response_code(422); echo json_encode(['error'=>'missing']); return; }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM participants WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $p = $stmt->fetch();
        if (!$p) { http_response_code(404); echo json_encode(['error'=>'not_found']); return; }
        $event = $pdo->query('SELECT id, enforce_single_time_in FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch();
        $eventId = $event ? (int)$event['id'] : null;
        $enforce = $event ? (int)$event['enforce_single_time_in'] === 1 : false;
        if ($enforce) {
            $chk = $pdo->prepare('SELECT id FROM attendance WHERE participant_id=? AND attendance_date=?' . ($eventId ? ' AND event_id=?' : ''));
            $bind = $eventId ? [(int)$p['id'],$date,$eventId] : [(int)$p['id'],$date];
            $chk->execute($bind);
            if ($chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'already_marked']); return; }
        }
        $path = SignatureService::saveBase64($uuid, $sig);
        $ins = $pdo->prepare("INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id, status) VALUES (?,?,?,?,?,'present')");
        $ins->execute([(int)$p['id'], $date, date('H:i:s'), $path, $eventId]);
        $aid = (int)$pdo->lastInsertId();
        Logger::log($_SESSION['admin_id'] ?? null, 'signature_new', ['aid'=>$aid,'uuid'=>$uuid,'date'=>$date,'ip'=>$ip]);
        echo json_encode(['ok'=>true]);
    }

    public function replaceJsonForTest(array $payload, string $csrf): array
    {
        if (empty($_SESSION['admin_id'])) return ['error'=>'forbidden'];
        if (!function_exists('csrf_check') || !csrf_check($csrf)) return ['error'=>'csrf'];
        $aid = (int)($payload['aid'] ?? 0);
        $sig = (string)($payload['signature'] ?? '');
        if ($aid <= 0 || $sig === '') return ['error'=>'missing'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT a.id,a.signature_path,p.uuid FROM attendance a JOIN participants p ON p.id=a.participant_id WHERE a.id=?');
        $stmt->execute([$aid]);
        $row = $stmt->fetch();
        if (!$row) return ['error'=>'not_found'];
        $path = SignatureService::saveBase64((string)$row['uuid'], $sig);
        $up = $pdo->prepare('UPDATE attendance SET signature_path=? WHERE id=?');
        $up->execute([$path, $aid]);
        return ['ok'=>true];
    }

    public function addNewJsonForTest(array $payload, string $csrf): array
    {
        if (empty($_SESSION['admin_id'])) return ['error'=>'forbidden'];
        if (!function_exists('csrf_check') || !csrf_check($csrf)) return ['error'=>'csrf'];
        $uuid = trim((string)($payload['uuid'] ?? ''));
        $date = trim((string)($payload['date'] ?? date('Y-m-d')));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return ['error'=>'invalid_date'];
        $sig = (string)($payload['signature'] ?? '');
        if ($uuid === '' || $sig === '') return ['error'=>'missing'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM participants WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $p = $stmt->fetch();
        if (!$p) return ['error'=>'not_found'];
        $event = $pdo->query('SELECT id, enforce_single_time_in FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch();
        $eventId = $event ? (int)$event['id'] : null;
        $enforce = $event ? (int)$event['enforce_single_time_in'] === 1 : false;
        if ($enforce) {
            $chk = $pdo->prepare('SELECT id FROM attendance WHERE participant_id=? AND attendance_date=?' . ($eventId ? ' AND event_id=?' : ''));
            $bind = $eventId ? [(int)$p['id'],$date,$eventId] : [(int)$p['id'],$date];
            $chk->execute($bind);
            if ($chk->fetch()) return ['ok'=>false,'error'=>'already_marked'];
        }
        $path = SignatureService::saveBase64($uuid, $sig);
        $ins = $pdo->prepare("INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id, status) VALUES (?,?,?,?,?,'present')");
        $ins->execute([(int)$p['id'], $date, date('H:i:s'), $path, $eventId]);
        return ['ok'=>true];
    }
}