<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Mailer.php';

$to = $argv[1] ?? '';
if ($to === '') { echo "no_recipient"; exit(1); }
$subject = 'SMTP Test Delivery';
$body = '<p>SMTP test via raw sockets.</p>';
$ok = \App\Services\Mailer::send($to, $subject, $body);
echo $ok ? 'ok' : 'fail';