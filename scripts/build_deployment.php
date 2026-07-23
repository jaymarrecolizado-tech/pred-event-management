<?php
declare(strict_types=1);

/**
 * Build DEPLOYMENT/ folder with files to upload to the live server.
 * Run: C:\xampp\php\php.exe scripts\build_deployment.php
 */

$root = dirname(__DIR__);
$dest = $root . DIRECTORY_SEPARATOR . 'DEPLOYMENT';

function rmTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($dir);
}

function copyTree(string $src, string $dst): void
{
    if (!is_dir($src)) {
        return;
    }
    if (!is_dir($dst)) {
        mkdir($dst, 0775, true);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $target = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0775, true);
            }
        } else {
            $parent = dirname($target);
            if (!is_dir($parent)) {
                mkdir($parent, 0775, true);
            }
            if (str_ends_with(strtolower($item->getFilename()), '.sh')) {
                $body = str_replace(["\r\n", "\r"], "\n", (string)file_get_contents($item->getPathname()));
                file_put_contents($target, $body);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }
}

echo "Building DEPLOYMENT package...\n";
rmTree($dest);
mkdir($dest, 0775, true);

foreach (['assets', 'ca', 'config', 'migrations', 'scripts', 'src', 'views'] as $dir) {
    copyTree($root . DIRECTORY_SEPARATOR . $dir, $dest . DIRECTORY_SEPARATOR . $dir);
    echo "  + {$dir}/\n";
}

if (is_dir($root . DIRECTORY_SEPARATOR . 'vendor')) {
    copyTree($root . DIRECTORY_SEPARATOR . 'vendor', $dest . DIRECTORY_SEPARATOR . 'vendor');
    echo "  + vendor/\n";
}

foreach (['index.php', 'qrcode.php', 'signature.php', '.htaccess', 'composer.json', 'composer.lock', 'post_deploy.sh', 'set_permissions.sh', '.env.example'] as $file) {
    $from = $root . DIRECTORY_SEPARATOR . $file;
    if (is_file($from)) {
        $to = $dest . DIRECTORY_SEPARATOR . $file;
        if (str_ends_with($file, '.sh')) {
            // Force Unix LF so bash on Linux does not choke on CRLF.
            $body = str_replace("\r\n", "\n", (string)file_get_contents($from));
            $body = str_replace("\r", "\n", $body);
            file_put_contents($to, $body);
        } else {
            copy($from, $to);
        }
        echo "  + {$file}\n";
    }
}

// Production env for upload (from .env.production — never use local .env secrets accidentally)
$envProd = $root . DIRECTORY_SEPARATOR . '.env.production';
if (is_file($envProd)) {
    copy($envProd, $dest . DIRECTORY_SEPARATOR . '.env');
    echo "  + .env (from .env.production)\n";
} else {
    echo "  ! missing .env.production — create it before uploading\n";
}

// Storage stubs only
$storageDirs = ['signatures', 'certificates', 'coa_signatories', 'qrcodes', 'imports', 'reports', 'runtime'];
foreach ($storageDirs as $sd) {
    $p = $dest . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $sd;
    if (!is_dir($p)) {
        mkdir($p, 0775, true);
    }
}
$storageFiles = [
    'storage/.htaccess',
    'storage/signatures/index.php',
    'storage/certificates/index.php',
    'storage/coa_signatories/index.php',
    'storage/imports/index.php',
    'storage/imports/.htaccess',
    'storage/qrcodes/.gitkeep',
];
foreach ($storageFiles as $rel) {
    $from = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $to = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($from)) {
        $parent = dirname($to);
        if (!is_dir($parent)) {
            mkdir($parent, 0775, true);
        }
        copy($from, $to);
    }
}
echo "  + storage/ (stubs only)\n";

# Remove local-only / dangerous junk if copied
$remove = [
    'scripts/dump_coa_img_meta.php',
    'scripts/extract_coa_images.php',
    'scripts/inspect_coa_template.php',
    'scripts/build_deployment.php',
    'config/.env',
];
foreach ($remove as $rel) {
    $p = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($p)) {
        unlink($p);
        echo "  - removed {$rel}\n";
    }
}

$readme = <<<TXT
DEPLOYMENT PACKAGE — digitalhero.dictr2.cloud
=============================================
Upload EVERYTHING inside this folder into the live document root
(example: public_html).

Includes:
- Application code, vendor/, assets/ (including CoA + registration banner)
- .env configured for database dghero / user dgherouser
- APP_URL=https://digitalhero.dictr2.cloud

IMPORTANT
1. If the server already has a working .env, back it up first.
2. After upload, set permissions: chmod 600 .env
3. SSH and run: bash post_deploy.sh
4. If admin login fails because tables are empty:
     php scripts/seed_admin.php admin 'YourStrongPasswordHere'

Do not leave diagnose/create_admin scripts on the server.
TXT;
file_put_contents($dest . DIRECTORY_SEPARATOR . 'README_UPLOAD.txt', $readme);

$count = 0;
$size = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile()) {
        $count++;
        $size += $f->getSize();
    }
}

echo "Done.\n";
echo "Folder: {$dest}\n";
echo "Files: {$count}\n";
echo 'Size: ' . round($size / 1048576, 1) . " MB\n";
