<?php
declare(strict_types=1);

namespace App\Services;

final class AuthService
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_CHECKER = 'checker';
    public const ROLE_SEO = 'seo_viewer';

    private const IDLE_TIMEOUT_SEC = 7200; // 2 hours
    private const ABSOLUTE_TIMEOUT_SEC = 28800; // 8 hours

    public static function check(): bool
    {
        if (empty($_SESSION['admin_id'])) {
            return false;
        }
        if (!self::touchActivity()) {
            self::logoutLocal();
            return false;
        }
        if (empty($_SESSION['admin_role'])) {
            self::hydrateFromDatabase((int)$_SESSION['admin_id']);
        }
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_role']);
    }

    public static function id(): ?int
    {
        return self::check() ? (int)$_SESSION['admin_id'] : null;
    }

    public static function role(): ?string
    {
        return self::check() ? (string)($_SESSION['admin_role'] ?? '') : null;
    }

    public static function username(): ?string
    {
        return self::check() ? (string)($_SESSION['admin_username'] ?? '') : null;
    }

    public static function displayName(): ?string
    {
        if (!self::check()) {
            return null;
        }
        $name = trim((string)($_SESSION['admin_display_name'] ?? ''));
        return $name !== '' ? $name : self::username();
    }

    public static function hasRole(string ...$roles): bool
    {
        $current = self::role();
        if ($current === null || $current === '') {
            return false;
        }
        return in_array($current, $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::hasRole(self::ROLE_ADMIN);
    }

    public static function isChecker(): bool
    {
        return self::hasRole(self::ROLE_CHECKER);
    }

    public static function isSeoViewer(): bool
    {
        return self::hasRole(self::ROLE_SEO);
    }

    public static function roleLabel(?string $role = null): string
    {
        $role = $role ?? self::role();
        return match ($role) {
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_CHECKER => 'Attendance Checker',
            self::ROLE_SEO => 'SEO Viewer',
            default => 'Staff',
        };
    }

    public static function loginHomeRoute(?string $role = null): string
    {
        $role = $role ?? self::role();
        return match ($role) {
            self::ROLE_CHECKER => 'admin_attendance',
            self::ROLE_SEO => 'admin_seo_dashboard',
            default => 'admin_registrants',
        };
    }

    /**
     * Establish authenticated session after password verification.
     *
     * @param array<string,mixed> $admin
     */
    public static function establishSession(array $admin): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_role'] = (string)($admin['role'] ?? self::ROLE_ADMIN);
        $_SESSION['admin_username'] = (string)($admin['username'] ?? '');
        $_SESSION['admin_display_name'] = (string)($admin['display_name'] ?? $admin['username'] ?? '');
        $_SESSION['auth_issued_at'] = time();
        $_SESSION['auth_last_activity'] = time();

        if (function_exists('csrf_rotate')) {
            csrf_rotate();
        }
    }

    public static function logoutLocal(): void
    {
        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_role'],
            $_SESSION['admin_username'],
            $_SESSION['admin_display_name'],
            $_SESSION['auth_issued_at'],
            $_SESSION['auth_last_activity']
        );
    }

    public static function deny(string $method, string $fallbackRoute = 'admin_login'): void
    {
        if (strtoupper($method) === 'GET') {
            if (self::check()) {
                header('Location: ?r=' . self::loginHomeRoute());
            } else {
                header('Location: ?r=' . $fallbackRoute);
            }
            return;
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'forbidden', 'code' => 403]);
    }

    private static function touchActivity(): bool
    {
        $now = time();
        $issued = (int)($_SESSION['auth_issued_at'] ?? $now);
        $last = (int)($_SESSION['auth_last_activity'] ?? $now);

        if (($now - $issued) > self::ABSOLUTE_TIMEOUT_SEC) {
            return false;
        }
        if (($now - $last) > self::IDLE_TIMEOUT_SEC) {
            return false;
        }

        $_SESSION['auth_last_activity'] = $now;
        if (empty($_SESSION['auth_issued_at'])) {
            $_SESSION['auth_issued_at'] = $now;
        }
        return true;
    }

    private static function hydrateFromDatabase(int $adminId): void
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT id, username, display_name, role, is_active FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([$adminId]);
            $row = $stmt->fetch();
            if (!$row || (int)($row['is_active'] ?? 0) !== 1) {
                self::logoutLocal();
                return;
            }
            $_SESSION['admin_role'] = (string)($row['role'] ?? self::ROLE_ADMIN);
            $_SESSION['admin_username'] = (string)($row['username'] ?? '');
            $_SESSION['admin_display_name'] = (string)($row['display_name'] ?? $row['username'] ?? '');
            if (empty($_SESSION['auth_issued_at'])) {
                $_SESSION['auth_issued_at'] = time();
            }
            if (empty($_SESSION['auth_last_activity'])) {
                $_SESSION['auth_last_activity'] = time();
            }
        } catch (\Throwable $e) {
            // Keep existing session id; role may remain empty and fail hasRole checks.
        }
    }
}
