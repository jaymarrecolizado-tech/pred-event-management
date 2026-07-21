<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\RateLimiter;

class AuthController
{
    private const LOCKOUT_MAX = 5;
    private const LOCKOUT_WINDOW = 900;

    public function loginForm(): void
    {
        if (AuthService::check()) {
            header('Location: ?r=' . AuthService::loginHomeRoute());
            return;
        }
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_login.php';
    }

    public function login(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $failKey = 'login_fail:' . $ip;

        if (RateLimiter::isLocked($failKey, self::LOCKOUT_MAX, self::LOCKOUT_WINDOW)) {
            http_response_code(429);
            echo 'Too many failed attempts. Try again in 15 minutes.';
            return;
        }

        $ok = RateLimiter::allow('login:' . $ip, 10, 60);
        if (!$ok) {
            http_response_code(429);
            echo 'Too Many Attempts';
            return;
        }

        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return;
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            http_response_code(422);
            echo 'Missing';
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, email, role, is_active, display_name FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (
            !$admin
            || (int)($admin['is_active'] ?? 0) !== 1
            || !password_verify($password, (string)$admin['password_hash'])
        ) {
            RateLimiter::increment($failKey, self::LOCKOUT_WINDOW);
            \App\Services\Logger::log(null, 'login_failed', ['username' => $username, 'ip' => $ip]);
            http_response_code(401);
            echo 'Invalid credentials';
            return;
        }

        RateLimiter::clear($failKey);
        AuthService::establishSession($admin);

        $upd = $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
        $upd->execute([(int)$admin['id']]);

        \App\Services\Logger::log((int)$admin['id'], 'login_success', [
            'ip' => $ip,
            'role' => (string)($admin['role'] ?? AuthService::ROLE_ADMIN),
        ]);

        header('Location: ?r=' . AuthService::loginHomeRoute((string)($admin['role'] ?? AuthService::ROLE_ADMIN)));
    }

    public function logout(): void
    {
        $id = AuthService::id();
        if ($id) {
            \App\Services\Logger::log($id, 'logout', ['role' => AuthService::role()]);
        }
        AuthService::logoutLocal();
        header('Location: ?r=admin_login');
    }
}
