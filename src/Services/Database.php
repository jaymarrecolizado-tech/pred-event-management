<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;
    private static bool $migrationsApplied = false;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'event_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        try {
            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo = $pdo;
            self::maybeAutoMigrate($pdo);
            return $pdo;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                $dsn = "mysql:host={$host};charset={$charset}";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
                $pdo->exec("USE `{$name}`");
                self::$pdo = $pdo;
                self::maybeAutoMigrate($pdo);
                return $pdo;
            }
            throw $e;
        }
    }

    public static function migrate(): void
    {
        $pdo = self::pdo();
        self::runMigrations($pdo);
        self::$migrationsApplied = true;
    }

    private static function maybeAutoMigrate(PDO $pdo): void
    {
        if (self::$migrationsApplied) {
            return;
        }
        if (self::shouldAutoMigrate()) {
            self::runMigrations($pdo);
            self::$migrationsApplied = true;
        } else {
            // Still repair id=0 attendance rows (display bug) when auto-migrate is off.
            self::repairZeroAttendanceIds($pdo);
        }
    }

    private static function shouldAutoMigrate(): bool
    {
        $value = getenv('DB_AUTO_MIGRATE');
        if ($value === false) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function runMigrations(PDO $pdo): void
    {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR;
        // 001_init
        if (!self::tableExists($pdo, 'participants') || !self::tableExists($pdo, 'attendance') || !self::tableExists($pdo, 'admins')) {
            self::executeSqlFile($pdo, $base . '001_init.sql');
        }
        // 002_action_logs
        if (!self::tableExists($pdo, 'action_logs')) {
            self::executeSqlFile($pdo, $base . '002_action_logs.sql');
        }
        // 003_events (includes index)
        if (!self::tableExists($pdo, 'events')) {
            self::executeSqlFile($pdo, $base . '003_events.sql');
        } else if (!self::indexExists($pdo, 'attendance', 'idx_attendance_pid_date')) {
            // Apply only the index addition if missing
            $pdo->exec('ALTER TABLE attendance ADD INDEX idx_attendance_pid_date (participant_id, attendance_date)');
        }
        // 004_report_templates
        if (!self::tableExists($pdo, 'report_templates')) {
            self::executeSqlFile($pdo, $base . '004_report_templates.sql');
        }
        // 005_performance_security
        if (!self::tableExists($pdo, 'rate_limits')) {
            self::executeSqlFile($pdo, $base . '005_performance_security.sql');
        } else {
            if (!self::indexExists($pdo, 'participants', 'idx_participants_agency')) {
                $pdo->exec('ALTER TABLE participants ADD INDEX idx_participants_agency (agency(100))');
            }
            if (!self::indexExists($pdo, 'attendance', 'idx_attendance_date')) {
                $pdo->exec('ALTER TABLE attendance ADD INDEX idx_attendance_date (attendance_date)');
            }
        }
        // 006_attendance_status
        if (self::tableExists($pdo, 'attendance') && !self::columnExists($pdo, 'attendance', 'status')) {
            self::executeSqlFile($pdo, $base . '006_attendance_status.sql');
        }
        // 007_rbac_roles
        if (self::tableExists($pdo, 'admins') && !self::columnExists($pdo, 'admins', 'role')) {
            self::executeSqlFile($pdo, $base . '007_rbac_roles.sql');
        }
        // 008_participant_vip
        if (self::tableExists($pdo, 'participants') && !self::columnExists($pdo, 'participants', 'is_vip')) {
            self::executeSqlFile($pdo, $base . '008_participant_vip.sql');
        }
        // 009_coa
        if (!self::tableExists($pdo, 'coa_signatories') || !self::tableExists($pdo, 'coa_send_batches') || !self::tableExists($pdo, 'coa_send_items')) {
            self::executeSqlFile($pdo, $base . '009_coa.sql');
        }
        // Repair attendance.id = 0 (PHP empty(0) hid Present + blocked signature.php)
        self::repairZeroAttendanceIds($pdo);
    }

    private static function repairZeroAttendanceIds(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'attendance')) {
            return;
        }
        $count = (int)$pdo->query('SELECT COUNT(*) FROM attendance WHERE id = 0')->fetchColumn();
        if ($count === 0) {
            return;
        }
        $max = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM attendance')->fetchColumn();
        $next = max($max + 1, 1);
        try {
            $pdo->beginTransaction();
            $rows = $pdo->query(
                'SELECT participant_id, attendance_date, time_in, signature_path, event_id, status, created_at, purpose
                 FROM attendance WHERE id = 0'
            )->fetchAll(PDO::FETCH_ASSOC);
            $pdo->exec('DELETE FROM attendance WHERE id = 0');
            $hasPurpose = self::columnExists($pdo, 'attendance', 'purpose');
            foreach ($rows as $row) {
                if ($hasPurpose) {
                    $ins = $pdo->prepare(
                        'INSERT INTO attendance (id, participant_id, attendance_date, time_in, signature_path, event_id, status, created_at, purpose)
                         VALUES (?,?,?,?,?,?,?,?,?)'
                    );
                    $ins->execute([
                        $next,
                        $row['participant_id'],
                        $row['attendance_date'],
                        $row['time_in'],
                        $row['signature_path'],
                        $row['event_id'],
                        $row['status'] ?? 'present',
                        $row['created_at'] ?? date('Y-m-d H:i:s'),
                        $row['purpose'] ?? 'standard',
                    ]);
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO attendance (id, participant_id, attendance_date, time_in, signature_path, event_id, status, created_at)
                         VALUES (?,?,?,?,?,?,?,?)'
                    );
                    $ins->execute([
                        $next,
                        $row['participant_id'],
                        $row['attendance_date'],
                        $row['time_in'],
                        $row['signature_path'],
                        $row['event_id'],
                        $row['status'] ?? 'present',
                        $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                }
                $next++;
            }
            $pdo->exec('ALTER TABLE attendance AUTO_INCREMENT = ' . $next);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('repairZeroAttendanceIds: ' . $e->getMessage());
        }
    }

    private static function executeSqlFile(PDO $pdo, string $path): void
    {
        if (!is_file($path)) return;
        $sql = file_get_contents($path);
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/',$sql))) as $stmt) {
            if ($stmt !== '') $pdo->exec($stmt);
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private static function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $stmt->execute([$table, $index]);
        return (bool)$stmt->fetchColumn();
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}