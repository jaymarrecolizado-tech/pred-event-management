<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$sql = file_get_contents(__DIR__ . '/../migrations/002_action_logs.sql');
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/',$sql))) as $stmt) {
    if ($stmt !== '') $pdo->exec($stmt);
}
echo 'migrated_002';