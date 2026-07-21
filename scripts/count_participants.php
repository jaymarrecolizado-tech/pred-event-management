<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$count = $pdo->query('SELECT COUNT(*) AS c FROM participants')->fetch();
echo isset($count['c']) ? (int)$count['c'] : 0;