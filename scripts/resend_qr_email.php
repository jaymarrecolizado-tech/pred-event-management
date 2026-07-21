<?php
declare(strict_types=1);

/**
 * Resend QR email for a participant by email address.
 * Run: C:\xampp\php\php.exe scripts/resend_qr_email.php maricar.pecson@dict.gov.ph
 */

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/QrService.php';
require __DIR__ . '/../src/Services/Mailer.php';

$toFind = $argv[1] ?? '';
if ($toFind === '') {
    fwrite(STDERR, "Usage: php scripts/resend_qr_email.php email@example.com\n");
    exit(1);
}

$pdo = \App\Services\Database::pdo();
$stmt = $pdo->prepare('SELECT id, uuid, first_name, last_name, email, qr_path FROM participants WHERE email = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$toFind]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    fwrite(STDERR, "Participant not found: {$toFind}\n");
    exit(1);
}

$path = (string)($p['qr_path'] ?? '');
if ($path === '' || !is_file($path)) {
    $path = \App\Services\QrService::generate('PART|' . $p['uuid'], $p['uuid']);
    $pdo->prepare('UPDATE participants SET qr_path=? WHERE id=?')->execute([$path, (int)$p['id']]);
}

$first = htmlspecialchars((string)$p['first_name'], ENT_QUOTES);
$to = (string)$p['email'];
$subject = 'DICT AI ROADSHOW 2026 — Your registration QR code';
$body = '<div style="font-family:Arial,sans-serif;color:#1a1a1a;line-height:1.5">'
    . '<p>Hi ' . $first . ',</p>'
    . '<p>Thank you for registering for <strong>DICT AI ROADSHOW 2026</strong>.</p>'
    . '<p>Your check-in QR code is attached. This is a <strong>multi-day event</strong> — keep the same QR and present it each day at the welcome desk.</p>'
    . '<p style="margin:1.25rem 0"><img src="cid:registration-qr" alt="Registration QR code" width="240" height="240" style="display:block;border:1px solid #ddd;border-radius:8px;"></p>'
    . '<p>If the image above does not appear, open the attached PNG file <strong>DICT-AI-ROADSHOW-QR.png</strong>.</p>'
    . '</div>';

$ok = \App\Services\Mailer::send($to, $subject, $body, $path, 'DICT-AI-ROADSHOW-QR.png', true);
echo $ok ? "SENT to {$to}\n" : "FAILED to {$to}\n";
exit($ok ? 0 : 1);
