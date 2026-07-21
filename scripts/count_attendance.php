<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$uuid = $argv[1] ?? '';
$stmt = $pdo->prepare('SELECT id FROM participants WHERE uuid = ?');
$stmt->execute([$uuid]);
$row = $stmt->fetch();
if (!$row) { echo 'not_found'; exit; }
$pid = (int)$row['id'];
$cnt = (int)$pdo->prepare('SELECT COUNT(*) AS c FROM attendance WHERE participant_id = ?')->execute([$pid]) ?: 0;
$q = $pdo->prepare('SELECT COUNT(*) AS c FROM attendance WHERE participant_id = ?');
$q->execute([$pid]);
echo (int)$q->fetch()['c'];