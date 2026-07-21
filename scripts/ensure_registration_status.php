<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
\App\Services\Database::migrate();

$col = $pdo->query("SHOW COLUMNS FROM participants LIKE 'registration_status'")->fetch(PDO::FETCH_ASSOC);
echo $col ? ("OK: registration_status " . $col['Type'] . " default=" . $col['Default'] . PHP_EOL) : "MISSING\n";
