<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class CertificateOfAppearanceService
{
    public static function defaultParticulars(): array
    {
        return [
            'lodging' => 'not_provided',
            'meals' => [
                'status' => 'not_provided',
                'breakfast' => false,
                'lunch' => false,
                'dinner' => false,
            ],
            'vehicle' => 'not_provided',
        ];
    }

    public static function normalizeParticulars(array $input): array
    {
        $base = self::defaultParticulars();
        $lodging = (string)($input['lodging'] ?? $base['lodging']);
        if (!in_array($lodging, ['not_provided', 'provided'], true)) {
            $lodging = 'not_provided';
        }
        $vehicle = (string)($input['vehicle'] ?? $base['vehicle']);
        if (!in_array($vehicle, ['not_provided', 'provided'], true)) {
            $vehicle = 'not_provided';
        }
        $mealsIn = is_array($input['meals'] ?? null) ? $input['meals'] : [];
        $mealsStatus = (string)($mealsIn['status'] ?? '');
        if (!in_array($mealsStatus, ['not_provided', 'provided'], true)) {
            // Legacy: any meal checkbox meant meals were provided with lodging.
            $mealsStatus = (!empty($mealsIn['breakfast']) || !empty($mealsIn['lunch']) || !empty($mealsIn['dinner']))
                ? 'provided'
                : 'not_provided';
        }
        $breakfast = $mealsStatus === 'provided' && !empty($mealsIn['breakfast']);
        $lunch = $mealsStatus === 'provided' && !empty($mealsIn['lunch']);
        $dinner = $mealsStatus === 'provided' && !empty($mealsIn['dinner']);
        return [
            'lodging' => $lodging,
            'meals' => [
                'status' => $mealsStatus,
                'breakfast' => $breakfast,
                'lunch' => $lunch,
                'dinner' => $dinner,
            ],
            'vehicle' => $vehicle,
        ];
    }

    public static function mergeParticulars(array $defaults, ?array $override): array
    {
        $defaults = self::normalizeParticulars($defaults);
        if ($override === null || $override === []) {
            return $defaults;
        }
        $merged = array_replace_recursive($defaults, $override);
        // Override may set meal checkboxes without an explicit status (legacy / partial override).
        if (isset($override['meals']) && is_array($override['meals']) && !array_key_exists('status', $override['meals'])) {
            $m = $override['meals'];
            if (!empty($m['breakfast']) || !empty($m['lunch']) || !empty($m['dinner'])) {
                $merged['meals']['status'] = 'provided';
            }
        }
        return self::normalizeParticulars($merged);
    }

    public static function resolveEmail(array $participant): ?string
    {
        $email = trim((string)($participant['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        $office = trim((string)($participant['office_email'] ?? ''));
        if ($office !== '' && filter_var($office, FILTER_VALIDATE_EMAIL)) {
            return $office;
        }
        return null;
    }

    public static function fullName(array $participant): string
    {
        $parts = array_filter([
            trim((string)($participant['first_name'] ?? '')),
            trim((string)($participant['middle_name'] ?? '')),
            trim((string)($participant['last_name'] ?? '')),
        ], static fn($v) => $v !== '');
        return implode(' ', $parts);
    }

    /**
     * Official CoA style: UPPERCASE with middle initial (e.g. MARICAR C. PECSON).
     */
    public static function certificateName(array $participant): string
    {
        $first = trim((string)($participant['first_name'] ?? ''));
        $middle = trim((string)($participant['middle_name'] ?? ''));
        $last = trim((string)($participant['last_name'] ?? ''));
        $name = $first;
        if ($middle !== '') {
            $initial = mb_strtoupper(mb_substr($middle, 0, 1));
            $mid = mb_strlen($middle) <= 2 ? mb_strtoupper(rtrim($middle, '.')) . '.' : ($initial . '.');
            $name .= ' ' . $mid;
        }
        if ($last !== '') {
            $name .= ' ' . $last;
        }
        return mb_strtoupper(trim($name));
    }

    /**
     * Present + signed participants in optional event/date range.
     *
     * @return list<array<string,mixed>>
     */
    public static function eligibleParticipants(PDO $pdo, ?int $eventId, ?string $dateFrom, ?string $dateTo): array
    {
        $where = [
            "COALESCE(a.signature_path, '') <> ''",
            "COALESCE(a.status, 'present') = 'present'",
        ];
        $bind = [];
        if ($eventId !== null && $eventId > 0) {
            $where[] = '(a.event_id = ? OR a.event_id IS NULL)';
            $bind[] = $eventId;
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $where[] = 'a.attendance_date >= ?';
            $bind[] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where[] = 'a.attendance_date <= ?';
            $bind[] = $dateTo;
        }
        $sqlWhere = implode(' AND ', $where);
        $sql = "SELECT
                    p.id, p.uuid, p.first_name, p.middle_name, p.last_name,
                    p.agency, p.designation, p.email, p.office_email,
                    GROUP_CONCAT(DISTINCT a.attendance_date ORDER BY a.attendance_date SEPARATOR ', ') AS attendance_dates,
                    MIN(a.attendance_date) AS first_date,
                    MAX(a.attendance_date) AS last_date
                FROM participants p
                INNER JOIN attendance a ON a.participant_id = p.id
                WHERE {$sqlWhere}
                GROUP BY p.id, p.uuid, p.first_name, p.middle_name, p.last_name,
                         p.agency, p.designation, p.email, p.office_email
                ORDER BY p.last_name ASC, p.first_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll() ?: [];
    }

    public static function emailSubject(string $inclusiveDates): string
    {
        return 'Certificate of Appearance' . ($inclusiveDates !== '' ? ' — ' . $inclusiveDates : '');
    }

    public static function emailBody(string $fullName, string $inclusiveDates): string
    {
        $safeName = htmlspecialchars($fullName, ENT_QUOTES);
        $safeDates = htmlspecialchars($inclusiveDates, ENT_QUOTES);
        return '<p>Dear ' . $safeName . ',</p>'
            . '<p>Please find attached your Certificate of Appearance'
            . ($safeDates !== '' ? ' for <strong>' . $safeDates . '</strong>' : '')
            . '.</p>'
            . '<p>This certification is issued upon request to attest to the fact and duration of your appearance.</p>'
            . '<p>Thank you.</p>';
    }

    public static function officeHeader(): array
    {
        return [
            'name' => getenv('COA_OFFICE_NAME') ?: 'DICT - Region II',
            'website' => getenv('COA_OFFICE_WEBSITE') ?: 'https://www.dict.gov.ph',
            'address' => getenv('COA_OFFICE_ADDRESS') ?: '02 Bagay Road, San Gabriel Village, Tuguegarao City, Cagayan 3500',
            'email' => getenv('COA_OFFICE_EMAIL') ?: 'region2@dict.gov.ph',
            'phone' => getenv('COA_OFFICE_PHONE') ?: '(078) 8251624',
        ];
    }

    /**
     * @param array<string,mixed> $participant
     * @param array{venue:string,purpose:string,inclusive_dates:string,issue_date:string} $activity
     * @param array<string,mixed> $particulars
     * @param array{full_name:string,title:string,signature_path:string} $signatory
     */
    public static function renderPdf(array $participant, array $activity, array $particulars, array $signatory): string
    {
        return CoaPdfRenderer::render($participant, $activity, $particulars, $signatory);
    }

    public static function formatIssueDate(string $ymd): string
    {
        $ts = strtotime($ymd);
        if ($ts === false) {
            return $ymd;
        }
        $day = (int)date('j', $ts);
        $suffix = 'th';
        if (!in_array($day % 100, [11, 12, 13], true)) {
            $suffix = match ($day % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };
        }
        return $day . $suffix . ' day of ' . date('F Y', $ts);
    }
}
