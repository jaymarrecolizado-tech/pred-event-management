<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/post_deploy.sh',
    $root . '/set_permissions.sh',
    $root . '/DEPLOYMENT/post_deploy.sh',
    $root . '/DEPLOYMENT/set_permissions.sh',
];

foreach ($files as $path) {
    if (!is_file($path)) {
        echo "skip missing {$path}\n";
        continue;
    }
    $raw = file_get_contents($path);
    $fixed = str_replace(["\r\n", "\r"], "\n", (string)$raw);
    file_put_contents($path, $fixed);
    $hasCr = str_contains($fixed, "\r") ? 'HAS_CR' : 'LF_OK';
    echo basename(dirname($path)) . '/' . basename($path) . " {$hasCr} bytes=" . strlen($fixed) . PHP_EOL;
}
