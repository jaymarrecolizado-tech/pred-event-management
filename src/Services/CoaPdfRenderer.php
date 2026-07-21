<?php
declare(strict_types=1);

namespace App\Services;

use TCPDF;

/**
 * Renders Certificate of Appearance PDFs: landscape A4 with two identical copies.
 */
final class CoaPdfRenderer
{
    /**
     * @param array<string,mixed> $participant
     * @param array{venue:string,purpose:string,inclusive_dates:string,issue_date:string} $activity
     * @param array<string,mixed> $particulars
     * @param array{full_name:string,title:string,signature_path:string} $signatory
     */
    public static function render(array $participant, array $activity, array $particulars, array $signatory): string
    {
        if (!class_exists(TCPDF::class) && is_file(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        }
        if (!class_exists(TCPDF::class)) {
            throw new \RuntimeException('TCPDF is not available');
        }

        $particulars = CertificateOfAppearanceService::normalizeParticulars($particulars);
        $fullName = CertificateOfAppearanceService::certificateName($participant);
        $agency = mb_strtoupper(trim((string)($participant['agency'] ?? '')) ?: '________________');
        $venue = trim((string)($activity['venue'] ?? ''));
        $purpose = trim((string)($activity['purpose'] ?? ''));
        $inclusive = trim((string)($activity['inclusive_dates'] ?? ''));
        $issueDate = trim((string)($activity['issue_date'] ?? date('Y-m-d')));
        $issueLabel = CertificateOfAppearanceService::formatIssueDate($issueDate);
        $office = CertificateOfAppearanceService::officeHeader();
        $root = dirname(__DIR__, 2);
        $coaAssets = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'coa' . DIRECTORY_SEPARATOR;
        $headerBanner = $coaAssets . 'header_banner.jpg';
        if (!is_file($headerBanner)) {
            $headerBanner = $coaAssets . 'header_banner.png';
        }

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('DICT Region II Event Management');
        $pdf->SetAuthor($office['name']);
        $pdf->SetTitle('Certificate of Appearance');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // Landscape A4: 297 x 210 mm — two side-by-side copies.
        $pageW = 297.0;
        $pageH = 210.0;
        $gap = 2.0;
        $margin = 6.0;
        $halfW = ($pageW - (2 * $margin) - $gap) / 2.0;
        $copyH = $pageH - (2 * $margin);

        $ctx = [
            'fullName' => $fullName,
            'agency' => $agency,
            'venue' => $venue,
            'purpose' => $purpose,
            'inclusive' => $inclusive,
            'issueLabel' => $issueLabel,
            'office' => $office,
            'particulars' => $particulars,
            'signatory' => $signatory,
            'headerBanner' => is_file($headerBanner) ? $headerBanner : null,
        ];

        self::drawCopy($pdf, $margin, $margin, $halfW, $copyH, $ctx);
        self::drawCopy($pdf, $margin + $halfW + $gap, $margin, $halfW, $copyH, $ctx);

        // Light vertical cut guide between copies.
        $cutX = $margin + $halfW + ($gap / 2.0);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);
        $pdf->SetLineStyle(['dash' => '2,2']);
        $pdf->Line($cutX, $margin + 2, $cutX, $pageH - $margin - 2);
        $pdf->SetLineStyle(['dash' => 0]);
        $pdf->SetDrawColor(0, 0, 0);

        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certificates';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'coa_' . (int)($participant['id'] ?? 0) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.pdf';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        $pdf->Output($path, 'F');
        return $path;
    }

