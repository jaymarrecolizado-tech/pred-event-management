<?php
declare(strict_types=1);

/**
 * End-to-end registration via HTTP (local XAMPP).
 * Run: C:\xampp\php\php.exe scripts/e2e_register_test.php
 */

$base = getenv('APP_TEST_BASE') ?: 'https://localhost/Projects/event_,management';
$cookie = tempnam(sys_get_temp_dir(), 'e2e_cookie_');

function req(string $method, string $url, ?array $post, string $cookieFile): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) {
        return ['code' => 0, 'headers' => '', 'body' => $err, 'location' => ''];
    }
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
        $location = trim($m[1]);
    }
    return ['code' => $code, 'headers' => $headers, 'body' => $body, 'location' => $location];
}

echo "GET register form...\n";
$get = req('GET', $base . '/?r=register', null, $cookie);
if ($get['code'] !== 200) {
    fwrite(STDERR, "FAIL: register page HTTP {$get['code']}\n{$get['body']}\n");
    @unlink($cookie);
    exit(1);
}

if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $get['body'], $m)) {
    fwrite(STDERR, "FAIL: CSRF token not found\n");
    @unlink($cookie);
    exit(1);
}
$csrf = $m[1];
$email = 'e2e_' . bin2hex(random_bytes(3)) . '@example.com';
$mobile = '0917' . str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

$payload = [
    'csrf' => $csrf,
    'first_name' => 'E2E',
    'middle_name' => 'Test',
    'last_name' => 'User',
    'nickname' => 'E2E',
    'sex' => 'Other',
    'email' => $email,
    'sector' => 'National Government Agency',
    'agency_select' => 'DICT Region 02',
    'agency_other' => '',
    'designation_select' => 'ISA III',
    'designation_other' => '',
    'contact_no' => $mobile,
];

echo "POST register_submit ({$email}, {$mobile})...\n";
$post = req('POST', $base . '/?r=register_submit', $payload, $cookie);
$okRedirect = $post['code'] >= 300 && $post['code'] < 400 && str_contains($post['location'], 'register_success');
if (!$okRedirect) {
    fwrite(STDERR, "FAIL: expected redirect to success, got HTTP {$post['code']} loc={$post['location']}\n");
    fwrite(STDERR, substr($post['body'], 0, 500) . "\n");
    @unlink($cookie);
    exit(1);
}

echo "PASS: redirected to {$post['location']}\n";

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
$pdo = \App\Services\Database::pdo();
$stmt = $pdo->prepare('SELECT id, registration_status, contact_no, email FROM participants WHERE email = ?');
$stmt->execute([$email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "FAIL: participant not found in DB\n");
    @unlink($cookie);
    exit(1);
}

$id = (int)$row['id'];
$status = (string)$row['registration_status'];
$storedMobile = (string)$row['contact_no'];
echo "DB id={$id} status={$status} mobile={$storedMobile}\n";

$pass = $id > 0 && $status === 'Registered' && str_starts_with($storedMobile, '+639');
if (!$pass) {
    fwrite(STDERR, "FAIL: unexpected DB values\n");
    @unlink($cookie);
    exit(1);
}

// cleanup test row
$pdo->prepare('DELETE FROM participants WHERE email = ?')->execute([$email]);
@unlink($cookie);
echo "PASS: e2e registration OK (cleaned up)\n";
exit(0);
