<?php
declare(strict_types=1);

namespace App\Controllers;

class SampleCsvController
{
    public function download(): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="participants_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Timestamp','Email Address','First Name','Middle Name','Last Name','Nickname','Sex','Sector','Agency','Designation','Office Email','Contact No']);
        fclose($out);
    }
}