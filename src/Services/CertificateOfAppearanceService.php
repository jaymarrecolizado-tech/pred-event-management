<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use TCPDF;

class CertificateOfAppearanceService
{
    public static function defaultParticulars(): array
    {
        return [
            'lodging' => 'not_provided',
            'meals' => [
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
        return [
            'lodging' => $lodging,
            'meals' => [
                'breakfast' => !empty($mealsIn['breakfast']),
                'lunch' => !empty($mealsIn['lunch']),
                'dinner' => !empty($mealsIn['dinner']),
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
        return self::normalizeParticulars(array_replace_recursive($defaults, $override));
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
        if (!class_exists(TCPDF::class) && is_file(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        }
        if (!class_exists(TCPDF::class)) {
            throw new \RuntimeException('TCPDF is not available');
        }

        $particulars = self::normalizeParticulars($particulars);
        $fullName = self::fullName($participant);
        $agency = trim((string)($participant['agency'] ?? '')) ?: '________________';
        $venue = trim((string)($activity['venue'] ?? ''));
        $purpose = trim((string)($activity['purpose'] ?? ''));
        $inclusive = trim((string)($activity['inclusive_dates'] ?? ''));
        $issueDate = trim((string)($activity['issue_date'] ?? date('Y-m-d')));
        $issueLabel = self::formatIssueDate($issueDate);
        $office = self::officeHeader();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Event Management');
        $pdf->SetAuthor($office['name']);
        $pdf->SetTitle('Certificate of Appearance');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(18, 16, 18);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 5, $office['name'] . '  ' . $office['website'], 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 4, $office['address'] . "\n" . $office['email'] . '  ' . $office['phone'], 0, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'CERTIFICATE OF APPEARANCE', 0, 1, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, 'TO WHOM IT MAY CONCERN:', 0, 1, 'L');
        $pdf->Ln(2);

        $body = 'This is to certify that <b>' . htmlspecialchars($fullName, ENT_QUOTES)
            . '</b> of <b>' . htmlspecialchars($agency, ENT_QUOTES)
            . '</b> appeared at <b>' . htmlspecialchars($venue, ENT_QUOTES)
            . '</b> on <b>' . htmlspecialchars($inclusive, ENT_QUOTES)
            . '</b> for the purpose of ' . htmlspecialchars($purpose, ENT_QUOTES) . '.';
        $pdf->writeHTML('<p style="text-align:justify; line-height:1.5;">' . $body . '</p>', true, false, true, false, '');

        $pdf->writeHTML(
            '<p style="text-align:justify; line-height:1.5;">It is further certified that during the stay of the above-mentioned name, this office:</p>',
            true,
            false,
            true,
            false,
            ''
        );

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(120, 7, 'Particulars', 1, 0, 'C');
        $pdf->Cell(0, 7, 'Inclusive Dates', 1, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);

        $lodgingLines = [];
        if ($particulars['lodging'] === 'not_provided') {
            $lodgingLines[] = '• DID NOT PROVIDE hotel/lodging and food and meals';
        } else {
            $lodgingLines[] = '• PROVIDED the following:';
            $lodgingLines[] = '   a) Hotel/lodging/accommodation';
            $lodgingLines[] = '   b) Meals';
            if (!empty($particulars['meals']['breakfast'])) {
                $lodgingLines[] = '      − Breakfast';
            }
            if (!empty($particulars['meals']['lunch'])) {
                $lodgingLines[] = '      − Lunch';
            }
            if (!empty($particulars['meals']['dinner'])) {
                $lodgingLines[] = '      − Dinner';
            }
        }
        $left = implode("\n", $lodgingLines);
        $h1 = max(18, $pdf->getStringHeight(118, $left) + 4);
        $pdf->MultiCell(120, $h1, $left, 1, 'L', false, 0);
        $pdf->MultiCell(0, $h1, $inclusive, 1, 'C', false, 1);

        $vehicleText = $particulars['vehicle'] === 'provided'
            ? '• PROVIDED VEHICLE'
            : '• DID NOT PROVIDE VEHICLE';
        $h2 = 12;
        $pdf->MultiCell(120, $h2, $vehicleText, 1, 'L', false, 0);
        $pdf->MultiCell(0, $h2, $inclusive, 1, 'C', false, 1);

        $pdf->Ln(4);
        $closing = 'This certification is issued upon the request of the interested party to attest to the fact '
            . 'and duration of his/her appearance, duly verified and affirmed by the undersigned.';
        $pdf->writeHTML('<p style="text-align:justify; line-height:1.5;">' . htmlspecialchars($closing, ENT_QUOTES) . '</p>', true, false, true, false, '');
        $pdf->Ln(2);
        $pdf->writeHTML('<p>Issued this <b>' . htmlspecialchars($issueLabel, ENT_QUOTES) . '</b>.</p>', true, false, true, false, '');
        $pdf->Ln(10);

        $sigPath = (string)($signatory['signature_path'] ?? '');
        if ($sigPath !== '' && is_file($sigPath)) {
            $pdf->Image($sigPath, 18, $pdf->GetY(), 45, 0, '', '', '', false, 300);
            $pdf->Ln(22);
        } else {
            $pdf->Ln(18);
        }

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 5, strtoupper((string)$signatory['full_name']), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, (string)$signatory['title'], 0, 1, 'L');

        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certificates';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'coa_' . (int)($participant['id'] ?? 0) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.pdf';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        $pdf->Output($path, 'F');
        return $path;
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
