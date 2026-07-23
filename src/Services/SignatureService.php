<?php
declare(strict_types=1);

namespace App\Services;

class SignatureService
{
    public static function storageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'signatures';
    }

    /**
     * Save a base64 PNG signature and return a portable relative path (YYYY/file.png).
     */
    public static function saveBase64(string $uuid, string $base64): string
    {
        $parts = explode(',', $base64, 2);
        $data = count($parts) === 2 ? $parts[1] : $parts[0];
        $bin = base64_decode($data, true);
        if ($bin === false || strlen($bin) > 5_000_000) {
            throw new \RuntimeException('Invalid signature');
        }

        $year = date('Y');
        $root = self::storageRoot();
        $dir = $root . DIRECTORY_SEPARATOR . $year;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Signature storage is not writable');
        }

        $name = $uuid . '_' . time() . '.png';
        $absolute = $dir . DIRECTORY_SEPARATOR . $name;
        if (@file_put_contents($absolute, $bin) === false) {
            throw new \RuntimeException('Failed to save signature');
        }

        // Portable relative path (survives deploy / host moves).
        return $year . '/' . $name;
    }

    /**
     * Resolve a DB signature_path (relative, absolute, or legacy absolute) to a readable file.
     * Does not modify the database.
     */
    public static function resolvePath(?string $stored): ?string
    {
        $stored = trim((string)$stored);
        if ($stored === '') {
            return null;
        }

        // Already an absolute path that exists on this host.
        if (is_file($stored) && is_readable($stored)) {
            return $stored;
        }

        $normalized = str_replace('\\', '/', $stored);
        $relative = null;

        // .../storage/signatures/2026/file.png (any absolute/legacy deploy path)
        if (preg_match('#(?:^|/)storage/signatures/(.+)$#i', $normalized, $m)) {
            $relative = ltrim($m[1], '/');
        } elseif (preg_match('#^\d{4}/[^/]+\.(png|jpe?g|gif|webp)$#i', $normalized)) {
            $relative = $normalized;
        } elseif (preg_match('#^[^/]+\.(png|jpe?g|gif|webp)$#i', $normalized)) {
            // Bare filename — try current year first, then search.
            $relative = date('Y') . '/' . $normalized;
        }

        if ($relative !== null) {
            $candidate = self::storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        // Last resort: find by basename under storage/signatures/* (survives moved hosts / wrong year).
        $base = basename($normalized);
        if ($base !== '' && preg_match('#^.+\.(png|jpe?g|gif|webp)$#i', $base)) {
            $found = self::findByBasename($base);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function findByBasename(string $basename): ?string
    {
        $root = self::storageRoot();
        if (!is_dir($root)) {
            return null;
        }

        // Prefer current and previous year directories first.
        $years = array_unique([date('Y'), (string)((int)date('Y') - 1)]);
        foreach ($years as $year) {
            $candidate = $root . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $basename;
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        // Scan year subfolders only (avoid deep recursion cost).
        $dirs = @scandir($root) ?: [];
        foreach ($dirs as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $years, true)) {
                continue;
            }
            $dir = $root . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $candidate = $dir . DIRECTORY_SEPARATOR . $basename;
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
