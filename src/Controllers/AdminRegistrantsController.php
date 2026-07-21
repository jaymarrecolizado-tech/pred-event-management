<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\Logger;
use App\Services\QrService;
use App\Services\Mailer;

class AdminRegistrantsController
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
        $q = trim((string)($_GET['q'] ?? ''));
        $agency = trim((string)($_GET['agency'] ?? ''));
        $sector = trim((string)($_GET['sector'] ?? ''));
        $vipOnly = isset($_GET['vip']) && (string)$_GET['vip'] === '1';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $offset = ($page - 1) * $per;

        $where = [];
        $bind = [];
        if ($q !== '') { $where[] = '(first_name LIKE ? OR last_name LIKE ?)'; $bind[] = "%{$q}%"; $bind[] = "%{$q}%"; }
        if ($agency !== '') { $where[] = 'agency LIKE ?'; $bind[] = "%{$agency}%"; }
        if ($sector !== '') { $where[] = 'sector LIKE ?'; $bind[] = "%{$sector}%"; }
        if ($vipOnly) { $where[] = 'is_vip = 1'; }
        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS id, uuid, first_name, last_name, agency, sector, email, office_email, is_vip FROM participants $sqlWhere ORDER BY id DESC LIMIT $per OFFSET $offset");
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT FOUND_ROWS() AS t')->fetch()['t'];
        $pages = max(1, (int)ceil($total / $per));

        $agenciesList = $pdo->query("SELECT DISTINCT agency FROM participants WHERE agency IS NOT NULL AND agency <> '' ORDER BY agency ASC LIMIT 500")->fetchAll();
        $sectorsList = $pdo->query("SELECT DISTINCT sector FROM participants WHERE sector IS NOT NULL AND sector <> '' ORDER BY sector ASC LIMIT 500")->fetchAll();
        $vipCount = (int)$pdo->query('SELECT COUNT(*) FROM participants WHERE is_vip = 1')->fetchColumn();
        $canManageVip = AuthService::isAdmin();
        $canViewVip = AuthService::hasRole(AuthService::ROLE_ADMIN, AuthService::ROLE_CHECKER);
        $data = compact('rows','page','pages','q','agency','sector','total','agenciesList','sectorsList','canManageVip','canViewVip','vipOnly','vipCount');
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_registrants.php';
    }

    public function toggleVip(): void
    {
        if (!AuthService::isAdmin()) {
            AuthService::deny('POST');
            return;
        }
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $id = (int)($_POST['participant_id'] ?? 0);
        $isVip = isset($_POST['is_vip']) && (string)$_POST['is_vip'] === '1' ? 1 : 0;
        if ($id <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Missing participant'];
            header('Location: ?r=admin_registrants');
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE participants SET is_vip = ? WHERE id = ?');
        $stmt->execute([$isVip, $id]);
        Logger::log(AuthService::id(), 'participant_vip_toggled', [
            'participant_id' => $id,
            'is_vip' => $isVip,
            'role' => AuthService::role(),
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $isVip ? 'Marked as VIP.' : 'VIP flag cleared.'];
        $q = http_build_query(array_filter([
            'r' => 'admin_registrants',
            'q' => $_POST['q'] ?? null,
            'agency' => $_POST['agency'] ?? null,
            'sector' => $_POST['sector'] ?? null,
            'page' => $_POST['page'] ?? null,
        ], static fn($v) => $v !== null && $v !== ''));
        header('Location: ?' . $q);
    }

    public function generateQrBatch(): void
    {
        if (!$this->requireAdmin()) return;
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $batchSize = max(1, min(200, (int)($_POST['batch'] ?? 50)));
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT id, uuid FROM participants WHERE qr_path IS NULL LIMIT ' . $batchSize)->fetchAll();
        if (!$rows) {
            $_SESSION['flash'] = ['type'=>'info','message'=>'No pending QR codes found.'];
            header('Location: ?r=admin_registrants');
            return;
        }
        $updated = 0;
        $failed = 0;
        $stmt = $pdo->prepare('UPDATE participants SET qr_path=? WHERE id=?');
        foreach ($rows as $row) {
            try {
                $path = QrService::generate('PART|' . $row['uuid'], $row['uuid']);
                $stmt->execute([$path, (int)$row['id']]);
                $updated++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }
        $msg = "Generated {$updated} QR(s)";
        if ($failed) $msg .= ", {$failed} failed";
        $_SESSION['flash'] = ['type'=>$failed ? 'warning' : 'success','message'=>$msg];
        header('Location: ?r=admin_registrants');
    }

    public function sendQrEmail(): void
    {
        if (!$this->requireAdmin()) return;
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $id = (int)($_POST['participant_id'] ?? 0);
        $customMessage = trim((string)($_POST['message'] ?? ''));
        if ($id <= 0) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>'Missing participant'];
            header('Location: ?r=admin_registrants');
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, uuid, first_name, last_name, email, office_email, qr_path FROM participants WHERE id = ?');
        $stmt->execute([$id]);
        $participant = $stmt->fetch();
        if (!$participant) {
            $_SESSION['flash'] = ['type'=>'danger','message'=>'Participant not found'];
            header('Location: ?r=admin_registrants');
            return;
        }
        $to = $participant['email'] ?: $participant['office_email'];
        if (!$to) {
            $_SESSION['flash'] = ['type'=>'warning','message'=>'Participant has no email address on record'];
            header('Location: ?r=admin_registrants');
            return;
        }
        $path = $this->ensureQrPath($pdo, $participant);
        $message = $customMessage !== '' ? $customMessage : 'Thank you for joining and registering for our event. Please keep this QR code handy for fast onsite check-in.';
        $body = '<p>Hi ' . htmlspecialchars($participant['first_name'] ?? '', ENT_QUOTES) . ',</p>'
            . '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES)) . '</p>'
            . '<p>Your QR code is attached for convenience.</p>';
        $sent = Mailer::send($to, 'Your event QR code', $body, $path);
        $_SESSION['flash'] = ['type' => $sent ? 'success' : 'danger', 'message' => $sent ? 'Email sent successfully.' : 'Failed to send email.'];
        header('Location: ?r=admin_registrants');
    }

    public function qrPreview(): void
    {
        if (!$this->requireAdmin()) return;
        $uuid = isset($_GET['uuid']) ? (string)$_GET['uuid'] : '';
        if ($uuid === '') {
            http_response_code(400);
            echo 'Missing UUID';
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, uuid, qr_path FROM participants WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $participant = $stmt->fetch();
        if (!$participant) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $path = $this->ensureQrPath($pdo, $participant);
        if (!is_file($path)) {
            http_response_code(404);
            echo 'QR not found';
            return;
        }
        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        readfile($path);
    }

    private function ensureQrPath(\PDO $pdo, array $participant): string
    {
        $path = $participant['qr_path'];
        if ($path && is_file($path)) {
            return $path;
        }
        $path = QrService::generate('PART|' . $participant['uuid'], $participant['uuid']);
        $stmt = $pdo->prepare('UPDATE participants SET qr_path=? WHERE id=?');
        $stmt->execute([$path, (int)$participant['id']]);
        return $path;
    }
}