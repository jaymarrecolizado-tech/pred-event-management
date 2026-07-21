<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/Uuid.php';
require __DIR__ . '/../src/Services/SignatureService.php';
require __DIR__ . '/../src/Controllers/AttendanceController.php';

$_SESSION['staff'] = true;
$_SESSION['csrf'] = bin2hex(random_bytes(32));
$pdo = \App\Services\Database::pdo();
$pdo->exec('INSERT INTO events (name,enforce_single_time_in,active) VALUES ("QA Event",1,1)');

$uuid = \App\Services\Uuid::v4();
$pdo->prepare('INSERT INTO participants (uuid,first_name,last_name) VALUES (?,?,?)')->execute([$uuid,'QA','Tester']);
$png = 'data:image/png;base64,' . base64_encode(random_bytes(128));
$ctrl = new \App\Controllers\AttendanceController();
$r1 = $ctrl->submitJsonForTest(['uuid'=>$uuid,'signature'=>$png], $_SESSION['csrf']);
$r2 = $ctrl->submitJsonForTest(['uuid'=>$uuid,'signature'=>$png], $_SESSION['csrf']);
echo ($r1['ok']??false) ? "first_ok\n" : "first_fail\n";
echo (isset($r2['error']) && $r2['error']==='already_marked') ? "second_blocked\n" : "second_fail\n";