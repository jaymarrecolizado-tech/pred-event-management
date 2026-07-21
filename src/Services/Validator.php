<?php
declare(strict_types=1);

namespace App\Services;

class Validator
{
    public static function email(?string $email): bool
    {
        return $email === null || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function required(string $value): bool
    {
        return trim($value) !== '';
    }
}