<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/Uuid.php';
require __DIR__ . '/../src/Services/QrService.php';
require __DIR__ . '/../src/Services/SignatureService.php';

$pdo = \App\Services\Database::pdo();
$need = 15;
$rows = $pdo->query('SELECT id, uuid, first_name, last_name FROM participants ORDER BY id DESC LIMIT '.$need)->fetchAll();
if (count($rows) < $need) {
    for ($i = count($rows); $i < $need; $i++) {
        $uuid = \App\Services\Uuid::v4();
        $pdo->prepare('INSERT INTO participants (uuid, first_name, last_name) VALUES (?,?,?)')->execute([$uuid,'Guest'.($i+1),'Sample']);
        $rows[] = ['id' => (int)$pdo->lastInsertId(), 'uuid' => $uuid, 'first_name' => 'Guest'.($i+1), 'last_name' => 'Sample'];
    }
}
$event = $pdo->query('SELECT id FROM events WHERE active=1 ORDER BY id DESC LIMIT 1')->fetch();
$eventId = $event ? (int)$event['id'] : null;

function makeSig(string $text): string {
    $w = 300; $h = 120; $im = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($im, 255,255,255);
    $black = imagecolorallocate($im, 20,20,20);
    imagefilledrectangle($im,0,0,$w,$h,$white);
    imageline($im,10,60,280,60,$black);
    imagestring($im, 5, 14, 40, $text, $black);
    ob_start(); imagepng($im); $png = ob_get_clean(); imagedestroy($im);
    return 'data:image/png;base64,' . base64_encode($png);
}

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$ins = $pdo->prepare('INSERT INTO attendance (participant_id, attendance_date, time_in, signature_path, event_id) VALUES (?,?,?,?,?)');

for ($i = 0; $i < 10; $i++) {
    $p = $rows[$i];
    $sig = \App\Services\SignatureService::saveBase64($p['uuid'], makeSig('sig'.($i+1)));
    $ins->execute([(int)$p['id'], $today, date('H:i:s'), $sig, $eventId]);
}
for ($i = 10; $i < 15; $i++) {
    $p = $rows[$i];
    $sig = \App\Services\SignatureService::saveBase64($p['uuid'], makeSig('sig'.($i+1)));
    $ins->execute([(int)$p['id'], $yesterday, date('H:i:s', strtotime('-1 day')), $sig, $eventId]);
}

$cToday = (int)$pdo->prepare('SELECT COUNT(*) AS c FROM attendance WHERE attendance_date = ?')->execute([$today]) ?: 0;
$st = $pdo->prepare('SELECT COUNT(*) AS c FROM attendance WHERE attendance_date = ?');
$st->execute([$today]);
$ct = (int)$st->fetch()['c'];
$st->execute([$yesterday]);
$cy = (int)$st->fetch()['c'];
echo json_encode(['today'=>$ct,'yesterday'=>$cy]);