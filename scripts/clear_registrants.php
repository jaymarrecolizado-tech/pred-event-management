<?php
/**
 * Remove ALL registrants/participants and related artifacts.
 * Also clears attendance, CoA send items, and QR image files.
 */
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$base = dirname(__DIR__);

$before = [
    'participants' => (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn(),
    'attendance' => (int)$pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn(),
];

// Child rows first (FKs may or may not exist depending on migration history)
if ((bool)$pdo->query("SHOW TABLES LIKE 'coa_send_items'")->fetch()) {
    $pdo->exec('DELETE FROM coa_send_items');
}
$pdo->exec('DELETE FROM attendance');
$pdo->exec('DELETE FROM participants');

$pdo->exec('ALTER TABLE attendance AUTO_INCREMENT = 1');
$pdo->exec('ALTER TABLE participants AUTO_INCREMENT = 1');

$deletedFiles = 0;
$clearDir = static function (string $root) use (&$deletedFiles): void {
    if (!is_dir($root)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $name = $file->getFilename();
        if (in_array($name, ['index.php', '.htaccess', '.gitkeep'], true)) {
            continue;
        }
        if ($file->isFile()) {
            if (@unlink($file->getPathname())) {
                $deletedFiles++;
            }
        } elseif ($file->isDir()) {
            @rmdir($file->getPathname());
        }
    }
};

$clearDir($base . '/storage/signatures');
$clearDir($base . '/storage/qrcodes');
$clearDir($base . '/storage/certificates');

echo 'participants_before=' . $before['participants'] . PHP_EOL;
echo 'attendance_before=' . $before['attendance'] . PHP_EOL;
echo 'participants_after=' . $pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn() . PHP_EOL;
echo 'attendance_after=' . $pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn() . PHP_EOL;
echo 'files_deleted=' . $deletedFiles . PHP_EOL;
echo "done\n";
