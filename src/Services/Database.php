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

        // Always create/repair core schema when missing (empty Hostinger DBs).
        // Full migrate also runs when DB_AUTO_MIGRATE=true.
        $needsCore = !self::tableExists($pdo, 'admins')
            || !self::tableExists($pdo, 'participants')
            || !self::tableExists($pdo, 'action_logs')
            || (self::tableExists($pdo, 'admins') && !self::columnExists($pdo, 'admins', 'role'));

        if (self::shouldAutoMigrate() || $needsCore) {
            self::runMigrations($pdo);
            self::$migrationsApplied = true;
            return;
        }

        self::ensureRegistrationStatusColumn($pdo);
        self::repairIdentityColumns($pdo);
        self::$migrationsApplied = true;
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
        // 010 registration status (Pre-reg vs Registered)
        self::ensureRegistrationStatusColumn($pdo);

        self::repairIdentityColumns($pdo);
    }

    private static function ensureRegistrationStatusColumn(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'participants')) {
            return;
        }
        if (self::columnExists($pdo, 'participants', 'registration_status')) {
            return;
        }
        try {
            $pdo->exec(
                "ALTER TABLE participants
                 ADD COLUMN registration_status VARCHAR(20) NOT NULL DEFAULT 'Registered' AFTER contact_no"
            );
            if (!self::indexExists($pdo, 'participants', 'idx_participants_reg_status')) {
                $pdo->exec('ALTER TABLE participants ADD INDEX idx_participants_reg_status (registration_status)');
            }
        } catch (\Throwable $e) {
            error_log('ensureRegistrationStatusColumn: ' . $e->getMessage());
        }
    }

    /**
     * Restore missing PRIMARY KEY / AUTO_INCREMENT and reassign id=0 rows.
     * Without this, new participants get id=0 and manual attendance fails with missing_participant.
     */
    private static function repairIdentityColumns(PDO $pdo): void
    {
        self::ensureAutoIncrementPrimaryKey($pdo, 'participants', 'BIGINT UNSIGNED');
        self::ensureAutoIncrementPrimaryKey($pdo, 'attendance', 'BIGINT UNSIGNED');
        self::ensureAutoIncrementPrimaryKey($pdo, 'events', 'INT');
        self::ensureParticipantsUuidUnique($pdo);
    }

    private static function ensureAutoIncrementPrimaryKey(PDO $pdo, string $table, string $idType): void
    {
        if (!self::tableExists($pdo, $table)) {
            return;
        }

        try {
            self::reassignZeroIds($pdo, $table);

            $meta = $pdo->prepare(
                'SELECT COLUMN_TYPE, EXTRA, COLUMN_KEY
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = \'id\''
            );
            $meta->execute([$table]);
            $col = $meta->fetch(PDO::FETCH_ASSOC);
            if (!$col) {
                return;
            }

            $hasAuto = stripos((string)($col['EXTRA'] ?? ''), 'auto_increment') !== false;
            $hasPk = strtoupper((string)($col['COLUMN_KEY'] ?? '')) === 'PRI'
                || self::indexExists($pdo, $table, 'PRIMARY');

            if ($hasAuto && $hasPk) {
                return;
            }

            if (!$hasPk) {
                $pdo->exec("ALTER TABLE `{$table}` MODIFY `id` {$idType} NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
            } else {
                $pdo->exec("ALTER TABLE `{$table}` MODIFY `id` {$idType} NOT NULL AUTO_INCREMENT");
            }

            $max = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM `{$table}`")->fetchColumn();
            $pdo->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . max($max + 1, 1));
        } catch (\Throwable $e) {
            error_log("ensureAutoIncrementPrimaryKey({$table}): " . $e->getMessage());
        }
    }

    private static function reassignZeroIds(PDO $pdo, string $table): void
    {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE id = 0")->fetchColumn();
        if ($count === 0) {
            return;
        }

        $max = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM `{$table}`")->fetchColumn();
        $next = max($max + 1, 1);

        // MyISAM: update in place (no reliable transactions).
        $rows = $pdo->query("SELECT * FROM `{$table}` WHERE id = 0")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $oldId = 0;
            $upd = $pdo->prepare("UPDATE `{$table}` SET id = ? WHERE id = 0 LIMIT 1");
            $upd->execute([$next]);

            if ($table === 'participants') {
                $link = $pdo->prepare('UPDATE attendance SET participant_id = ? WHERE participant_id = ?');
                $link->execute([$next, $oldId]);
            }

            $next++;
        }
    }

    private static function ensureParticipantsUuidUnique(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'participants') || !self::columnExists($pdo, 'participants', 'uuid')) {
            return;
        }
        if (self::indexExists($pdo, 'participants', 'uuid') || self::indexExists($pdo, 'participants', 'uq_participants_uuid')) {
            return;
        }
        try {
            $pdo->exec('ALTER TABLE participants ADD UNIQUE KEY uq_participants_uuid (uuid)');
        } catch (\Throwable $e) {
            error_log('ensureParticipantsUuidUnique: ' . $e->getMessage());
        }
    }

    /** @deprecated kept for scripts that call it directly */
    private static function repairZeroAttendanceIds(PDO $pdo): void
    {
        self::ensureAutoIncrementPrimaryKey($pdo, 'attendance', 'BIGINT UNSIGNED');
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