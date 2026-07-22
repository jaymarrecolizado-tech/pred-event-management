<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\Logger;

class AdminUsersController
{
    private const MIN_PASSWORD_LEN = 10;

    public function list(): void
    {
        if (!AuthService::isAdmin()) {
            AuthService::deny('GET');
            return;
        }
        $pdo = Database::pdo();
        try {
            $rows = $pdo->query(
                'SELECT id, username, display_name, email, role, is_active, last_login_at, created_at
                 FROM admins ORDER BY id ASC'
            )->fetchAll();
        } catch (\Throwable $e) {
            // Older schema fallback — never break the Users page.
            error_log('admin_users list fallback: ' . $e->getMessage());
            $rows = $pdo->query(
                'SELECT id, username, email, created_at FROM admins ORDER BY id ASC'
            )->fetchAll() ?: [];
            foreach ($rows as &$row) {
                $row['display_name'] = $row['display_name'] ?? $row['username'] ?? '';
                $row['role'] = $row['role'] ?? AuthService::ROLE_ADMIN;
                $row['is_active'] = $row['is_active'] ?? 1;
                $row['last_login_at'] = $row['last_login_at'] ?? null;
            }
            unset($row);
        }
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $roles = [
            AuthService::ROLE_ADMIN => 'Admin',
            AuthService::ROLE_CHECKER => 'Attendance Checker',
            AuthService::ROLE_SEO => 'SEO Viewer',
        ];
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_users.php';
    }

