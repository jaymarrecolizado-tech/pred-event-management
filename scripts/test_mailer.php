<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Mailer.php';

\App\Services\Mailer::send('test@example.com','Test','Body');
echo "ok";