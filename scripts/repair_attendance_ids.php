<?php
/**
 * Repair attendance rows with id=0 (breaks Present badge + signature.php?aid=).
 * Safe to re-run. Run: C:\xampp\php\php.exe scripts\repair_attendance_ids.php
 */
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$count = (int)$pdo->query('SELECT COUNT(*) FROM attendance WHERE id = 0')->fetchColumn();
if ($count === 0) {
    echo "No attendance rows with id=0. Nothing to repair.\n";
    exit(0);
}

$max = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM attendance')->fetchColumn();
$next = max($max + 1, 1);

$pdo->beginTransaction();
try {
    $stmt = $pdo->query('SELECT participant_id, attendance_date, time_in, signature_path, event_id, status, created_at, purpose FROM attendance WHERE id = 0');
    $zeros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pdo->exec('DELETE FROM attendance WHERE id = 0');

    $ins = $pdo->prepare(
        'INSERT INTO attendance (id, participant_id, attendance_date, time_in, signature_path, event_id, status, created_at, purpose)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    foreach ($zeros as $row) {
        $ins->execute([
            $next,
            $row['participant_id'],
            $row['attendance_date'],
            $row['time_in'],
            $row['signature_path'],
            $row['event_id'],
            $row['status'] ?? 'present',
            $row['created_at'] ?? date('Y-m-d H:i:s'),
            $row['purpose'] ?? 'standard',
        ]);
        echo "Repaired participant {$row['participant_id']} -> attendance id {$next}\n";
        $next++;
    }
    $pdo->exec('ALTER TABLE attendance AUTO_INCREMENT = ' . $next);
    $pdo->commit();
    echo "Done. Repaired {$count} row(s). AUTO_INCREMENT={$next}\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Repair failed: ' . $e->getMessage() . "\n");
    exit(1);
}
