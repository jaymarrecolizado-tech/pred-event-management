<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/Uuid.php';
require __DIR__ . '/../src/Services/QrService.php';

$rows = [
 ['email'=>'r.rinion@psa.gov.ph','first'=>'Romeo Jr.','middle'=>'Bautista','last'=>'Rinion','nickname'=>'Jhun','sex'=>'Male','sector'=>'National Government Agency','agency'=>'Philippine Statistics Authority','designation'=>'Statistical Specialist II','office_email'=>'r.rinion@psa.gov.ph','contact'=>'09272849173'],
 ['email'=>'e.urata@psa.gov.ph','first'=>'ERIC REYNALD','middle'=>'GARCIA','last'=>'URATA','nickname'=>'RIC','sex'=>'Male','sector'=>'National Government Agency','agency'=>'PHILIPPINE STATISTICS AUTHORITY - CAGAYAN','designation'=>'Administrative Officer I','office_email'=>'e.urata@psa.gov.ph','contact'=>'09168646165'],
 ['email'=>'quirino@psa.gov.ph','first'=>'LIZ','middle'=>'TEMPORAL','last'=>'DUQUE','nickname'=>'LIZ','sex'=>'Female','sector'=>'National Government Agency','agency'=>'PHILIPPINE STATISTICS AUTHORITY','designation'=>'STATISTICAL SPECIALIST II','office_email'=>'quirino@psa.gov.ph','contact'=>'09056986736'],
 ['email'=>'loydtejano.psa@gmail.com','first'=>'JOHN LOYD','middle'=>'TABABA','last'=>'TEJANO','nickname'=>'LOYD','sex'=>'Male','sector'=>'National Government Agency','agency'=>'PHILIPPINE STATISTICS AUTHORITY-QUIRINO','designation'=>'STATISTICAL AIDE VI','office_email'=>'quirino@psa.gov.ph','contact'=>'09616156441'],
 ['email'=>'cpdco.tugcity@gmail.com','first'=>'Bon Bernard','middle'=>'Pascual','last'=>'Acang','nickname'=>'Bon','sex'=>'Male','sector'=>'Local Government Unit','agency'=>'City Planning Development Office - LGU Tuguegarao','designation'=>'Planning Officer III','office_email'=>'cpdco.tugcity@gmail.com','contact'=>'09399787804'],
 ['email'=>'joeycalucag1226@gmail.com','first'=>'Joey','middle'=>'Annang','last'=>'Calucag','nickname'=>'Joey','sex'=>'Male','sector'=>'Local Government Unit','agency'=>'City Planning and Development Office - LGU Tuguegarao','designation'=>'Project Development Officer I','office_email'=>'cpdco.tugcity@gmail.com','contact'=>'09169857214'],
 ['email'=>'toferselosa@gmail.com','first'=>'Kristofer','middle'=>'Ramos','last'=>'Selosa','nickname'=>'Kris','sex'=>'Male','sector'=>'National Government Agency','agency'=>'DENR R2','designation'=>'Information Officer I','office_email'=>'Rscig.r2@denr.gov.ph','contact'=>'(0936)359-9489'],
 ['email'=>'rpunciano@denr.gov.ph','first'=>'Romell','middle'=>'Pichay','last'=>'Unciano','nickname'=>'mell','sex'=>'Male','sector'=>'National Government Agency','agency'=>'DENR R2- PENRO Isabela','designation'=>'Information Systems Analyst II','office_email'=>'rpunciano@denr.gov.ph','contact'=>'09213425954'],
 ['email'=>'rictmdpro2@gmail.com','first'=>'Gerald','middle'=>'Poblete','last'=>'Miguel','nickname'=>'Gerald','sex'=>'Male','sector'=>'National Government Agency','agency'=>'PNP','designation'=>'Assistant Chief, RICTMD','office_email'=>'rictmdpro2@gmail.com','contact'=>'09066468153'],
 ['email'=>'mptaguinod@gmail.com','first'=>'Mac Paul','middle'=>'Antonio','last'=>'Taguinod','nickname'=>'Paul','sex'=>'Male','sector'=>'National Government Agency','agency'=>'PNP','designation'=>'PSMS','office_email'=>'rictmdpro2@gmail.com','contact'=>'09972675164'],
 ['email'=>'penablancawd@gmail.com','first'=>'ANALYN','middle'=>'QUILANG','last'=>'GASPAR','nickname'=>'JANA','sex'=>'Female','sector'=>'GOCCs','agency'=>'PEÑABLANCA WATER DISTRICT','designation'=>'Utilities/Customer Service Asst C','office_email'=>'penablancawd@gmail.com','contact'=>'0966 009 3286'],
 ['email'=>'brianfrancissibbaluca@gmail.com','first'=>'Brian Francis','middle'=>'Isip','last'=>'Sibbaluca','nickname'=>'Koki','sex'=>'Male','sector'=>'GOCCs','agency'=>'Social Security System','designation'=>'Job Order Worker','office_email'=>'tuguegarao@sss.gov.ph','contact'=>'09153340319'],
 ['email'=>'rranunsacion@gmail.com','first'=>'Rey','middle'=>'Ramiscal','last'=>'Anunsacion','nickname'=>'Rey','sex'=>'Female','sector'=>'National Government Agency','agency'=>'Bureau of Fisheries and Aquatic  Resources','designation'=>'MIS Staff','office_email'=>'rranunsacion@gmail.com','contact'=>'09161698481'],
 ['email'=>'jaytagayunbulan@gmail.com','first'=>'JAY','middle'=>'TAGAYUN','last'=>'BULAN','nickname'=>'JAY','sex'=>'Male','sector'=>'GOCCs','agency'=>'PHILHEALTH REGIONAL OFFICE 02','designation'=>'COMPUTER MAINTENANCE TECHNOLOGIST 1','office_email'=>'bulanj.pro2@philhealth.gov.ph','contact'=>'09173031835'],
 ['email'=>'jesteradduru@gmail.com','first'=>'Jester','middle'=>'Clores','last'=>'Adduru','nickname'=>'Jester','sex'=>'Male','sector'=>'National Government Agency','agency'=>'Department of Economy, Planning, and Development','designation'=>'Information Systems Analyst I','office_email'=>'jcadduru@depdev.gov.ph','contact'=>'09765191773'],
 ['email'=>'malonanalam@gmail.com','first'=>'Marlon','middle'=>'Tallud','last'=>'Malana','nickname'=>'Lon','sex'=>'Male','sector'=>'GOCCs','agency'=>'Philhealth Regional Office II','designation'=>'ITO II','office_email'=>'malana.pro2@philhealth.gov.ph','contact'=>'9171177299'],
];

$pdo = \App\Services\Database::pdo();
$pdo->beginTransaction();
$ins = $pdo->prepare('INSERT INTO participants (uuid,email,first_name,middle_name,last_name,nickname,sex,sector,agency,designation,office_email,contact_no,qr_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$chk = $pdo->prepare('SELECT id FROM participants WHERE email = ?');
foreach ($rows as $r) {
    $email = trim($r['email']);
    $chk->execute([$email]);
    if ($chk->fetch()) continue;
    $uuid = \App\Services\Uuid::v4();
    $qrPath = \App\Services\QrService::generate('PART|' . $uuid, $uuid);
    $ins->execute([
        $uuid,
        $email,
        $r['first'],
        $r['middle'],
        $r['last'],
        $r['nickname'],
        $r['sex'],
        $r['sector'],
        $r['agency'],
        $r['designation'],
        $r['office_email'],
        $r['contact'],
        $qrPath,
    ]);
}
$pdo->commit();
echo 'seeded';