<?php
declare(strict_types=1);

/**
 * Build CoA letterhead: DICT banner LEFT, Bagong Pilipinas RIGHT.
 */
$destDir = dirname(__DIR__) . '/assets/coa';
if (!is_dir($destDir)) {
    mkdir($destDir, 0775, true);
}

$bagongPath = is_file($destDir . '/bagong_pilipinas.png')
    ? $destDir . '/bagong_pilipinas.png'
    : $destDir . '/bagong_pilipinas.jpg';
$dictPath = $destDir . '/dict_banner.png';

if (!is_file($bagongPath) || !is_file($dictPath)) {
    fwrite(STDERR, "Missing bagong or dict logo in assets/coa\n");
    exit(1);
}

$bagong = str_ends_with(strtolower($bagongPath), '.png')
    ? imagecreatefrompng($bagongPath)
    : imagecreatefromjpeg($bagongPath);
$dict = imagecreatefrompng($dictPath);

$bw = imagesx($bagong);
$bh = imagesy($bagong);
$dw = imagesx($dict);
$dh = imagesy($dict);

$canvasW = 1700;
$canvasH = 260;
$canvas = imagecreatetruecolor($canvasW, $canvasH);
$bg = imagecolorallocate($canvas, 255, 255, 255);
imagefilledrectangle($canvas, 0, 0, $canvasW, $canvasH, $bg);

// DICT banner on the LEFT
$dictH = 220;
$dictW = (int)round($dw * ($dictH / $dh));
$maxDictW = (int)($canvasW * 0.68);
if ($dictW > $maxDictW) {
    $dictW = $maxDictW;
    $dictH = (int)round($dh * ($dictW / $dw));
}
$dictY = (int)(($canvasH - $dictH) / 2);
imagecopyresampled($canvas, $dict, 20, $dictY, 0, 0, $dictW, $dictH, $dw, $dh);

// Bagong Pilipinas on the RIGHT
$bagongH = 210;
$bagongW = (int)round($bw * ($bagongH / $bh));
$bagongX = $canvasW - $bagongW - 30;
$bagongY = (int)(($canvasH - $bagongH) / 2);
imagecopyresampled($canvas, $bagong, $bagongX, $bagongY, 0, 0, $bagongW, $bagongH, $bw, $bh);

$out = $destDir . '/letterhead.png';
imagepng($canvas, $out);
echo "wrote {$out} bytes=" . filesize($out) . PHP_EOL;

imagedestroy($bagong);
imagedestroy($dict);
imagedestroy($canvas);
