<?php
/**
 * Clear all attendance records and signature image files.
 * Does NOT delete participants/registrants.
 */
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

$pdo = \App\Services\Database::pdo();
$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'signatures';

$before = (int)$pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
$pdo->exec('DELETE FROM attendance');
$pdo->exec('ALTER TABLE attendance AUTO_INCREMENT = 1');

$deletedFiles = 0;
if (is_dir($root)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $name = $file->getFilename();
        if ($name === 'index.php' || $name === '.htaccess' || $name === '.gitkeep') {
            continue;
        }
        if ($file->isFile()) {
            if (@unlink($file->getPathname())) {
                $deletedFiles++;
            }
        } elseif ($file->isDir()) {
            @rmdir($file->getPathname()); // remove empty year dirs
        }
    }
}

$after = (int)$pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
echo "attendance_before={$before}\n";
echo "attendance_after={$after}\n";
echo "signature_files_deleted={$deletedFiles}\n";
echo "participants_untouched=" . $pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn() . "\n";
echo "done\n";
