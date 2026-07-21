<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Validator.php';

echo (\App\Services\Validator::email('a@b.com') ? 'email_ok' : 'email_fail'), "\n";
echo (!\App\Services\Validator::email('no_at') ? 'email_bad_ok' : 'email_bad_fail'), "\n";
echo (\App\Services\Validator::required('X') ? 'req_ok' : 'req_fail'), "\n";
echo (!\App\Services\Validator::required('') ? 'req_bad_ok' : 'req_bad_fail'), "\n";