<?php
declare(strict_types=1);

namespace App\Controllers;

final class AdminExportPageController
{
    public function index(): void
    {
        if (empty($_SESSION['admin_id'])) {
            header('Location: ?r=admin_login');
            return;
        }

        require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin_export.php';
    }
}

