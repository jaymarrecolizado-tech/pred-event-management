<?php
declare(strict_types=1);

/**
 * Clear attendee/registration test data via PDO (.env credentials).
 * Prefer: bash scripts/clear_attendee_data.sh
 */

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

use App\Services\Database;

$pdo = Database::pdo();

$countsBefore = [];
foreach (['participants', 'attendance', 'coa_send_items', 'coa_send_batches', 'import_logs', 'rate_limits'] as $table) {
    try {
        $countsBefore[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    } catch (Throwable $e) {
        $countsBefore[$table] = -1; // table missing
    }
}

echo "Before:\n";
foreach ($countsBefore as $table => $n) {
    echo "  {$table}: " . ($n < 0 ? '(missing)' : $n) . "\n";
}

$pdo->beginTransaction();
try {
    $steps = [
        'coa_send_items',
        'coa_send_batches',
        'attendance',
        'participants',
        'import_logs',
        'rate_limits',
    ];

    foreach ($steps as $table) {
        if (($countsBefore[$table] ?? -1) < 0) {
            continue;
        }
        $pdo->exec("DELETE FROM `{$table}`");
        echo "Deleted from {$table}\n";
    }

    foreach (['participants', 'attendance', 'coa_send_items', 'coa_send_batches', 'import_logs'] as $table) {
        if (($countsBefore[$table] ?? -1) < 0) {
            continue;
        }
        $pdo->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "After:\n";
foreach (['participants', 'attendance', 'coa_send_items', 'coa_send_batches'] as $table) {
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        echo "  {$table}: {$n}\n";
    } catch (Throwable $e) {
        echo "  {$table}: (missing)\n";
    }
}

echo "Database clear complete.\n";
