<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$rows = $pdo->query("SHOW TABLES")->fetchAll();
foreach ($rows as $r) echo implode(',', $r) . "\n";