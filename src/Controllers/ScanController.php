<?php
declare(strict_types=1);

namespace App\Controllers;

class ScanController
{
    public function show(): void
    {
        $_SESSION['staff'] = true;
        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'scan.php';
    }
}