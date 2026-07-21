<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Controllers/AdminImportController.php';
require __DIR__ . '/../src/Services/Database.php';

$_SESSION['admin_id'] = 1;
$_SESSION['csrf'] = bin2hex(random_bytes(32));
$csv = "Timestamp,Email Address,First Name,Middle Name,Last Name,Nickname,Sex,Sector,Agency,Designation,Office Email,Contact No\n" .
       ",alice@example.com,Alice,B,Tester,Al,Female,Sector Y,Agency X,Analyst,alice.office@example.com,123\n" .
       ",,Bob,,Builder,,Male,,Agency X,,bob.office@example.com,555\n";
$tmp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tmp, $csv);
$_FILES['csv'] = ['name'=>'sample.csv','type'=>'text/csv','tmp_name'=>$tmp,'size'=>strlen($csv),'error'=>0];
$_POST['csrf'] = $_SESSION['csrf'];
(new \App\Controllers\AdminImportController())->preview();