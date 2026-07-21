<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Controllers/AdminImportController.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/Uuid.php';

$pdo = \App\Services\Database::pdo();
$pdo->exec("DELETE FROM participants");
$uuid = \App\Services\Uuid::v4();
$pdo->prepare('INSERT INTO participants (uuid,email,first_name,last_name,agency) VALUES (?,?,?,?,?)')->execute([$uuid,'dup@example.com','Jane','Doe','Agency A']);

$c = new \App\Controllers\AdminImportController();
$ref = new ReflectionClass($c);
$m = $ref->getMethod('detectStatus');
$m->setAccessible(true);

$row1 = ['email'=>'dup@example.com','first_name'=>'X','middle_name'=>'','last_name'=>'Y','nickname'=>'','sex'=>'','sector'=>'','agency'=>'','designation'=>'','office_email'=>'','contact_no'=>''];
echo $m->invoke($c, $pdo, $row1), "\n";

$row2 = ['email'=>'','first_name'=>'Jane','middle_name'=>'','last_name'=>'Doe','nickname'=>'','sex'=>'','sector'=>'','agency'=>'Agency A','designation'=>'','office_email'=>'','contact_no'=>''];
echo $m->invoke($c, $pdo, $row2), "\n";

$row3 = ['email'=>'','first_name'=>'','middle_name'=>'','last_name'=>'Doe','nickname'=>'','sex'=>'','sector'=>'','agency'=>'','designation'=>'','office_email'=>'','contact_no'=>''];
echo $m->invoke($c, $pdo, $row3), "\n";

$row4 = ['email'=>'new@example.com','first_name'=>'New','middle_name'=>'','last_name'=>'Person','nickname'=>'','sex'=>'','sector'=>'','agency'=>'','designation'=>'','office_email'=>'','contact_no'=>''];
echo $m->invoke($c, $pdo, $row4), "\n";