    /**
     * @param array<string,mixed> $ctx
     */
    private static function drawCopy(TCPDF $pdf, float $x, float $y, float $w, float $h, array $ctx): void
    {
        $pad = 5.0;
        $contentX = $x + $pad;
        $contentW = $w - (2 * $pad);
        $cursorY = $y + 2.0;

        $office = $ctx['office'];
        $particulars = $ctx['particulars'];
        $signatory = $ctx['signatory'];
        $safeName = htmlspecialchars((string)$ctx['fullName'], ENT_QUOTES);
        $safeAgency = htmlspecialchars((string)$ctx['agency'], ENT_QUOTES);
        $safeVenue = htmlspecialchars((string)$ctx['venue'], ENT_QUOTES);
        $safeInclusive = htmlspecialchars((string)$ctx['inclusive'], ENT_QUOTES);
        $safePurpose = htmlspecialchars((string)$ctx['purpose'], ENT_QUOTES);
        $safeIssue = htmlspecialchars((string)$ctx['issueLabel'], ENT_QUOTES);

        // Header banner
        $bannerH = 0.0;
        if (!empty($ctx['headerBanner'])) {
            $bannerPath = (string)$ctx['headerBanner'];
            $ext = str_ends_with(strtolower($bannerPath), '.png') ? 'PNG' : 'JPG';
            $bannerH = 18.0;
            $pdf->Image($bannerPath, $contentX, $cursorY, $contentW, $bannerH, $ext, '', '', false, 300, '', false, false, 0, false, false, false);
            $cursorY += $bannerH + 3.0;
        }

        $pdf->SetTextColor(0, 0, 0);

        // Title
        $pdf->SetFont('times', 'B', 12);
        $title = 'CERTIFICATE OF APPEARANCE';
        $pdf->SetXY($contentX, $cursorY);
        $pdf->Cell($contentW, 5.5, $title, 0, 1, 'C');
        $titleWidth = $pdf->GetStringWidth($title);
        $lineX = $contentX + (($contentW - $titleWidth) / 2.0);
        $lineY = $pdf->GetY();
        $pdf->SetLineWidth(0.35);
        $pdf->Line($lineX, $lineY, $lineX + $titleWidth, $lineY);
        $cursorY = $lineY + 3.5;

        // Salutation
        $pdf->SetFont('times', '', 9);
        $pdf->SetXY($contentX, $cursorY);
        $pdf->Cell($contentW, 4.2, 'TO WHOM IT MAY CONCERN:', 0, 1, 'L');
        $cursorY = $pdf->GetY() + 1.5;

        $pdf->SetLeftMargin($contentX);
        $pdf->SetRightMargin(297.0 - ($contentX + $contentW));
        $pdf->SetY($cursorY);

        $bodyCss = 'text-align:justify; line-height:1.35; font-size:9pt; font-family:times; margin:0;';
        $pdf->writeHTML(
            '<p style="' . $bodyCss . '">'
            . 'This is to certify that <b>' . $safeName . '</b> of <b>' . $safeAgency
            . '</b> appeared at <b>' . $safeVenue . '</b> on <b>' . $safeInclusive
            . '</b> for the purpose of ' . $safePurpose . '.'
            . '</p>',
            true,
            false,
            true,
            false,
            ''
        );
        $pdf->Ln(1.2);
        $pdf->writeHTML(
            '<p style="' . $bodyCss . '">'
            . 'It is further certified that during the stay of the above-mentioned name, this office:'
            . '</p>',
            true,
            false,
            true,
            false,
            ''
        );
        $pdf->Ln(1.0);

        $lodgingHtml = self::particularsLodgingHtml($particulars);
        $mealsHtml = self::particularsMealsHtml($particulars);
        $vehicleHtml = $particulars['vehicle'] === 'provided'
            ? '&#8226; PROVIDED VEHICLE'
            : '&#8226; DID NOT PROVIDE VEHICLE';

        $table = '<table border="1" cellpadding="3" cellspacing="0" width="100%" style="border-collapse:collapse; font-family:times; font-size:8pt;">'
            . '<tr>'
            . '<th width="62%" align="center" style="font-weight:bold;">Particulars</th>'
            . '<th width="38%" align="center" style="font-weight:bold;">Inclusive Dates</th>'
            . '</tr>'
            . '<tr>'
            . '<td width="62%" style="line-height:1.3;">' . $lodgingHtml . '</td>'
            . '<td width="38%" align="center">' . $safeInclusive . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td width="62%" style="line-height:1.3;">' . $mealsHtml . '</td>'
            . '<td width="38%" align="center">' . $safeInclusive . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td width="62%">' . $vehicleHtml . '</td>'
            . '<td width="38%" align="center">' . $safeInclusive . '</td>'
            . '</tr>'
            . '</table>';
        $pdf->writeHTML($table, true, false, true, false, '');
        $pdf->Ln(1.5);

        $closing = 'This certification is issued upon the request of the interested party to attest to the fact '
            . 'and duration of his/her appearance, duly verified and affirmed by the undersigned.';
        $pdf->writeHTML(
            '<p style="' . $bodyCss . '">' . htmlspecialchars($closing, ENT_QUOTES) . '</p>',
            true,
            false,
            true,
            false,
            ''
        );
        $pdf->Ln(1.0);
        $pdf->writeHTML(
            '<p style="font-size:9pt; font-family:times; margin:0;">Issued this <b>' . $safeIssue . '.</b></p>',
            true,
            false,
            true,
            false,
            ''
        );

        // Signature block (left-aligned within copy)
        $sigPath = (string)($signatory['signature_path'] ?? '');
        $sigY = $pdf->GetY() + 3.0;
        $sigMaxY = $y + $h - 22.0;
        if ($sigY > $sigMaxY - 18.0) {
            $sigY = $sigMaxY - 18.0;
        }
        if ($sigPath !== '' && is_file($sigPath)) {
            $pdf->Image($sigPath, $contentX + 2.0, $sigY, 28, 0, '', '', '', false, 300);
            $nameY = $sigY + 14.0;
        } else {
            $nameY = $sigY + 10.0;
        }

        $pdf->SetXY($contentX, $nameY);
        $pdf->SetFont('times', 'B', 9);
        $pdf->Cell($contentW, 4.0, mb_strtoupper((string)$signatory['full_name']), 0, 1, 'L');
        $pdf->SetX($contentX);
        $pdf->SetFont('times', '', 8);
        $pdf->Cell($contentW, 3.6, (string)$signatory['title'], 0, 1, 'L');

        // Footer contact block — fixed at bottom of this copy
        self::drawFooter($pdf, $contentX, $y + $h - 14.0, $contentW, $office);

        // Reset margins for next copy
        $pdf->SetLeftMargin(0);
        $pdf->SetRightMargin(0);
    }

