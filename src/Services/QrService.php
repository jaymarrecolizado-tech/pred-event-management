<?php
declare(strict_types=1);

namespace App\Services;

class QrService
{
    private static bool $remoteFailed = false;

    public static function generate(string $payload, string $uuid): string
    {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'qrcodes';
        $shard = substr($uuid, 0, 2);
        $dir = $base . DIRECTORY_SEPARATOR . $shard;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $path = $dir . DIRECTORY_SEPARATOR . $uuid . '.png';
        $external = getenv('QR_EXTERNAL') === 'true';
        if (!$external && class_exists('Endroid\\QrCode\\QrCode')) {
            $qr = new \Endroid\QrCode\QrCode($payload);
            $qr->setSize(300);
            $qr->writeFile($path);
            return $path;
        }
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($payload);
        if (self::remoteEnabled() && !self::$remoteFailed) {
            $img = self::fetchRemote($url);
            if ($img !== null) {
                file_put_contents($path, $img);
                return $path;
            }
            self::$remoteFailed = true;
        }
        self::generateFallback($payload, $path);
        return $path;
    }

    private static function fetchRemote(string $url): ?string
    {
        $strict = getenv('QR_STRICT_SSL') === 'true';
        $context = $strict ? null : self::insecureContext();
        $data = @file_get_contents($url, false, $context);
        if ($data !== false) {
            return $data;
        }
        if ($strict) {
            $data = @file_get_contents($url, false, self::insecureContext());
            if ($data !== false) {
                return $data;
            }
        }
        return null;
    }

    private static function remoteEnabled(): bool
    {
        $env = getenv('QR_ENABLE_REMOTE');
        if ($env === false) {
            return true;
        }
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    private static function insecureContext()
    {
        return stream_context_create([
            'http' => [
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
    }

    private static function generateFallback(string $payload, string $path): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            file_put_contents($path, '');
            return;
        }
        $img = imagecreatetruecolor(300, 300);
        $bg = imagecolorallocate($img, 247, 247, 247);
        $fg = imagecolorallocate($img, 60, 60, 60);
        imagefilledrectangle($img, 0, 0, 300, 300, $bg);
        imagestring($img, 5, 20, 130, 'QR UNAVAILABLE', $fg);
        imagestring($img, 3, 20, 160, substr($payload, 0, 24), $fg);
        imagepng($img, $path);
        imagedestroy($img);
    }
}
