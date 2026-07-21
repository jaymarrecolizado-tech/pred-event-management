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
     */
    public static function resolvePath(?string $stored): ?string
    {
        $stored = trim((string)$stored);
        if ($stored === '') {
            return null;
        }

        if (is_file($stored)) {
            return $stored;
        }

        $normalized = str_replace('\\', '/', $stored);
        $relative = null;

        if (preg_match('#(?:^|/)storage/signatures/(.+)$#i', $normalized, $m)) {
            $relative = $m[1];
        } elseif (preg_match('#^\d{4}/.+\.png$#i', $normalized)) {
            $relative = $normalized;
        } elseif (strpos($normalized, '/') === false && preg_match('#^.+\.png$#i', $normalized)) {
            $relative = date('Y') . '/' . $normalized;
        }

        if ($relative === null) {
            return null;
        }

        $candidate = self::storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        return is_file($candidate) ? $candidate : null;
    }
}
