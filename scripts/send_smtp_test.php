<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Mailer.php';

$to = $argv[1] ?? getenv('SMTP_TEST_TO') ?: '';
if ($to === '') { echo "no_recipient"; exit(1); }
$subject = 'SMTP Test Delivery';
$body = '<p>This is a test email from Event Registration & Attendance System.</p><p>If you can read this, SMTP is configured correctly.</p>';
$ok = \App\Services\Mailer::send($to, $subject, $body);
echo $ok ? 'ok' : 'fail';