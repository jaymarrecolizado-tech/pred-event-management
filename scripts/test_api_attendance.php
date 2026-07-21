<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Controllers/AttendanceController.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/SignatureService.php';

$_SESSION['staff'] = true;
$_SESSION['csrf'] = bin2hex(random_bytes(32));
$pdo = \App\Services\Database::pdo();
$uuid = $pdo->query('SELECT uuid FROM participants ORDER BY id DESC LIMIT 1')->fetch()['uuid'] ?? '';
$png = 'data:image/png;base64,' . base64_encode(str_repeat("\x89", 200));
$_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf'];
$payload = json_encode(['uuid'=>$uuid, 'signature'=>$png]);
$stream = fopen('php://temp', 'r+'); fwrite($stream, $payload); rewind($stream);
function file_get_contents($f){ return $GLOBALS['payload']; }
(new \App\Controllers\AttendanceController())->submit();