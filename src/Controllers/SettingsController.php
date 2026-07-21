<?php
declare(strict_types=1);

namespace App\Controllers;

class SettingsController
{
    public function form(): void
    {
        if (empty($_SESSION['admin_id'])) { header('Location: ?r=admin_login'); return; }
        $env = [
            'SMTP_HOST' => getenv('SMTP_HOST') ?: '',
            'SMTP_PORT' => getenv('SMTP_PORT') ?: '',
            'SMTP_USER' => getenv('SMTP_USER') ?: '',
            'SMTP_SECURE' => getenv('SMTP_SECURE') ?: 'tls',
            'SMTP_FROM' => getenv('SMTP_FROM') ?: '',
        ];
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_settings.php';
    }

    public function save(): void
    {
        if (empty($_SESSION['admin_id'])) { header('Location: ?r=admin_login'); return; }
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) { http_response_code(400); echo 'Invalid CSRF'; return; }
        $host = trim((string)($_POST['SMTP_HOST'] ?? ''));
        $port = trim((string)($_POST['SMTP_PORT'] ?? ''));
        $user = trim((string)($_POST['SMTP_USER'] ?? ''));
        $pass = trim((string)($_POST['SMTP_PASS'] ?? ''));
        $secure = trim((string)($_POST['SMTP_SECURE'] ?? 'tls'));
        $from = trim((string)($_POST['SMTP_FROM'] ?? ''));
        $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        $lines = [];
        $set = function(string $k, string $v) use (&$lines){ $lines[] = $k.'='.$v; };
        $set('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
        $set('DB_NAME', getenv('DB_NAME') ?: 'event_db');
        $set('DB_USER', getenv('DB_USER') ?: 'root');
        $set('DB_PASS', getenv('DB_PASS') ?: '');
        $set('MAIL_MODE', 'smtp');
        $set('SMTP_HOST', $host);
        $set('SMTP_PORT', $port ?: '465');
        $set('SMTP_USER', $user);
        if ($pass !== '') $set('SMTP_PASS', $pass); else if (getenv('SMTP_PASS')) $set('SMTP_PASS', getenv('SMTP_PASS'));
        $set('SMTP_SECURE', $secure ?: 'ssl');
        $set('SMTP_FROM', $from ?: $user);
        $set('QR_EXTERNAL', getenv('QR_EXTERNAL') ?: 'false');
        file_put_contents($file, implode("\n", $lines) . "\n");
        header('Location: ?r=admin_settings');
    }
}