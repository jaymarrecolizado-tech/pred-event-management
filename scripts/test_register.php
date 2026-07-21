<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Controllers/RegisterController.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/Uuid.php';
require __DIR__ . '/../src/Services/QrService.php';
require __DIR__ . '/../src/Services/Mailer.php';

$_SESSION['csrf'] = bin2hex(random_bytes(32));
$_POST = [
  'csrf' => $_SESSION['csrf'],
  'first_name' => 'Alice',
  'middle_name' => 'B',
  'last_name' => 'Tester',
  'email' => 'alice@example.com',
  'agency' => 'Agency X',
  'sector' => 'Sector Y',
  'nickname' => 'Al',
  'sex' => 'Female',
  'designation' => 'Analyst',
  'office_email' => 'alice.office@example.com',
  'contact_no' => '1234567890',
];

$controller = new \App\Controllers\RegisterController();
$controller->submit();