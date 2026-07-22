<?php
declare(strict_types=1);

/**
 * Smoke test for recent registration / import / identity fixes.
 * Run: C:\xampp\php\php.exe scripts/smoke_test.php
 */

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/ParticipantValidator.php';

use App\Services\Database;
use App\Services\ParticipantValidator;

$passed = 0;
$failed = 0;

function assert_true(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        echo "[PASS] {$label}\n";
        $passed++;
    } else {
        echo "[FAIL] {$label}\n";
        $failed++;
    }
}

echo "=== DICT AI ROADSHOW smoke test ===\n\n";

$pdo = Database::pdo();
assert_true($pdo instanceof PDO, 'DB connection');

$col = $pdo->query("SHOW COLUMNS FROM participants LIKE 'registration_status'")->fetch(PDO::FETCH_ASSOC);
assert_true((bool)$col, 'participants.registration_status column exists');

$idMeta = $pdo->query("SHOW COLUMNS FROM participants WHERE Field='id'")->fetch(PDO::FETCH_ASSOC);
assert_true(stripos((string)($idMeta['Extra'] ?? ''), 'auto_increment') !== false, 'participants.id is AUTO_INCREMENT');
assert_true(strtoupper((string)($idMeta['Key'] ?? '')) === 'PRI', 'participants.id is PRIMARY KEY');

$zeroParticipants = (int)$pdo->query('SELECT COUNT(*) FROM participants WHERE id = 0')->fetchColumn();
assert_true($zeroParticipants === 0, 'no participants with id=0');

$zeroAttendance = (int)$pdo->query('SELECT COUNT(*) FROM attendance WHERE id = 0')->fetchColumn();
assert_true($zeroAttendance === 0, 'no attendance with id=0');

// PH mobile normalization
assert_true(ParticipantValidator::normalizePhMobile('09171234567') === '+639171234567', 'normalize 09…');
assert_true(ParticipantValidator::normalizePhMobile('+639171234567') === '+639171234567', 'normalize +639…');
assert_true(ParticipantValidator::normalizePhMobile('639171234567') === '+639171234567', 'normalize 639…');
assert_true(ParticipantValidator::normalizePhMobile('bad') === null, 'reject invalid mobile');
assert_true(ParticipantValidator::normalizePhMobile('0917123456') === null, 'reject short mobile');

$ok = ParticipantValidator::validateForRegistration([
    'first_name' => 'Smoke',
    'last_name' => 'Tester',
    'email' => 'smoke.tester@example.com',
    'sector' => 'National Government Agency',
    'agency' => 'DICT Region 02',
    'contact_no' => '09171234567',
]);
assert_true($ok['errors'] === [], 'valid registration payload accepted');
assert_true(($ok['data']['contact_no'] ?? null) === '+639171234567', 'mobile stored as +639…');

$badMobile = ParticipantValidator::validateForRegistration([
    'first_name' => 'Smoke',
    'last_name' => 'Tester',
    'email' => 'smoke2@example.com',
    'sector' => 'National Government Agency',
    'agency' => 'DICT Region 02',
    'contact_no' => '12345',
]);
assert_true(isset($badMobile['errors']['contact_no']), 'invalid mobile rejected');

$missingEmail = ParticipantValidator::validateForRegistration([
    'first_name' => 'Smoke',
    'last_name' => 'Tester',
    'email' => '',
    'sector' => 'National Government Agency',
    'agency' => 'DICT Region 02',
    'contact_no' => '09171234567',
]);
assert_true(isset($missingEmail['errors']['email']), 'missing email rejected');

// Assets / branding files
$root = dirname(__DIR__);
assert_true(
    is_file($root . '/assets/dict-ai-roadshow-2026-banner.jpg')
    || is_file($root . '/assets/dict-ai-roadshow-2026-banner.png'),
    'banner image present'
);
assert_true(is_file($root . '/assets/guest-registration.js'), 'registration JS present');
assert_true(is_file($root . '/views/register.php'), 'register view present');

$registerPhp = file_get_contents($root . '/views/register.php') ?: '';
assert_true(str_contains($registerPhp, 'DICT AI ROADSHOW 2026'), 'register title branded');
assert_true(str_contains($registerPhp, 'name="contact_no"'), 'mobile field in register form');
assert_true(!str_contains($registerPhp, 'name="office_email"'), 'office email removed from register form');
assert_true(str_contains($registerPhp, 'id="btnRegister"'), 'submit button present');
assert_true(str_contains($registerPhp, 'guest-submit-fallback'), 'no-js fallback present');

$css = file_get_contents($root . '/assets/guest-registration.css') ?: '';
assert_true(str_contains($css, 'display: none !important'), 'fallback submit hide uses !important');

$footer = file_get_contents($root . '/views/partials/guest_footer.php') ?: '';
assert_true(str_contains($footer, 'JE Lite of DICT R2'), 'footer credit present');

// Live insert/delete roundtrip with Pre-reg vs Registered semantics via SQL
$email = 'smoke_' . bin2hex(random_bytes(4)) . '@example.com';
$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    random_int(0, 0xffff), random_int(0, 0xffff),
    random_int(0, 0xffff),
    random_int(0, 0x0fff) | 0x4000,
    random_int(0, 0x3fff) | 0x8000,
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
);

$ins = $pdo->prepare(
    'INSERT INTO participants (uuid,email,first_name,last_name,sector,agency,contact_no,registration_status,qr_path)
     VALUES (?,?,?,?,?,?,?,?,NULL)'
);
$ins->execute([$uuid, $email, 'Smoke', 'Insert', 'GOCCs', 'DICT', '+639171234567', 'Pre-reg']);
$newId = (int)$pdo->lastInsertId();
assert_true($newId > 0, 'insert returns non-zero lastInsertId');

$row = $pdo->prepare('SELECT id, registration_status, contact_no FROM participants WHERE uuid = ?');
$row->execute([$uuid]);
$saved = $row->fetch(PDO::FETCH_ASSOC);
assert_true((int)($saved['id'] ?? 0) === $newId, 'inserted participant readable');
assert_true(($saved['registration_status'] ?? '') === 'Pre-reg', 'Pre-reg status persisted');

$pdo->prepare('DELETE FROM participants WHERE uuid = ?')->execute([$uuid]);
$gone = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE uuid = ?');
$gone->execute([$uuid]);
assert_true((int)$gone->fetchColumn() === 0, 'smoke participant cleaned up');

echo "\n=== Result: {$passed} passed, {$failed} failed ===\n";
exit($failed === 0 ? 0 : 1);
