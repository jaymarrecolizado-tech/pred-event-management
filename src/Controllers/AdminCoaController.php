<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\CertificateOfAppearanceService as Coa;
use App\Services\Database;
use App\Services\Logger;
use App\Services\Mailer;
use PDO;

class AdminCoaController
{
    private function requireAdmin(): bool
    {
        if (!AuthService::isAdmin()) {
            if (empty($_SESSION['admin_id'])) {
                header('Location: ?r=admin_login');
                return false;
            }
            AuthService::deny('GET');
            return false;
        }
        return true;
    }

    private function ensureTables(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'coa_signatories')
            || !$this->tableExists($pdo, 'coa_send_batches')
            || !$this->tableExists($pdo, 'coa_send_items')) {
            $sqlFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '009_coa.sql';
            if (is_file($sqlFile)) {
                $sql = (string)file_get_contents($sqlFile);
                foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $stmt) {
                    if ($stmt !== '') {
                        $pdo->exec($stmt);
                    }
                }
            }
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    public function signatories(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);
        $rows = $pdo->query('SELECT * FROM coa_signatories ORDER BY active DESC, full_name ASC')->fetchAll() ?: [];
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_coa_signatories.php';
    }

    public function saveSignatory(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'] ?? null)) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);

        $id = (int)($_POST['id'] ?? 0);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($fullName === '' || $title === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Name and title are required.'];
            header('Location: ?r=admin_coa_signatories');
            return;
        }

        $signaturePath = null;
        if (isset($_FILES['signature']) && is_uploaded_file($_FILES['signature']['tmp_name'])) {
            $type = mime_content_type($_FILES['signature']['tmp_name']) ?: '';
            if (!in_array($type, ['image/png', 'image/jpeg', 'image/jpg'], true)) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Signature must be PNG or JPG.'];
                header('Location: ?r=admin_coa_signatories');
                return;
            }
            $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'coa_signatories';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $ext = str_contains($type, 'png') ? 'png' : 'jpg';
            $dest = $dir . DIRECTORY_SEPARATOR . 'sig_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['signature']['tmp_name'], $dest)) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to save signature image.'];
                header('Location: ?r=admin_coa_signatories');
                return;
            }
            $signaturePath = $dest;
        }

        if ($id > 0) {
            if ($signaturePath) {
                $stmt = $pdo->prepare('UPDATE coa_signatories SET full_name=?, title=?, signature_path=?, active=? WHERE id=?');
                $stmt->execute([$fullName, $title, $signaturePath, $active, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE coa_signatories SET full_name=?, title=?, active=? WHERE id=?');
                $stmt->execute([$fullName, $title, $active, $id]);
            }
            Logger::log(AuthService::id(), 'coa_signatory_updated', ['id' => $id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Signatory updated.'];
        } else {
            if (!$signaturePath) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'E-signature image is required for new signatories.'];
                header('Location: ?r=admin_coa_signatories');
                return;
            }
            $stmt = $pdo->prepare('INSERT INTO coa_signatories (full_name, title, signature_path, active) VALUES (?,?,?,?)');
            $stmt->execute([$fullName, $title, $signaturePath, $active]);
            Logger::log(AuthService::id(), 'coa_signatory_created', ['id' => (int)$pdo->lastInsertId()]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Signatory added.'];
        }
        header('Location: ?r=admin_coa_signatories');
    }

    public function signatoryImage(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);
        $id = (int)($_GET['id'] ?? 0);
        $row = $this->fetchSignatory($pdo, $id);
        if (!$row || empty($row['signature_path']) || !is_file($row['signature_path'])) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $path = $row['signature_path'];
        $mime = mime_content_type($path) ?: 'image/png';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($path));
        readfile($path);
    }

    public function compose(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);

        $eventId = isset($_GET['event_id']) && $_GET['event_id'] !== '' ? (int)$_GET['event_id'] : null;
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));
        $load = isset($_GET['load']) && $_GET['load'] === '1';

        $events = $pdo->query('SELECT id, name, active FROM events ORDER BY active DESC, id DESC')->fetchAll() ?: [];
        $signatories = $pdo->query('SELECT id, full_name, title FROM coa_signatories WHERE active = 1 ORDER BY full_name ASC')->fetchAll() ?: [];
        $defaults = Coa::defaultParticulars();
        $recipients = [];
        if ($load) {
            $recipients = Coa::eligibleParticipants(
                $pdo,
                $eventId,
                $dateFrom !== '' ? $dateFrom : null,
                $dateTo !== '' ? $dateTo : null
            );
        }

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $lastResult = $_SESSION['coa_last_result'] ?? null;
        unset($_SESSION['coa_last_result']);
        $monitorSummary = $this->monitorSummary($pdo);

        $venue = getenv('COA_DEFAULT_VENUE') ?: 'DICT Regional Office 2';
        $purpose = getenv('COA_DEFAULT_PURPOSE') ?: 'coordinating matters related to the programs and projects of DICT Region 02';
        $inclusiveDates = '';
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom === $dateTo) {
            $inclusiveDates = date('F j, Y', strtotime($dateFrom)) ?: $dateFrom;
        } elseif ($dateFrom !== '' && $dateTo !== '') {
            $inclusiveDates = (date('F j', strtotime($dateFrom)) ?: $dateFrom) . '-' . (date('j, Y', strtotime($dateTo)) ?: $dateTo);
        }
        $issueDate = date('Y-m-d');

        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_coa.php';
    }

    public function preview(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'] ?? null)) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);

        $participantId = (int)($_POST['participant_id'] ?? 0);
        $signatoryId = (int)($_POST['signatory_id'] ?? 0);
        if ($participantId <= 0 || $signatoryId <= 0) {
            http_response_code(400);
            echo 'Missing participant or signatory';
            return;
        }

        $participant = $this->fetchParticipant($pdo, $participantId);
        $signatory = $this->fetchSignatory($pdo, $signatoryId);
        if (!$participant || !$signatory) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $activity = $this->activityFromPost();
        $defaults = Coa::normalizeParticulars($this->particularsFromPost('default'));
        $override = $this->overrideFromPost($participantId);
        $particulars = Coa::mergeParticulars($defaults, $override);

        try {
            $path = Coa::renderPdf($participant, $activity, $particulars, $signatory);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'PDF failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
    }

    public function send(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'] ?? null)) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);

        $signatoryId = (int)($_POST['signatory_id'] ?? 0);
        $signatory = $this->fetchSignatory($pdo, $signatoryId);
        if (!$signatory) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Select an active signatory with an e-signature.'];
            header('Location: ?r=admin_coa');
            return;
        }
        if (empty($signatory['signature_path']) || !is_file($signatory['signature_path'])) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Signatory e-signature file is missing.'];
            header('Location: ?r=admin_coa');
            return;
        }

        $activity = $this->activityFromPost();
        if ($activity['venue'] === '' || $activity['purpose'] === '' || $activity['inclusive_dates'] === '' || $activity['issue_date'] === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Venue, purpose, inclusive dates, and issue date are required.'];
            header('Location: ?r=admin_coa');
            return;
        }

        $eventId = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
        $dateFrom = trim((string)($_POST['date_from'] ?? ''));
        $dateTo = trim((string)($_POST['date_to'] ?? ''));
        $defaults = Coa::normalizeParticulars($this->particularsFromPost('default'));

        $selected = array_map('intval', (array)($_POST['recipient_ids'] ?? []));
        $skipIds = array_map('intval', (array)($_POST['skip_ids'] ?? []));
        if (!$selected) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'No recipients selected. Load eligible guests first.'];
            header('Location: ?r=admin_coa');
            return;
        }

        $eligible = Coa::eligibleParticipants(
            $pdo,
            $eventId,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null
        );
        $byId = [];
        foreach ($eligible as $row) {
            $byId[(int)$row['id']] = $row;
        }

        $batchStmt = $pdo->prepare(
            'INSERT INTO coa_send_batches
             (event_id, date_from, date_to, venue, purpose, inclusive_dates, issue_date, default_particulars, signatory_id, admin_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $batchStmt->execute([
            $eventId,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
            $activity['venue'],
            $activity['purpose'],
            $activity['inclusive_dates'],
            $activity['issue_date'],
            json_encode($defaults),
            $signatoryId,
            AuthService::id(),
        ]);
        $batchId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO coa_send_items
             (batch_id, participant_id, attendance_summary, particulars, pdf_path, email_to, status, error, sent_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $failures = [];
        $chunk = 0;

        foreach ($selected as $pid) {
            if (!isset($byId[$pid])) {
                continue;
            }
            $participant = $byId[$pid];
            $override = $this->overrideFromPost($pid);
            $particulars = Coa::mergeParticulars($defaults, $override);
            $summary = (string)($participant['attendance_dates'] ?? '');
            $email = Coa::resolveEmail($participant);

            if (in_array($pid, $skipIds, true)) {
                $itemStmt->execute([$batchId, $pid, $summary, json_encode($particulars), null, $email, 'skipped', 'Manually skipped', null]);
                $skipped++;
                continue;
            }
            if ($email === null) {
                $itemStmt->execute([$batchId, $pid, $summary, json_encode($particulars), null, null, 'skipped', 'No valid email', null]);
                $skipped++;
                $failures[] = Coa::fullName($participant) . ' — no email';
                continue;
            }

            try {
                $pdfPath = Coa::renderPdf($participant, $activity, $particulars, $signatory);
                $ok = Mailer::send(
                    $email,
                    Coa::emailSubject($activity['inclusive_dates']),
                    Coa::emailBody(Coa::fullName($participant), $activity['inclusive_dates']),
                    $pdfPath
                );
                if ($ok) {
                    $itemStmt->execute([$batchId, $pid, $summary, json_encode($particulars), $pdfPath, $email, 'sent', null, date('Y-m-d H:i:s')]);
                    $sent++;
                } else {
                    $itemStmt->execute([$batchId, $pid, $summary, json_encode($particulars), $pdfPath, $email, 'failed', 'Mailer returned false', null]);
                    $failed++;
                    $failures[] = Coa::fullName($participant) . ' — mail failed';
                }
            } catch (\Throwable $e) {
                $itemStmt->execute([$batchId, $pid, $summary, json_encode($particulars), null, $email, 'failed', $e->getMessage(), null]);
                $failed++;
                $failures[] = Coa::fullName($participant) . ' — ' . $e->getMessage();
            }

            $chunk++;
            if ($chunk % 20 === 0) {
                usleep(100000);
            }
        }

        $pdo->prepare('UPDATE coa_send_batches SET sent_count=?, failed_count=?, skipped_count=? WHERE id=?')
            ->execute([$sent, $failed, $skipped, $batchId]);

        Logger::log(AuthService::id(), 'coa_bulk_send', [
            'batch_id' => $batchId,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        $_SESSION['coa_last_result'] = [
            'batch_id' => $batchId,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'failures' => $failures,
        ];
        $_SESSION['flash'] = [
            'type' => $failed > 0 ? 'warning' : 'success',
            'message' => "CoA send complete: {$sent} sent, {$failed} failed, {$skipped} skipped.",
        ];
        header('Location: ?r=admin_coa_monitor&batch_id=' . $batchId);
    }

    public function monitor(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);

        $summary = $this->monitorSummary($pdo);
        $batchId = isset($_GET['batch_id']) && $_GET['batch_id'] !== '' ? (int)$_GET['batch_id'] : null;
        $statusFilter = trim((string)($_GET['status'] ?? ''));
        if (!in_array($statusFilter, ['sent', 'failed', 'pending', 'skipped'], true)) {
            $statusFilter = '';
        }

        $batches = $pdo->query(
            "SELECT b.*,
                    s.full_name AS signatory_name,
                    (SELECT COUNT(*) FROM coa_send_items i WHERE i.batch_id = b.id AND i.status = 'pending') AS queued_count
             FROM coa_send_batches b
             LEFT JOIN coa_signatories s ON s.id = b.signatory_id
             ORDER BY b.id DESC
             LIMIT 50"
        )->fetchAll() ?: [];

        $items = [];
        $selectedBatch = null;
        if ($batchId) {
            foreach ($batches as $b) {
                if ((int)$b['id'] === $batchId) {
                    $selectedBatch = $b;
                    break;
                }
            }
            if (!$selectedBatch) {
                $stmt = $pdo->prepare(
                    "SELECT b.*, s.full_name AS signatory_name,
                            (SELECT COUNT(*) FROM coa_send_items i WHERE i.batch_id = b.id AND i.status = 'pending') AS queued_count
                     FROM coa_send_batches b
                     LEFT JOIN coa_signatories s ON s.id = b.signatory_id
                     WHERE b.id = ?"
                );
                $stmt->execute([$batchId]);
                $selectedBatch = $stmt->fetch() ?: null;
            }
            if ($selectedBatch) {
                $sql = "SELECT i.*, p.first_name, p.middle_name, p.last_name, p.agency, p.email, p.office_email
                        FROM coa_send_items i
                        JOIN participants p ON p.id = i.participant_id
                        WHERE i.batch_id = ?";
                $bind = [$batchId];
                if ($statusFilter !== '') {
                    $sql .= ' AND i.status = ?';
                    $bind[] = $statusFilter;
                }
                $sql .= " ORDER BY FIELD(i.status, 'pending', 'failed', 'sent', 'skipped'), i.id ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bind);
                $items = $stmt->fetchAll() ?: [];
            }
        } elseif ($statusFilter !== '') {
            $sql = "SELECT i.*, p.first_name, p.middle_name, p.last_name, p.agency, p.email, p.office_email
                    FROM coa_send_items i
                    JOIN participants p ON p.id = i.participant_id
                    WHERE i.status = ?
                    ORDER BY i.id DESC
                    LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$statusFilter]);
            $items = $stmt->fetchAll() ?: [];
        }

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_coa_monitor.php';
    }

    public function queueFailed(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'] ?? null)) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);

        $batchId = isset($_POST['batch_id']) && $_POST['batch_id'] !== '' ? (int)$_POST['batch_id'] : null;
        $itemId = (int)($_POST['item_id'] ?? 0);

        if ($itemId > 0) {
            $stmt = $pdo->prepare("UPDATE coa_send_items SET status = 'pending', error = CONCAT('Queued for resend: ', COALESCE(error, '')) WHERE id = ? AND status = 'failed'");
            $stmt->execute([$itemId]);
            $n = $stmt->rowCount();
            $bStmt = $pdo->prepare('SELECT batch_id FROM coa_send_items WHERE id = ?');
            $bStmt->execute([$itemId]);
            $batchId = (int)($bStmt->fetchColumn() ?: 0);
            if ($batchId > 0) {
                $this->refreshBatchCounts($pdo, $batchId);
            }
            Logger::log(AuthService::id(), 'coa_queue_item', ['item_id' => $itemId, 'queued' => $n]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => $n ? 'Item queued for resend.' : 'Item was not failed (nothing queued).'];
            header('Location: ?r=admin_coa_monitor' . ($batchId ? '&batch_id=' . $batchId : '') . '&status=pending');
            return;
        }

        if ($batchId) {
            $stmt = $pdo->prepare("UPDATE coa_send_items SET status = 'pending', error = CONCAT('Queued for resend: ', COALESCE(error, '')) WHERE batch_id = ? AND status = 'failed'");
            $stmt->execute([$batchId]);
            $n = $stmt->rowCount();
            $this->refreshBatchCounts($pdo, $batchId);
            Logger::log(AuthService::id(), 'coa_queue_failed', ['batch_id' => $batchId, 'queued' => $n]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Queued {$n} failed certificate(s) for resend."];
            header('Location: ?r=admin_coa_monitor&batch_id=' . $batchId . '&status=pending');
            return;
        }

        $stmt = $pdo->prepare("UPDATE coa_send_items SET status = 'pending', error = CONCAT('Queued for resend: ', COALESCE(error, '')) WHERE status = 'failed'");
        $stmt->execute();
        $n = $stmt->rowCount();
        $batchIds = $pdo->query("SELECT DISTINCT batch_id FROM coa_send_items WHERE status = 'pending'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($batchIds as $bid) {
            $this->refreshBatchCounts($pdo, (int)$bid);
        }
        Logger::log(AuthService::id(), 'coa_queue_failed_all', ['queued' => $n]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Queued {$n} failed certificate(s) for resend."];
        header('Location: ?r=admin_coa_monitor&status=pending');
    }

    public function resendQueued(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!isset($_POST['csrf']) || !csrf_check($_POST['csrf'] ?? null)) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }
        $pdo = Database::pdo();
        $this->ensureTables($pdo);

        $batchId = isset($_POST['batch_id']) && $_POST['batch_id'] !== '' ? (int)$_POST['batch_id'] : null;
        $limit = max(1, min(100, (int)($_POST['limit'] ?? 50)));

        $sql = "SELECT i.*, b.venue, b.purpose, b.inclusive_dates, b.issue_date, b.signatory_id
                FROM coa_send_items i
                JOIN coa_send_batches b ON b.id = i.batch_id
                WHERE i.status = 'pending'";
        $bind = [];
        if ($batchId) {
            $sql .= ' AND i.batch_id = ?';
            $bind[] = $batchId;
        }
        $sql .= ' ORDER BY i.id ASC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll() ?: [];

        $sent = 0;
        $failed = 0;
        $touchedBatches = [];

        foreach ($rows as $item) {
            $result = $this->resendItemRow($pdo, $item);
            $bid = (int)$item['batch_id'];
            $touchedBatches[$bid] = true;
            if ($result === 'sent') {
                $sent++;
            } else {
                $failed++;
            }
        }

        foreach (array_keys($touchedBatches) as $bid) {
            $this->refreshBatchCounts($pdo, (int)$bid);
        }

        Logger::log(AuthService::id(), 'coa_resend_queued', [
            'batch_id' => $batchId,
            'sent' => $sent,
            'failed' => $failed,
            'attempted' => count($rows),
        ]);

        $_SESSION['flash'] = [
            'type' => $failed > 0 ? 'warning' : 'success',
            'message' => count($rows) === 0
                ? 'No queued certificates to resend.'
                : "Resend finished: {$sent} sent, {$failed} failed (of " . count($rows) . ' queued).',
        ];
        $redir = '?r=admin_coa_monitor';
        if ($batchId) {
            $redir .= '&batch_id=' . $batchId;
        }
        header('Location: ' . $redir);
    }

    /**
     * @return array{sent:int,failed:int,pending:int,skipped:int,batches:int}
     */
    private function monitorSummary(PDO $pdo): array
    {
        $row = $pdo->query(
            "SELECT
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped
             FROM coa_send_items"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $batches = (int)$pdo->query('SELECT COUNT(*) FROM coa_send_batches')->fetchColumn();
        return [
            'sent' => (int)($row['sent'] ?? 0),
            'failed' => (int)($row['failed'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'skipped' => (int)($row['skipped'] ?? 0),
            'batches' => $batches,
        ];
    }

    private function refreshBatchCounts(PDO $pdo, int $batchId): void
    {
        $stmt = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped_count
             FROM coa_send_items WHERE batch_id = ?"
        );
        $stmt->execute([$batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $pdo->prepare('UPDATE coa_send_batches SET sent_count=?, failed_count=?, skipped_count=? WHERE id=?')
            ->execute([
                (int)($row['sent_count'] ?? 0),
                (int)($row['failed_count'] ?? 0),
                (int)($row['skipped_count'] ?? 0),
                $batchId,
            ]);
    }

    /**
     * @param array<string,mixed> $item joined item+batch fields
     */
    private function resendItemRow(PDO $pdo, array $item): string
    {
        $itemId = (int)$item['id'];
        $participant = $this->fetchParticipant($pdo, (int)$item['participant_id']);
        $signatory = $this->fetchSignatory($pdo, (int)$item['signatory_id']);
        $upd = $pdo->prepare('UPDATE coa_send_items SET status=?, pdf_path=?, email_to=?, error=?, sent_at=? WHERE id=?');

        if (!$participant) {
            $upd->execute(['failed', $item['pdf_path'] ?? null, $item['email_to'] ?? null, 'Participant missing', null, $itemId]);
            return 'failed';
        }
        if (!$signatory || empty($signatory['signature_path']) || !is_file((string)$signatory['signature_path'])) {
            $upd->execute(['failed', $item['pdf_path'] ?? null, $item['email_to'] ?? null, 'Signatory or e-signature missing', null, $itemId]);
            return 'failed';
        }

        $email = Coa::resolveEmail($participant) ?: trim((string)($item['email_to'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $upd->execute(['failed', $item['pdf_path'] ?? null, null, 'No valid email', null, $itemId]);
            return 'failed';
        }

        $activity = [
            'venue' => (string)$item['venue'],
            'purpose' => (string)$item['purpose'],
            'inclusive_dates' => (string)$item['inclusive_dates'],
            'issue_date' => (string)$item['issue_date'],
        ];
        $particulars = json_decode((string)($item['particulars'] ?? '{}'), true);
        if (!is_array($particulars)) {
            $particulars = Coa::defaultParticulars();
        }

        try {
            $pdfPath = (string)($item['pdf_path'] ?? '');
            if ($pdfPath === '' || !is_file($pdfPath)) {
                $pdfPath = Coa::renderPdf($participant, $activity, $particulars, $signatory);
            }
            $ok = Mailer::send(
                $email,
                Coa::emailSubject($activity['inclusive_dates']),
                Coa::emailBody(Coa::fullName($participant), $activity['inclusive_dates']),
                $pdfPath
            );
            if ($ok) {
                $upd->execute(['sent', $pdfPath, $email, null, date('Y-m-d H:i:s'), $itemId]);
                return 'sent';
            }
            $upd->execute(['failed', $pdfPath, $email, 'Mailer returned false on resend', null, $itemId]);
            return 'failed';
        } catch (\Throwable $e) {
            $upd->execute(['failed', $item['pdf_path'] ?? null, $email, $e->getMessage(), null, $itemId]);
            return 'failed';
        }
    }

    private function activityFromPost(): array
    {
        return [
            'venue' => trim((string)($_POST['venue'] ?? '')),
            'purpose' => trim((string)($_POST['purpose'] ?? '')),
            'inclusive_dates' => trim((string)($_POST['inclusive_dates'] ?? '')),
            'issue_date' => trim((string)($_POST['issue_date'] ?? date('Y-m-d'))),
        ];
    }

    private function particularsFromPost(string $prefix): array
    {
        $lodging = (string)($_POST[$prefix . '_lodging'] ?? 'not_provided');
        $vehicle = (string)($_POST[$prefix . '_vehicle'] ?? 'not_provided');
        return [
            'lodging' => $lodging,
            'meals' => [
                'breakfast' => !empty($_POST[$prefix . '_meal_breakfast']),
                'lunch' => !empty($_POST[$prefix . '_meal_lunch']),
                'dinner' => !empty($_POST[$prefix . '_meal_dinner']),
            ],
            'vehicle' => $vehicle,
        ];
    }

    private function overrideFromPost(int $participantId): ?array
    {
        $key = 'override_' . $participantId;
        if (empty($_POST[$key . '_enabled'])) {
            return null;
        }
        return [
            'lodging' => (string)($_POST[$key . '_lodging'] ?? 'not_provided'),
            'meals' => [
                'breakfast' => !empty($_POST[$key . '_meal_breakfast']),
                'lunch' => !empty($_POST[$key . '_meal_lunch']),
                'dinner' => !empty($_POST[$key . '_meal_dinner']),
            ],
            'vehicle' => (string)($_POST[$key . '_vehicle'] ?? 'not_provided'),
        ];
    }

    private function fetchParticipant(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, uuid, first_name, middle_name, last_name, agency, designation, email, office_email FROM participants WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchSignatory(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, full_name, title, signature_path, active FROM coa_signatories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
