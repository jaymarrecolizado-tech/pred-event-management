<?php
declare(strict_types=1);

/**
 * Dev helper: seed checker + SEO viewer accounts (idempotent by username).
 *
 * Usage:
 *   php scripts/seed_role_users.php
 *   php scripts/seed_role_users.php checker_user checkerPass123!
 *   php scripts/seed_role_users.php seo_user seoPass12345! seo_viewer
 */

require __DIR__ . '/../config/bootstrap.php';

spl_autoload_register(static function ($class): void {
    $prefix = 'App\\';
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $rel = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = $base . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Services\AuthService;
use App\Services\Database;

Database::migrate();
$pdo = Database::pdo();

$pairs = [
    [
        'username' => $argv[1] ?? 'checker',
        'password' => $argv[2] ?? 'CheckerPass123!',
        'role' => $argv[3] ?? AuthService::ROLE_CHECKER,
        'display_name' => 'Attendance Checker',
    ],
    [
        'username' => 'seo',
        'password' => 'SeoViewer123!',
        'role' => AuthService::ROLE_SEO,
        'display_name' => 'SEO Viewer',
    ],
];

// If custom argv provided, only seed that one account.
if (isset($argv[1])) {
    $pairs = [[
        'username' => (string)$argv[1],
        'password' => (string)($argv[2] ?? 'ChangeMe123!'),
        'role' => (string)($argv[3] ?? AuthService::ROLE_CHECKER),
        'display_name' => (string)$argv[1],
    ]];
}

$allowed = [AuthService::ROLE_ADMIN, AuthService::ROLE_CHECKER, AuthService::ROLE_SEO];

foreach ($pairs as $pair) {
    if (!in_array($pair['role'], $allowed, true)) {
        fwrite(STDERR, "Invalid role: {$pair['role']}\n");
        continue;
    }
    if (strlen($pair['password']) < 10) {
        fwrite(STDERR, "Password too short for {$pair['username']}\n");
        continue;
    }

    $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$pair['username']]);
    $existing = $stmt->fetch();
    $hash = password_hash($pair['password'], PASSWORD_BCRYPT);

    if ($existing) {
        $upd = $pdo->prepare('UPDATE admins SET password_hash = ?, role = ?, display_name = ?, is_active = 1 WHERE id = ?');
        $upd->execute([$hash, $pair['role'], $pair['display_name'], (int)$existing['id']]);
        echo "updated {$pair['username']} ({$pair['role']})\n";
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO admins (username, display_name, password_hash, email, role, is_active) VALUES (?,?,?,?,?,1)'
        );
        $ins->execute([$pair['username'], $pair['display_name'], $hash, null, $pair['role']]);
        echo "created {$pair['username']} ({$pair['role']})\n";
    }
}

echo "done\n";