    /**
     * @param array{name:string,website:string,address:string,email:string,phone:string} $office
     */
    private static function drawFooter(TCPDF $pdf, float $x, float $y, float $w, array $office): void
    {
        $lineH = 3.6;
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $lineH, $office['name'] . '  ' . $office['website'], 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetX($x);
        $pdf->Cell($w, $lineH, $office['address'], 0, 1, 'C');

        $pdf->SetX($x);
        $pdf->Cell($w, $lineH, $office['email'] . '  ' . $office['phone'], 0, 1, 'C');
    }

    /**
     * @param array<string,mixed> $particulars
     */
    private static function particularsLodgingHtml(array $particulars): string
    {
        if (($particulars['lodging'] ?? '') === 'provided') {
            return '&#8226; PROVIDED hotel/lodging/accommodation';
        }
        return '&#8226; DID NOT PROVIDE hotel/lodging';
    }

    /**
     * @param array<string,mixed> $particulars
     */
    private static function particularsMealsHtml(array $particulars): string
    {
        $meals = is_array($particulars['meals'] ?? null) ? $particulars['meals'] : [];
        if (($meals['status'] ?? '') !== 'provided') {
            return '&#8226; DID NOT PROVIDE food and meals';
        }
        $lines = ['&#8226; PROVIDED food and meals'];
        if (!empty($meals['breakfast'])) {
            $lines[] = '&nbsp;&nbsp;&nbsp;- Breakfast';
        }
        if (!empty($meals['lunch'])) {
            $lines[] = '&nbsp;&nbsp;&nbsp;- Lunch';
        }
        if (!empty($meals['dinner'])) {
            $lines[] = '&nbsp;&nbsp;&nbsp;- Dinner';
        }
        return implode('<br/>', $lines);
    }
}