    public function create(): void
    {
        if (!AuthService::isAdmin()) {
            AuthService::deny('POST');
            return;
        }
        if (!$this->csrfOk()) {
            return;
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? AuthService::ROLE_CHECKER));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || !$this->validRole($role)) {
            $this->flash('danger', 'Username and a valid role are required.');
            $this->redirectUsers();
        }
        if (strlen($password) < self::MIN_PASSWORD_LEN) {
            $this->flash('danger', 'Password must be at least ' . self::MIN_PASSWORD_LEN . ' characters.');
            $this->redirectUsers();
        }
        if ($this->isWeakPassword($password)) {
            $this->flash('danger', 'Choose a stronger password (avoid common defaults).');
            $this->redirectUsers();
        }

        try {
            $pdo = Database::pdo();
            $exists = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
            $exists->execute([$username]);
            if ($exists->fetch()) {
                $this->flash('danger', 'Username already exists.');
                $this->redirectUsers();
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $this->insertAdmin($pdo, [
                'username' => $username,
                'display_name' => $displayName !== '' ? $displayName : null,
                'password_hash' => $hash,
                'email' => $email !== '' ? $email : null,
                'role' => $role,
            ]);

            Logger::log(AuthService::id(), 'user_created', [
                'username' => $username,
                'role' => $role,
                'actor_role' => AuthService::role(),
            ]);
            $this->flash('success', 'User created.');
        } catch (\Throwable $e) {
            error_log('admin_users_create: ' . $e->getMessage());
            $this->flash('danger', 'Could not create account. Please try again or contact support.');
        }
        $this->redirectUsers();
    }

    public function update(): void
    {
        if (!AuthService::isAdmin()) {
            AuthService::deny('POST');
            return;
        }
        if (!$this->csrfOk()) {
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $action = trim((string)($_POST['action'] ?? ''));
        if ($id <= 0 || $action === '') {
            $this->flash('danger', 'Invalid request.');
            header('Location: ?r=admin_users');
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, username, role, is_active FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            $this->flash('danger', 'User not found.');
            header('Location: ?r=admin_users');
            return;
        }

        switch ($action) {
            case 'deactivate':
                if ((string)$user['role'] === AuthService::ROLE_ADMIN && $this->activeAdminCount($pdo) <= 1) {
                    $this->flash('danger', 'Cannot deactivate the last active admin.');
                    break;
                }
                $pdo->prepare('UPDATE admins SET is_active = 0 WHERE id = ?')->execute([$id]);
                Logger::log(AuthService::id(), 'user_deactivated', ['target_id' => $id, 'username' => $user['username']]);
                $this->flash('success', 'User deactivated.');
                break;

            case 'activate':
                $pdo->prepare('UPDATE admins SET is_active = 1 WHERE id = ?')->execute([$id]);
                Logger::log(AuthService::id(), 'user_activated', ['target_id' => $id, 'username' => $user['username']]);
                $this->flash('success', 'User activated.');
                break;

            case 'set_role':
                $role = trim((string)($_POST['role'] ?? ''));
                if (!$this->validRole($role)) {
                    $this->flash('danger', 'Invalid role.');
                    break;
                }
                if (
                    (string)$user['role'] === AuthService::ROLE_ADMIN
                    && $role !== AuthService::ROLE_ADMIN
                    && $this->activeAdminCount($pdo) <= 1
                    && (int)$user['is_active'] === 1
                ) {
                    $this->flash('danger', 'Cannot demote the last active admin.');
                    break;
                }
                $pdo->prepare('UPDATE admins SET role = ? WHERE id = ?')->execute([$role, $id]);
                Logger::log(AuthService::id(), 'user_role_changed', [
                    'target_id' => $id,
                    'from' => $user['role'],
                    'to' => $role,
                ]);
                $this->flash('success', 'Role updated.');
                break;

            case 'reset_password':
                $password = (string)($_POST['password'] ?? '');
                if (strlen($password) < self::MIN_PASSWORD_LEN) {
                    $this->flash('danger', 'Password must be at least ' . self::MIN_PASSWORD_LEN . ' characters.');
                    break;
                }
                if ($this->isWeakPassword($password)) {
                    $this->flash('danger', 'Choose a stronger password (avoid common defaults).');
                    break;
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
                Logger::log(AuthService::id(), 'user_password_reset', ['target_id' => $id, 'username' => $user['username']]);
                $this->flash('success', 'Password reset.');
                break;

            case 'edit':
                $displayName = trim((string)($_POST['display_name'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $role = trim((string)($_POST['role'] ?? (string)$user['role']));

                if (!$this->validRole($role)) {
                    $this->flash('danger', 'Invalid role.');
                    break;
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->flash('danger', 'Enter a valid email address.');
                    break;
                }
                if (
                    (string)$user['role'] === AuthService::ROLE_ADMIN
                    && $role !== AuthService::ROLE_ADMIN
                    && $this->activeAdminCount($pdo) <= 1
                    && (int)$user['is_active'] === 1
                ) {
                    $this->flash('danger', 'Cannot demote the last active admin.');
                    break;
                }

                $pdo->prepare('UPDATE admins SET display_name = ?, email = ?, role = ? WHERE id = ?')->execute([
                    $displayName !== '' ? $displayName : null,
                    $email !== '' ? $email : null,
                    $role,
                    $id,
                ]);
                Logger::log(AuthService::id(), 'user_edited', [
                    'target_id' => $id,
                    'username' => $user['username'],
                    'role' => $role,
                    'actor_role' => AuthService::role(),
                ]);
                $this->flash('success', 'Account updated.');
                break;

            default:
                $this->flash('danger', 'Unknown action.');
        }

        header('Location: ?r=admin_users');
    }

    private function csrfOk(): bool
    {
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) {
            http_response_code(400);
            echo 'Invalid CSRF';
            return false;
        }
        return true;
    }

    private function validRole(string $role): bool
    {
        return in_array($role, [
            AuthService::ROLE_ADMIN,
            AuthService::ROLE_CHECKER,
            AuthService::ROLE_SEO,
        ], true);
    }

    private function activeAdminCount(\PDO $pdo): int
    {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM admins WHERE role = 'admin' AND is_active = 1"
        )->fetchColumn();
    }

    private function isWeakPassword(string $password): bool
    {
        $weak = ['password', 'password123', 'admin123', '1234567890', 'changeme123'];
        return in_array(strtolower($password), $weak, true);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private function redirectUsers(): void
    {
        if (!headers_sent()) {
            header('Location: ?r=admin_users');
        }
        exit;
    }

    /**
     * Insert admin using available columns (safe if an older schema is still catching up).
     *
     * @param array{username:string,display_name:?string,password_hash:string,email:?string,role:string} $data
     */
    private function insertAdmin(\PDO $pdo, array $data): void
    {
        $cols = $this->adminColumns($pdo);
        $fields = ['username', 'password_hash'];
        $values = [$data['username'], $data['password_hash']];

        if (isset($cols['email'])) {
            $fields[] = 'email';
            $values[] = $data['email'];
        }
        if (isset($cols['display_name'])) {
            $fields[] = 'display_name';
            $values[] = $data['display_name'];
        }
        if (isset($cols['role'])) {
            $fields[] = 'role';
            $values[] = $data['role'];
        }
        if (isset($cols['is_active'])) {
            $fields[] = 'is_active';
            $values[] = 1;
        }

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = 'INSERT INTO admins (' . implode(',', $fields) . ') VALUES (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * @return array<string,true>
     */
    private function adminColumns(\PDO $pdo): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $cache = [];
        $rows = $pdo->query('SHOW COLUMNS FROM admins')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $name = (string)($row['Field'] ?? '');
            if ($name !== '') {
                $cache[$name] = true;
            }
        }
        return $cache;
    }
}
