<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';

\App\Services\Database::migrate();
echo "Database migrations executed successfully.\n";