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
        $rows = $pdo->query(
            'SELECT id, username, display_name, email, role, is_active, last_login_at, created_at
             FROM admins ORDER BY id ASC'
        )->fetchAll();
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
            header('Location: ?r=admin_users');
            return;
        }
        if (strlen($password) < self::MIN_PASSWORD_LEN) {
            $this->flash('danger', 'Password must be at least ' . self::MIN_PASSWORD_LEN . ' characters.');
            header('Location: ?r=admin_users');
            return;
        }
        if ($this->isWeakPassword($password)) {
            $this->flash('danger', 'Choose a stronger password (avoid common defaults).');
            header('Location: ?r=admin_users');
            return;
        }

        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $exists->execute([$username]);
        if ($exists->fetch()) {
            $this->flash('danger', 'Username already exists.');
            header('Location: ?r=admin_users');
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO admins (username, display_name, password_hash, email, role, is_active)
             VALUES (?,?,?,?,?,1)'
        );
        $stmt->execute([
            $username,
            $displayName !== '' ? $displayName : null,
            $hash,
            $email !== '' ? $email : null,
            $role,
        ]);

        Logger::log(AuthService::id(), 'user_created', [
            'username' => $username,
            'role' => $role,
            'actor_role' => AuthService::role(),
        ]);
        $this->flash('success', 'User created.');
        header('Location: ?r=admin_users');
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
}
