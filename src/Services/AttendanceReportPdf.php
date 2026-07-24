<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Landscape attendance report with repeating header and page numbers.
 */
class AttendanceReportPdf extends \TCPDF
{
    public string $reportTitle = 'Attendance Report';
    public string $reportSubtitle = '';
    public ?string $leftLogoPath = null;
    public ?string $rightLogoPath = null;

    public function Header(): void
    {
        $margins = $this->getMargins();
        $pageW = (float)$this->getPageWidth();
        $left = (float)$margins['left'];
        $right = (float)$margins['right'];
        $usable = $pageW - $left - $right;
        $logoH = 16.0;
        $y = 8.0;

        if ($this->leftLogoPath !== null && is_file($this->leftLogoPath)) {
            $this->Image($this->leftLogoPath, $left, $y, 0, $logoH, '', '', '', false, 300);
        }

        if ($this->rightLogoPath !== null && is_file($this->rightLogoPath)) {
            $w = $this->logoWidth($this->rightLogoPath, $logoH);
            $this->Image($this->rightLogoPath, $pageW - $right - $w, $y, $w, $logoH, '', '', '', false, 300);
        }

        $textW = max(60.0, $usable - 78.0);
        $textX = $left + (($usable - $textW) / 2.0);
        $this->SetXY($textX, $y - 1.0);

        $this->SetTextColor(26, 54, 93);
        $this->SetFont('helvetica', 'B', 13);
        $title = trim($this->reportTitle) !== '' ? $this->reportTitle : 'Attendance Report';
        $this->MultiCell($textW, 5.2, $title, 0, 'C', false, 1);

        if (trim($this->reportSubtitle) !== '') {
            $this->SetX($textX);
            $this->SetTextColor(55, 65, 81);
            $this->SetFont('helvetica', '', 9);
            $this->MultiCell($textW, 4.2, $this->reportSubtitle, 0, 'C', false, 1);
        }

        $lineY = max($this->GetY(), $y + $logoH) + 2.0;
        $this->SetDrawColor(26, 54, 93);
        $this->SetLineWidth(0.45);
        $this->Line($left, $lineY, $pageW - $right, $lineY);
        $this->SetDrawColor(180, 198, 231);
        $this->SetLineWidth(0.2);
        $this->Line($left, $lineY + 0.8, $pageW - $right, $lineY + 0.8);

        $this->SetY($lineY + 3.0);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(203, 213, 224);
        $this->SetLineWidth(0.2);
        $margins = $this->getMargins();
        $pageW = (float)$this->getPageWidth();
        $this->Line((float)$margins['left'], $this->GetY(), $pageW - (float)$margins['right'], $this->GetY());

        $this->SetY(-11);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);

        $generated = 'Generated ' . date('M j, Y g:i A');
        $this->Cell(60, 8, $generated, 0, 0, 'L');
        $this->Cell(0, 8, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    private function logoWidth(string $path, float $height): float
    {
        $size = @getimagesize($path);
        if (is_array($size) && ($size[1] ?? 0) > 0) {
            return $height * ((float)$size[0] / (float)$size[1]);
        }
        return 42.0;
    }
}
