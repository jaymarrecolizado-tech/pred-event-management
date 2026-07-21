<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../src/Services/Database.php';
require __DIR__ . '/../src/Services/RateLimiter.php';

use App\Services\Database;
use App\Services\RateLimiter;

$pdo = Database::pdo();
$key = 'test:' . bin2hex(random_bytes(4));

RateLimiter::clear($key);

$first = RateLimiter::allow($key, 3, 60);
$second = RateLimiter::allow($key, 3, 60);
$third = RateLimiter::allow($key, 3, 60);
$fourth = RateLimiter::allow($key, 3, 60);

if (!$first || !$second || !$third || $fourth) {
    fwrite(STDERR, "allow() window test failed\n");
    exit(1);
}

RateLimiter::clear($key);
$c1 = RateLimiter::increment($key, 900);
$c2 = RateLimiter::increment($key, 900);
$locked = RateLimiter::isLocked($key, 3, 900);
if ($locked) {
    fwrite(STDERR, "isLocked() should be false at count 2 (got counts: $c1, $c2)\n");
    exit(1);
}
RateLimiter::increment($key, 900);
if (!RateLimiter::isLocked($key, 3, 900)) {
    fwrite(STDERR, "isLocked() should be true at count 3\n");
    exit(1);
}
RateLimiter::clear($key);
if (RateLimiter::isLocked($key, 3, 900)) {
    fwrite(STDERR, "clear() did not reset lock state\n");
    exit(1);
}

$stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
if (!$stmt->fetchColumn()) {
    fwrite(STDERR, "rate_limits table missing\n");
    exit(1);
}

echo "rate_limiter_ok\n";
