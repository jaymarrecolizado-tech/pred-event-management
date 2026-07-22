<?php
declare(strict_types=1);

/**
 * Seed / reset an admin user (safe for empty Hostinger DBs).
 * Usage: php scripts/seed_admin.php admin 'YourStrongPassword'
 */

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

use App\Services\Database;

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? '';
if ($password === '') {
    fwrite(STDERR, "Usage: php scripts/seed_admin.php <username> <password>\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$pdo = Database::pdo();
$hash = password_hash($password, PASSWORD_BCRYPT);

$hasRole = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'");
    $hasRole = (bool)$chk->fetch();
} catch (Throwable $e) {
    $hasRole = false;
}

$existing = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
$existing->execute([$username]);
$row = $existing->fetch(PDO::FETCH_ASSOC);

if ($row) {
    if ($hasRole) {
        $upd = $pdo->prepare('UPDATE admins SET password_hash = ?, is_active = 1, role = COALESCE(role, \'admin\') WHERE id = ?');
        $upd->execute([$hash, (int)$row['id']]);
    } else {
        $upd = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $upd->execute([$hash, (int)$row['id']]);
    }
    echo "Updated password for existing user: {$username}\n";
    exit(0);
}

if ($hasRole) {
    $stmt = $pdo->prepare(
        'INSERT INTO admins (username, password_hash, email, role, is_active, display_name) VALUES (?,?,?,?,1,?)'
    );
    $stmt->execute([$username, $hash, null, 'admin', $username]);
} else {
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, email) VALUES (?,?,?)');
    $stmt->execute([$username, $hash, null]);
}

echo "Created admin user: {$username}\n";
