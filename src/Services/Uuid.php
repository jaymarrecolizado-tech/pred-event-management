<?php
declare(strict_types=1);

namespace App\Services;

class Uuid
{
    public static function v4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        $hex = bin2hex($d);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20,12)
        );
    }
}