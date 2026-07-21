<?php
declare(strict_types=1);

namespace App\Services;

final class ParticipantValidator
{
    /**
     * @return array{data: array<string,string|null>, errors: array<string,string>}
     */
    public static function validateForRegistration(array $input): array
    {
        $data = [
            'first_name' => self::clean($input['first_name'] ?? ''),
            'middle_name' => self::nullable(self::clean($input['middle_name'] ?? '')),
            'last_name' => self::clean($input['last_name'] ?? ''),
            'email' => self::nullable(self::clean($input['email'] ?? '')),
            'office_email' => null,
            'agency' => self::nullable(self::clean($input['agency'] ?? '')),
            'designation' => self::nullable(self::clean($input['designation'] ?? '')),
            'sector' => self::clean($input['sector'] ?? ''),
            'nickname' => self::nullable(self::clean($input['nickname'] ?? '')),
            'sex' => self::nullable(self::clean($input['sex'] ?? '')),
            'contact_no' => self::normalizePhMobile($input['contact_no'] ?? ''),
        ];

        $errors = [];

        self::requireField($data['first_name'], 'first_name', 'First name is required', $errors);
        self::requireField($data['last_name'], 'last_name', 'Last name is required', $errors);
        self::requireField($data['sector'], 'sector', 'Sector is required', $errors);
        self::requireField($data['agency'], 'agency', 'Agency is required', $errors);

        self::maxLength($data['first_name'], 80, 'first_name', $errors);
        self::maxLength($data['last_name'], 80, 'last_name', $errors);
        self::maxLength($data['agency'], 160, 'agency', $errors);
        self::maxLength($data['designation'], 160, 'designation', $errors);
        self::maxLength($data['nickname'], 80, 'nickname', $errors);

        if (!$data['email']) {
            $errors['email'] = 'Email is required so we can send your QR code';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid';
        }

        if ($data['contact_no'] === null) {
            $errors['contact_no'] = 'Mobile number is required (09XXXXXXXXX or +639XXXXXXXXX)';
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * Accept 09XXXXXXXXX, +639XXXXXXXXX, or 639XXXXXXXXX and store as +639XXXXXXXXX.
     */
    public static function normalizePhMobile(string $raw): ?string
    {
        $value = preg_replace('/[\s\-().]/', '', trim($raw)) ?? '';
        if ($value === '') {
            return null;
        }

        if (preg_match('/^09\d{9}$/', $value) === 1) {
            return '+63' . substr($value, 1);
        }
        if (preg_match('/^\+639\d{9}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^639\d{9}$/', $value) === 1) {
            return '+' . $value;
        }

        return null;
    }

    private static function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        return $value;
    }

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string,string> $errors
     */
    private static function requireField(?string $value, string $key, string $message, array &$errors): void
    {
        if ($value === null || $value === '') {
            $errors[$key] = $message;
        }
    }

    /**
     * @param array<string,string> $errors
     */
    private static function maxLength(?string $value, int $max, string $key, array &$errors): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            $errors[$key] = "Must be {$max} characters or less";
        }
    }
}
