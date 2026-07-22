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
            $this->redirect('?r=' . AuthService::loginHomeRoute());
        }
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_login.php';
    }

    public function login(): void
    {
        try {
            $this->handleLogin();
        } catch (\Throwable $e) {
            error_log('Login error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            $debug = getenv('APP_DEBUG') === 'true';
            echo $debug
                ? ('Login failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES))
                : 'Login temporarily unavailable. Please try again, or ask the admin to run migrations / check .env database settings.';
        }
    }

    private function handleLogin(): void
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
        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash, email, role, is_active, display_name
             FROM admins WHERE username = ? LIMIT 1'
        );
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

        try {
            $upd = $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
            $upd->execute([(int)$admin['id']]);
        } catch (\Throwable $e) {
            // Column may be missing on very old DBs; login should still proceed.
            error_log('last_login_at update skipped: ' . $e->getMessage());
        }

        \App\Services\Logger::log((int)$admin['id'], 'login_success', [
            'ip' => $ip,
            'role' => (string)($admin['role'] ?? AuthService::ROLE_ADMIN),
        ]);

        $this->redirect('?r=' . AuthService::loginHomeRoute((string)($admin['role'] ?? AuthService::ROLE_ADMIN)));
    }

    public function logout(): void
    {
        try {
            $id = AuthService::id();
            if ($id) {
                \App\Services\Logger::log($id, 'logout', ['role' => AuthService::role()]);
            }
        } catch (\Throwable $e) {
            error_log('Logout log skipped: ' . $e->getMessage());
        }
        AuthService::logoutLocal();
        $this->redirect('?r=admin_login');
    }

    private function redirect(string $location): void
    {
        if (!headers_sent()) {
            header('Location: ' . $location);
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        exit;
    }
}
