<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AttendanceReportPdf;
use App\Services\Database;
use App\Services\SignatureService;

class ReportController
{
    private function requireAdmin(): bool
    {
        if (empty($_SESSION['admin_id'])) { header('Location: ?r=admin_login'); return false; }
        return true;
    }

    public function form(): void
    {
        if (!$this->requireAdmin()) return;
        $pdfAvailable = class_exists('TCPDF') || class_exists('\\TCPDF');
        if (!$pdfAvailable && is_file(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
            $pdfAvailable = class_exists('TCPDF') || class_exists('\\TCPDF');
        }

        $pdo = Database::pdo();
        $tpl = [];
        $reportNotice = null;
        try {
            // Ensure templates table exists even when auto-migrate is off.
            $exists = $pdo->query("SHOW TABLES LIKE 'report_templates'")->fetch();
            if (!$exists) {
                $sqlFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '004_report_templates.sql';
                if (is_file($sqlFile)) {
                    $pdo->exec((string)file_get_contents($sqlFile));
                }
            }
            $stmt = $pdo->prepare('SELECT id, name FROM report_templates WHERE admin_id = ? ORDER BY id DESC');
            $stmt->execute([(int)$_SESSION['admin_id']]);
            $tpl = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            error_log('Report form templates error: ' . $e->getMessage());
            $reportNotice = 'Report templates are temporarily unavailable. You can still generate reports.';
            $tpl = [];
        }

        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_report.php';
    }

    public function generate(): void
    {
        if (!$this->requireAdmin()) return;
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) { http_response_code(400); echo 'Invalid CSRF'; return; }
        $date = trim((string)($_POST['date'] ?? ''));
        $title = trim((string)($_POST['title'] ?? 'Attendance Report'));
        $subtitle = trim((string)($_POST['subtitle'] ?? ''));
        $fields = (array)($_POST['fields'] ?? []);
        $format = trim((string)($_POST['format'] ?? 'auto'));
        $download = ((string)($_POST['download'] ?? '0')) === '1';
        $start = trim((string)($_POST['start_date'] ?? ''));
        $end = trim((string)($_POST['end_date'] ?? ''));
        $leftLogoPath = null; $rightLogoPath = null;
        foreach (['left_logo'=>'leftLogoPath','right_logo'=>'rightLogoPath'] as $key=>$var) {
            if (isset($_FILES[$key]) && is_uploaded_file($_FILES[$key]['tmp_name'])) {
                $type = mime_content_type($_FILES[$key]['tmp_name']);
                if (!in_array($type, ['image/png','image/jpeg'])) continue;
                $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'logos';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $ext = $type === 'image/png' ? 'png' : 'jpg';
                $dest = $dir . DIRECTORY_SEPARATOR . (time().'_'.bin2hex(random_bytes(4))).'.'.$ext;
                move_uploaded_file($_FILES[$key]['tmp_name'], $dest);
                $$var = $dest;
            }
        }
        $pdo = Database::pdo();
        $where=[];$bind=[];
        if ($date !== '') { $where[]='a.attendance_date = ?'; $bind[]=$date; }
        if ($start !== '' && $end !== '') { $where[]='a.attendance_date BETWEEN ? AND ?'; $bind[]=$start; $bind[]=$end; }
        $sqlWhere = $where?('WHERE '.implode(' AND ',$where)) : '';
        $stmt = $pdo->prepare("SELECT a.id,a.signature_path,a.attendance_date,a.time_in,p.uuid,p.first_name,p.last_name,p.agency,p.designation,p.email,p.sex,p.contact_no,p.sector FROM attendance a JOIN participants p ON p.id=a.participant_id $sqlWhere ORDER BY a.id ASC");
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
        
        if (empty($fields)) {
            $fields = ['id', 'name', 'agency', 'designation', 'email', 'sex', 'registered_at'];
        }
        $fields = array_values(array_unique($fields));

        // Check if TCPDF is available via composer autoload
        $pdfAvailable = false;
        if (class_exists('\\TCPDF')) {
            $pdfAvailable = true;
        } elseif (class_exists('TCPDF')) {
            $pdfAvailable = true;
        } elseif (file_exists(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            // Try to ensure autoload is loaded
            require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
            $pdfAvailable = class_exists('\\TCPDF') || class_exists('TCPDF');
        }
        $usePdf = ($format === 'pdf' && $pdfAvailable) || ($format === 'auto' && $pdfAvailable);
        
        $titleHtml = nl2br(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
        $subtitleHtml = $subtitle !== '' ? nl2br(htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8')) : '';

        $pdfError = null;
        if ($usePdf) {
            try {
                $pdf = new AttendanceReportPdf('L', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->reportTitle = $title !== '' ? $title : 'Attendance Report';
                $pdf->reportSubtitle = $subtitle;
                $pdf->leftLogoPath = $leftLogoPath;
                $pdf->rightLogoPath = $rightLogoPath;
                $pdf->SetCreator('Event Management');
                $pdf->SetAuthor('Event Management');
                $pdf->SetTitle($title !== '' ? $title : 'Attendance Report');
                $pdf->SetSubject('Attendance Report');
                $pdf->SetMargins(12, 40, 12);
                $pdf->SetHeaderMargin(6);
                $pdf->SetFooterMargin(12);
                $pdf->SetAutoPageBreak(true, 18);
                $pdf->setPrintHeader(true);
                $pdf->setPrintFooter(true);
                $pdf->setImageScale(defined('PDF_IMAGE_SCALE_RATIO') ? PDF_IMAGE_SCALE_RATIO : 1.25);
                $pdf->setLanguageArray([]);
                $pdf->AddPage();
            } catch (\Throwable $e) {
                $usePdf = false;
                $pdfError = 'PDF initialization failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                error_log('TCPDF Initialization Error: ' . $e->getMessage());
            }
        }

        if ($usePdf) {
            try {
                $pdf->writeHTML($this->buildReportTableHtml($rows, $fields, false), true, false, true, false, '');
                $pdf->Output('attendance_report.pdf', $download ? 'D' : 'I');
                return;
            } catch (\Throwable $e) {
                error_log('TCPDF writeHTML/Output Error: ' . $e->getMessage());
                $usePdf = false;
                $pdfError = 'PDF generation failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }

        $this->renderHtmlReport($title, $titleHtml, $subtitleHtml, $leftLogoPath, $rightLogoPath, $rows, $fields, $pdfError);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $fields
     */
    private function buildReportTableHtml(array $rows, array $fields, bool $forBrowser): string
    {
        $map = $this->fieldMap();

        $html = '<style>
            .section-title { color:#1a365d; font-size:12pt; font-weight:bold; margin:0 0 6px 0; }
            .meta { color:#64748b; font-size:8pt; margin:0 0 8px 0; }
            table.report { border-collapse:collapse; width:100%; }
            table.report th {
                background-color:#1a365d; color:#ffffff; font-weight:bold;
                font-size:8.5pt; text-align:center; border:0.4pt solid #1a365d;
                padding:5px 4px;
            }
            table.report td {
                font-size:8pt; border:0.3pt solid #cbd5e1; padding:4px 4px;
                vertical-align:middle;
            }
            table.report tr.odd td { background-color:#f8fafc; }
            table.report tr.even td { background-color:#ffffff; }
            .sig { text-align:center; }
            .empty { color:#64748b; font-style:italic; }
        </style>';

        $html .= '<div class="section-title">Registered Guest List</div>';
        $html .= '<div class="meta">' . count($rows) . ' record' . (count($rows) === 1 ? '' : 's') . '</div>';

        if ($rows === []) {
            $html .= '<p class="empty">No attendance records found for the selected criteria.</p>';
            return $html;
        }

        $html .= '<table class="report" cellpadding="4" cellspacing="0" width="100%"><thead><tr>';
        foreach ($fields as $f) {
            if (isset($map[$f])) {
                $html .= '<th>' . $map[$f] . '</th>';
            }
        }
        $html .= '<th>Signature</th></tr></thead><tbody>';

        $rowNo = 0;
        foreach ($rows as $r) {
            $rowNo++;
            $oddEven = ($rowNo % 2 === 1) ? 'odd' : 'even';
            $html .= '<tr class="' . $oddEven . '">';
            foreach ($fields as $f) {
                if (!isset($map[$f])) {
                    continue;
                }
                $align = ($f === 'id' || $f === 'sex') ? ' align="center"' : '';
                $html .= '<td' . $align . '>' . $this->val($f, $r, $rowNo) . '</td>';
            }
            $b64 = '';
            $sigFile = SignatureService::resolvePath($r['signature_path'] ?? null);
            if ($sigFile !== null) {
                $b64 = base64_encode((string)file_get_contents($sigFile));
            }
            $imgStyle = $forBrowser ? ' style="height:36px;max-width:120px;"' : ' height="32"';
            $imgTag = $b64 !== '' ? ('<img src="data:image/png;base64,' . $b64 . '"' . $imgStyle . '>') : '—';
            $html .= '<td class="sig">' . $imgTag . '</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $fields
     */
    private function renderHtmlReport(
        string $title,
        string $titleHtml,
        string $subtitleHtml,
        ?string $leftLogoPath,
        ?string $rightLogoPath,
        array $rows,
        array $fields,
        ?string $pdfError
    ): void {
        header('Content-Type: text/html; charset=UTF-8');
        $titleEscaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $leftImg = $leftLogoPath
            ? '<img src="data:image/' . $this->extOf($leftLogoPath) . ';base64,' . base64_encode((string)file_get_contents($leftLogoPath)) . '" alt="">'
            : '';
        $rightImg = $rightLogoPath
            ? '<img src="data:image/' . $this->extOf($rightLogoPath) . ';base64,' . base64_encode((string)file_get_contents($rightLogoPath)) . '" alt="">'
            : '';

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . $titleEscaped . '</title>
<style>
:root { --navy:#1a365d; --line:#cbd5e1; --muted:#64748b; --band:#f8fafc; }
* { box-sizing:border-box; }
body { margin:0; font-family:Segoe UI, Helvetica, Arial, sans-serif; color:#0f172a; background:#eef2f7; }
.sheet { max-width:1200px; margin:18px auto; background:#fff; padding:22px 28px 56px; box-shadow:0 8px 24px rgba(15,23,42,.08); }
.report-header { display:grid; grid-template-columns:160px 1fr 160px; gap:16px; align-items:center; padding-bottom:12px; border-bottom:2px solid var(--navy); margin-bottom:14px; }
.report-header img { max-height:64px; width:auto; max-width:100%; object-fit:contain; }
.report-header .right { text-align:right; }
.report-header .center { text-align:center; }
.report-header h1 { margin:0; font-size:1.35rem; color:var(--navy); line-height:1.25; white-space:pre-line; }
.report-header .subtitle { margin-top:6px; color:#334155; font-size:.95rem; line-height:1.35; white-space:pre-line; }
.section-title { color:var(--navy); font-size:1.05rem; font-weight:700; margin:0 0 4px; }
.meta { color:var(--muted); font-size:.85rem; margin:0 0 12px; }
table.report { width:100%; border-collapse:collapse; }
table.report th { background:var(--navy); color:#fff; font-size:.78rem; text-align:center; padding:8px 6px; border:1px solid var(--navy); }
table.report td { font-size:.82rem; padding:7px 6px; border:1px solid var(--line); vertical-align:middle; }
table.report tr.odd td { background:var(--band); }
table.report td.sig { text-align:center; }
.print-footer { display:flex; justify-content:space-between; color:var(--muted); font-size:.8rem; margin-top:18px; padding-top:10px; border-top:1px solid var(--line); }
@media print {
  @page { size:A4 landscape; margin:12mm 10mm 14mm 10mm; }
  body { background:#fff; }
  .sheet { max-width:none; margin:0; padding:0 0 24px; box-shadow:none; }
  .no-print { display:none !important; }
  .report-header { break-inside:avoid; }
  thead { display:table-header-group; }
  tr { break-inside:avoid; }
  .print-footer { position:fixed; left:0; right:0; bottom:0; margin:0; padding:6px 10mm; background:#fff; }
  .print-footer .page::after { content:"Page " counter(page); }
}
</style></head><body><div class="sheet">';

        if ($pdfError !== null) {
            echo '<div class="no-print" style="background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:10px 12px;margin-bottom:12px;border-radius:6px"><strong>Note:</strong> ' . $pdfError . ' Displaying as HTML instead.</div>';
        }

        echo '<div class="report-header"><div>' . $leftImg . '</div><div class="center"><h1>' . $titleHtml . '</h1>';
        if ($subtitleHtml !== '') {
            echo '<div class="subtitle">' . $subtitleHtml . '</div>';
        }
        echo '</div><div class="right">' . $rightImg . '</div></div>';
        echo $this->buildReportTableHtml($rows, $fields, true);
        echo '<div class="print-footer"><span>Generated ' . htmlspecialchars(date('M j, Y g:i A'), ENT_QUOTES) . '</span><span class="page"></span></div>';
        echo '</div></body></html>';
    }

    private function fieldMap(): array
    {
        return [
            'id' => 'No.',
            'name' => 'Name',
            'agency' => 'Agency/Org.',
            'sector' => 'Sector',
            'designation' => 'Designation',
            'email' => 'Email',
            'sex' => 'Gender',
            'registered_at' => 'Registered At',
        ];
    }

    private function val(string $f, array $r, int $rowNumber = 0): string
    {
        switch ($f) {
            case 'id':
                if ($rowNumber > 0) {
                    return (string)$rowNumber;
                }
                return (string)$r['id'];
            case 'name': return htmlspecialchars(($r['first_name'].' '.$r['last_name']), ENT_QUOTES);
            case 'agency': return htmlspecialchars((string)$r['agency'], ENT_QUOTES);
            case 'designation': return htmlspecialchars((string)$r['designation'], ENT_QUOTES);
            case 'email': return htmlspecialchars((string)$r['email'], ENT_QUOTES);
            case 'sector': return htmlspecialchars((string)$r['sector'], ENT_QUOTES);
            case 'sex': return htmlspecialchars((string)$r['sex'], ENT_QUOTES);
            case 'registered_at': {
                return htmlspecialchars($r['attendance_date'] ?? '', ENT_QUOTES);
            }
        }
        return '';
    }
    private function extOf(string $path): string {
        $lower = strtolower($path);
        return (substr($lower, -4) === '.png') ? 'png' : 'jpeg';
    }

    public function saveTemplate(): void
    {
        if (!$this->requireAdmin()) return;
        if (!isset($_POST['csrf']) || !function_exists('csrf_check') || !csrf_check($_POST['csrf'])) { http_response_code(400); echo 'Invalid CSRF'; return; }
        $name = trim((string)($_POST['tpl_name'] ?? 'Untitled'));
        $config = [
            'title' => (string)($_POST['title'] ?? ''),
            'subtitle' => (string)($_POST['subtitle'] ?? ''),
            'date' => (string)($_POST['date'] ?? ''),
            'start_date' => (string)($_POST['start_date'] ?? ''),
            'end_date' => (string)($_POST['end_date'] ?? ''),
            'fields' => (array)($_POST['fields'] ?? []),
            'format' => (string)($_POST['format'] ?? 'auto'),
        ];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO report_templates (admin_id,name,config) VALUES (?,?,?)');
        $stmt->execute([(int)$_SESSION['admin_id'],$name,json_encode($config)]);
        header('Location: ?r=admin_report');
    }

    public function loadTemplate(): void
    {
        if (!$this->requireAdmin()) return;
        $id = (int)($_GET['tpl_id'] ?? 0);
        $pdo = Database::pdo();
        $tpl = $pdo->prepare('SELECT config FROM report_templates WHERE id=? AND admin_id=?');
        $tpl->execute([$id,(int)$_SESSION['admin_id']]);
        $row = $tpl->fetch();
        header('Content-Type: application/json');
        echo $row ? $row['config'] : json_encode(['error'=>'not_found']);
    }
}