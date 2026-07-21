<?php
declare(strict_types=1);

putenv('QR_EXTERNAL=false');
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Services/Uuid.php';
require __DIR__ . '/../src/Services/QrService.php';

$uuid = \App\Services\Uuid::v4();
$path = \App\Services\QrService::generate('PART|' . $uuid, $uuid);
echo $path;