<?php
declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

spl_autoload_register(function($class){
    $prefix = 'App\\';
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $rel = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = $base . $rel . '.php';
    if (is_file($file)) require $file;
});
if (is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

$_SESSION['admin_id'] = 1;
$csrf = csrf_token();

use App\Services\Database;
use App\Controllers\AdminSignatureController;

$pdo = Database::pdo();
$att = $pdo->query('SELECT a.id, p.uuid FROM attendance a JOIN participants p ON p.id=a.participant_id ORDER BY a.id DESC LIMIT 1')->fetch();
if (!$att) {
    $p = $pdo->query('SELECT id, uuid FROM participants ORDER BY id DESC LIMIT 1')->fetch();
    if (!$p) { echo "{\"error\":\"no_participants\"}"; exit; }
    $uuid = $p['uuid'];
    $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
    $ctrl = new AdminSignatureController();
    $resNew = $ctrl->addNewJsonForTest(['uuid'=>$uuid,'date'=>date('Y-m-d'),'signature'=>$sig], $csrf);
    $att = $pdo->query('SELECT a.id, p.uuid FROM attendance a JOIN participants p ON p.id=a.participant_id ORDER BY a.id DESC LIMIT 1')->fetch();
}

$aid = (int)$att['id'];
$uuid = (string)$att['uuid'];
$sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';

$ctrl = new AdminSignatureController();
$resReplace = $ctrl->replaceJsonForTest(['aid'=>$aid,'signature'=>$sig], $csrf);
$resNewAgain = $ctrl->addNewJsonForTest(['uuid'=>$uuid,'date'=>date('Y-m-d'),'signature'=>$sig], $csrf);

echo json_encode(['replace'=>$resReplace,'new'=>$resNewAgain]);