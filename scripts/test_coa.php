<?php
declare(strict_types=1);

/**
 * Certificate of Appearance service tests (no SMTP send).
 * Run: C:\xampp\php\php.exe scripts\test_coa.php
 */

require __DIR__ . '/../config/bootstrap.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $rel = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = $base . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

use App\Services\CertificateOfAppearanceService as Coa;
use App\Services\Database;

$failed = 0;
$passed = 0;
$assert = static function (bool $cond, string $msg) use (&$failed, &$passed): void {
    if ($cond) {
        echo "PASS: {$msg}\n";
        $passed++;
    } else {
        echo "FAIL: {$msg}\n";
        $failed++;
    }
};

// --- particulars helpers ---
$defaults = Coa::defaultParticulars();
$assert($defaults['lodging'] === 'not_provided', 'default lodging is not_provided');
$assert($defaults['vehicle'] === 'not_provided', 'default vehicle is not_provided');

$normalized = Coa::normalizeParticulars([
    'lodging' => 'provided',
    'meals' => ['breakfast' => '1', 'lunch' => 0, 'dinner' => true],
    'vehicle' => 'bogus',
]);
$assert($normalized['lodging'] === 'provided', 'normalize lodging provided');
$assert($normalized['meals']['breakfast'] === true, 'normalize breakfast true');
$assert($normalized['meals']['lunch'] === false, 'normalize lunch false');
$assert($normalized['meals']['dinner'] === true, 'normalize dinner true');
$assert($normalized['vehicle'] === 'not_provided', 'invalid vehicle falls back');

$merged = Coa::mergeParticulars($defaults, [
    'lodging' => 'provided',
    'meals' => ['lunch' => true],
]);
$assert($merged['lodging'] === 'provided', 'override lodging');
$assert($merged['meals']['lunch'] === true, 'override lunch');
$assert($merged['meals']['breakfast'] === false, 'unspecified meal stays false from override normalize');

$noOverride = Coa::mergeParticulars(['lodging' => 'provided', 'meals' => ['breakfast' => true], 'vehicle' => 'provided'], null);
$assert($noOverride['lodging'] === 'provided', 'null override keeps defaults');

// --- email resolution ---
$assert(Coa::resolveEmail(['email' => 'a@example.com', 'office_email' => 'b@example.com']) === 'a@example.com', 'prefer personal email');
$assert(Coa::resolveEmail(['email' => '', 'office_email' => 'b@example.com']) === 'b@example.com', 'fallback office email');
$assert(Coa::resolveEmail(['email' => 'bad', 'office_email' => '']) === null, 'skip when no valid email');
$assert(Coa::fullName(['first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz']) === 'Juan Dela Cruz', 'full name');

// --- issue date formatting ---
$assert(str_contains(Coa::formatIssueDate('2026-06-17'), '17th day of June 2026'), 'issue date ordinal');

// --- DB eligibility + PDF ---
$pdo = Database::pdo();
foreach (['coa_signatories', 'coa_send_batches', 'coa_send_items'] as $t) {
    $ok = (bool)$pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t))->fetch();
    $assert($ok, "table {$t} exists");
}

// Ensure tables if migrate off
if (!(bool)$pdo->query("SHOW TABLES LIKE 'coa_signatories'")->fetch()) {
    \App\Services\Database::migrate();
}

$eligible = Coa::eligibleParticipants($pdo, null, null, null);
$assert(is_array($eligible), 'eligibleParticipants returns array');

foreach ($eligible as $row) {
    $assert(isset($row['id'], $row['first_name'], $row['last_name']), 'eligible row has identity fields');
    break;
}

// Count present+signed participants (join required — orphans in attendance are ignored)
$presentCount = (int)$pdo->query(
    "SELECT COUNT(DISTINCT p.id) FROM participants p
     INNER JOIN attendance a ON a.participant_id = p.id
     WHERE COALESCE(a.signature_path, '') <> '' AND COALESCE(a.status, 'present') = 'present'"
)->fetchColumn();
$assert(count($eligible) === $presentCount, 'eligible count matches present+signed participants');

// PDF render smoke test (needs a fake signatory image)
$sigDir = dirname(__DIR__) . '/storage/coa_signatories';
if (!is_dir($sigDir)) {
    mkdir($sigDir, 0775, true);
}
$sigPath = $sigDir . '/test_sig.png';
if (!is_file($sigPath)) {
    // 1x1 PNG
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    file_put_contents($sigPath, $png);
}

$participant = [
    'id' => 0,
    'first_name' => 'Juan',
    'middle_name' => '',
    'last_name' => 'Dela Cruz',
    'agency' => 'Provincial Government of Cagayan',
];
$activity = [
    'venue' => 'DICT Regional Office 2',
    'purpose' => 'coordinating matters related to the programs and projects of DICT Region 02',
    'inclusive_dates' => 'June 17-18, 2026',
    'issue_date' => '2026-06-17',
];
$signatory = [
    'full_name' => 'Ms. Mina Flor T. Villafuerte',
    'title' => 'Chief, Administrative and Finance Division',
    'signature_path' => $sigPath,
];

try {
    $pdfPath = Coa::renderPdf($participant, $activity, $merged, $signatory);
    $assert(is_file($pdfPath), 'PDF file created');
    $assert(filesize($pdfPath) > 500, 'PDF file has content');
    // Verify override lodging text is not required as binary search; just existence
} catch (Throwable $e) {
    $assert(false, 'PDF render: ' . $e->getMessage());
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
