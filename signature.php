<?php
declare(strict_types=1);

/**
 * Serve attendance signature images.
 * Always returns an image response when possible so <img> tags never show a broken icon
 * from HTML error pages. Does not modify attendance records.
 */

require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $rel = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = $base . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (is_file(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

/**
 * 1x1 transparent PNG — used when the real file is missing so the UI can still render.
 */
function signature_placeholder_png(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true
    ) ?: '';
}

function send_png(string $bytes, int $status = 200, bool $missing = false): void
{
    http_response_code($status);
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="signature.png"');
    header('Cache-Control: ' . ($missing ? 'no-store' : 'private, max-age=60'));
    header('X-Signature-Status: ' . ($missing ? 'missing' : 'ok'));
    echo $bytes;
    exit;
}

try {
    $aid = isset($_GET['aid']) ? (int)$_GET['aid'] : 0;
    if ($aid <= 0) {
        send_png(signature_placeholder_png(), 400, true);
    }

    $pdo = \App\Services\Database::pdo();
    $stmt = $pdo->prepare('SELECT signature_path FROM attendance WHERE id = ?');
    $stmt->execute([$aid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        send_png(signature_placeholder_png(), 404, true);
    }

    $path = \App\Services\SignatureService::resolvePath((string)($row['signature_path'] ?? ''));
    if ($path === null) {
        error_log('signature.php: unresolved path for attendance_id=' . $aid . ' stored=' . (string)($row['signature_path'] ?? ''));
        send_png(signature_placeholder_png(), 404, true);
    }

    $bytes = @file_get_contents($path);
    if ($bytes === false || $bytes === '') {
        error_log('signature.php: unreadable file for attendance_id=' . $aid . ' path=' . $path);
        send_png(signature_placeholder_png(), 404, true);
    }

    // Detect mime from path; default PNG.
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => 'image/png',
    };

    http_response_code(200);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="signature.' . ($ext !== '' ? $ext : 'png') . '"');
    header('Cache-Control: private, max-age=60');
    header('X-Signature-Status: ok');
    echo $bytes;
    exit;
} catch (Throwable $e) {
    error_log('signature.php error: ' . $e->getMessage());
    send_png(signature_placeholder_png(), 500, true);
}
