<?php
declare(strict_types=1);

/**
 * One-shot: restore AUTO_INCREMENT primary keys and fix id=0 rows.
 * Run: C:\xampp\php\php.exe scripts/repair_identity_columns.php
 */

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();

echo "Before:\n";
foreach (['participants', 'attendance', 'events'] as $table) {
    $zero = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE id = 0")->fetchColumn();
    $meta = $pdo->query(
        "SELECT COLUMN_KEY, EXTRA FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = 'id'"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    echo "  {$table}: zero_ids={$zero} key=" . ($meta['COLUMN_KEY'] ?? '?') . ' extra=' . ($meta['EXTRA'] ?? '?') . "\n";
}

\App\Services\Database::migrate();

echo "\nAfter:\n";
foreach (['participants', 'attendance', 'events'] as $table) {
    $zero = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE id = 0")->fetchColumn();
    $meta = $pdo->query(
        "SELECT COLUMN_KEY, EXTRA FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = 'id'"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    $max = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM `{$table}`")->fetchColumn();
    echo "  {$table}: zero_ids={$zero} key=" . ($meta['COLUMN_KEY'] ?? '?') . ' extra=' . ($meta['EXTRA'] ?? '?') . " max_id={$max}\n";
}

$rows = $pdo->query('SELECT id, uuid, first_name, last_name FROM participants ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
echo "\nParticipants:\n";
foreach ($rows as $r) {
    echo "  #{$r['id']} {$r['first_name']} {$r['last_name']} ({$r['uuid']})\n";
}

echo "\nDone.\n";
