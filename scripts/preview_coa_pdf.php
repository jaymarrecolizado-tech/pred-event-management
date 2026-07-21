<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Services/CertificateOfAppearanceService.php';

use App\Services\CertificateOfAppearanceService as Coa;

$sigDir = dirname(__DIR__) . '/storage/coa_signatories';
$sigPath = $sigDir . '/test_sig.png';
if (!is_dir($sigDir)) {
    mkdir($sigDir, 0775, true);
}
if (!is_file($sigPath)) {
    // simple signature-like stroke
    $img = imagecreatetruecolor(400, 120);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $ink = imagecolorallocate($img, 20, 20, 20);
    imagefilledrectangle($img, 0, 0, 400, 120, $bg);
    imagesetthickness($img, 3);
    imagearc($img, 120, 70, 140, 60, 200, 20, $ink);
    imageline($img, 80, 80, 280, 40, $ink);
    imagepng($img, $sigPath);
    imagedestroy($img);
}

$participant = [
    'id' => 2,
    'first_name' => 'Maricar',
    'middle_name' => 'C',
    'last_name' => 'Pecson',
    'agency' => 'DICT R2',
];
$activity = [
    'venue' => 'DICT Regional Office 2',
    'purpose' => 'attending DICT AI ROADSHOW 2026',
    'inclusive_dates' => 'July 21, 2026',
    'issue_date' => '2026-07-21',
];
$signatory = [
    'full_name' => 'Engr. Rogelio T. Layugan',
    'title' => 'ITO-II, DICTR2 Cagayan Provincial Officer',
    'signature_path' => $sigPath,
];

$path = Coa::renderPdf($participant, $activity, [
    'lodging' => 'not_provided',
    'meals' => ['status' => 'provided', 'lunch' => true],
    'vehicle' => 'not_provided',
], $signatory);
$preview = dirname(__DIR__) . '/storage/certificates/coa_preview_official_style.pdf';
copy($path, $preview);
echo "preview={$preview}\n";
echo 'bytes=' . filesize($preview) . PHP_EOL;
echo 'letterhead=' . (is_file(dirname(__DIR__) . '/assets/coa/letterhead.png') ? 'yes' : 'no') . PHP_EOL;
