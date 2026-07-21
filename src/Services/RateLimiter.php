<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class RateLimiter
{
    public static function allow(string $key, int $max, int $windowSec): bool
    {
        $count = self::increment($key, $windowSec);
        return $count <= $max;
    }

    public static function isLocked(string $key, int $max, int $windowSec): bool
    {
        $entry = self::readEntry($key, $windowSec);
        return $entry['count'] >= $max;
    }

    public static function increment(string $key, int $windowSec): int
    {
        if (self::useDatabase()) {
            try {
                return self::incrementDb($key, $windowSec);
            } catch (\Throwable $e) {
                return self::incrementFile($key, $windowSec);
            }
        }
        return self::incrementFile($key, $windowSec);
    }

    public static function clear(string $key): void
    {
        if (self::useDatabase()) {
            try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE rate_key = ?');
                $stmt->execute([$key]);
                return;
            } catch (\Throwable $e) {
                // fall through to file store
            }
        }
        self::clearFile($key);
    }

    private static function useDatabase(): bool
    {
        return getenv('RATE_LIMITER_DRIVER') !== 'file';
    }

    private static function readEntry(string $key, int $windowSec): array
    {
        if (self::useDatabase()) {
            try {
                return self::readEntryDb($key, $windowSec);
            } catch (\Throwable $e) {
                return self::readEntryFile($key, $windowSec);
            }
        }
        return self::readEntryFile($key, $windowSec);
    }

    private static function readEntryDb(string $key, int $windowSec): array
    {
        $pdo = Database::pdo();
        $now = time();
        $stmt = $pdo->prepare('SELECT count, window_start FROM rate_limits WHERE rate_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($now - (int)$row['window_start']) >= $windowSec) {
            return ['count' => 0, 'start' => $now];
        }
        return ['count' => (int)$row['count'], 'start' => (int)$row['window_start']];
    }

    private static function incrementDb(string $key, int $windowSec): int
    {
        $pdo = Database::pdo();
        $now = time();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT count, window_start FROM rate_limits WHERE rate_key = ? FOR UPDATE');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || ($now - (int)$row['window_start']) >= $windowSec) {
                $count = 1;
                $start = $now;
            } else {
                $count = (int)$row['count'] + 1;
                $start = (int)$row['window_start'];
            }

            $upsert = $pdo->prepare(
                'INSERT INTO rate_limits (rate_key, count, window_start) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE count = VALUES(count), window_start = VALUES(window_start)'
            );
            $upsert->execute([$key, $count, $start]);
            $pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function readEntryFile(string $key, int $windowSec): array
    {
        $data = self::loadFileData();
        $now = time();
        $entry = $data[$key] ?? ['count' => 0, 'start' => $now];
        if ($now - (int)($entry['start'] ?? 0) >= $windowSec) {
            return ['count' => 0, 'start' => $now];
        }
        return ['count' => (int)($entry['count'] ?? 0), 'start' => (int)($entry['start'] ?? $now)];
    }

    private static function incrementFile(string $key, int $windowSec): int
    {
        $dir = self::fileDir();
        $file = $dir . DIRECTORY_SEPARATOR . 'ratelimit.json';
        $now = time();

        $handle = fopen($file, 'c+');
        if (!$handle) {
            return 0;
        }

        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        $data = $raw ? (json_decode($raw, true) ?: []) : [];
        $data = is_array($data) ? $data : [];

        foreach ($data as $k => $entry) {
            if ($now - (int)($entry['start'] ?? 0) >= $windowSec) {
                unset($data[$k]);
            }
        }

        $entry = $data[$key] ?? ['count' => 0, 'start' => $now];
        if ($now - (int)$entry['start'] >= $windowSec) {
            $entry = ['count' => 0, 'start' => $now];
        }

        $entry['count'] = (int)$entry['count'] + 1;
        $data[$key] = $entry;

        $payload = '{}';
        try {
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $payload = '{}';
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $payload);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return (int)$entry['count'];
    }

    private static function clearFile(string $key): void
    {
        $dir = self::fileDir();
        $file = $dir . DIRECTORY_SEPARATOR . 'ratelimit.json';
        if (!is_file($file)) {
            return;
        }

        $handle = fopen($file, 'c+');
        if (!$handle) {
            return;
        }

        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        $data = $raw ? (json_decode($raw, true) ?: []) : [];
        if (is_array($data) && isset($data[$key])) {
            unset($data[$key]);
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $payload);
            fflush($handle);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private static function loadFileData(): array
    {
        $file = self::fileDir() . DIRECTORY_SEPARATOR . 'ratelimit.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : [];
        return is_array($data) ? $data : [];
    }

    private static function fileDir(): string
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'runtime';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
