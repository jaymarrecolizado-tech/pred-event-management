<?php
declare(strict_types=1);

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Set to 1 for debugging

try {
    // Load bootstrap - try multiple paths
    $bootstrapPaths = [
        __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php',
    ];

    $bootstrapLoaded = false;
    foreach ($bootstrapPaths as $bootstrapPath) {
        if (file_exists($bootstrapPath)) {
            require $bootstrapPath;
            $bootstrapLoaded = true;
            break;
        }
    }

    if (!$bootstrapLoaded) {
        throw new Exception('Bootstrap file not found');
    }

    $uuid = isset($_GET['uuid']) ? (string)$_GET['uuid'] : '';
    if ($uuid === '') {
        http_response_code(400);
        echo 'Missing UUID';
        exit;
    }

    // Check session permission (relaxed for testing)
    $allowed = false;
    if (isset($_SESSION['qr_allowed'][$uuid])) {
        $allowed = true;
    } elseif (getenv('APP_DEBUG') === 'true') {
        // Allow in debug mode for testing
        $allowed = true;
    } else {
        // Also allow if we can verify the UUID exists in database
        try {
            // Load autoloader
            if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
                require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
            }
            
            // Register autoloader
            spl_autoload_register(function($class){
                $prefix = 'App\\';
                $base = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
                if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
                $rel = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
                $file = $base . $rel . '.php';
                if (is_file($file)) require $file;
            });
            
            $pdo = \App\Services\Database::pdo();
            $stmt = $pdo->prepare('SELECT uuid FROM participants WHERE uuid = ?');
            $stmt->execute([$uuid]);
            if ($stmt->fetch()) {
                $allowed = true;
            }
        } catch (Exception $e) {
            // If database check fails, deny access
            $allowed = false;
        }
    }

    if (!$allowed) {
        http_response_code(403);
        if (getenv('APP_DEBUG') === 'true') {
            echo 'Forbidden - Session check failed. UUID: ' . htmlspecialchars($uuid, ENT_QUOTES);
        } else {
            echo 'Forbidden';
        }
        exit;
    }

    // QrService saves to: project_root/storage/qrcodes/{first2chars}/{uuid}.png
    // On Hostinger, project root is public_html, so path is: __DIR__/storage/qrcodes/...
    $shard = substr($uuid, 0, 2);
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'qrcodes' . DIRECTORY_SEPARATOR . $shard . DIRECTORY_SEPARATOR . $uuid . '.png';

    if (!is_file($path)) {
        // Debug mode: show diagnostic info
        if (getenv('APP_DEBUG') === 'true') {
            http_response_code(404);
            header('Content-Type: text/html');
            echo '<!DOCTYPE html><html><head><title>QR Code Not Found</title><style>body{font-family:Arial;padding:20px;}code{background:#f4f4f4;padding:2px 6px;}</style></head><body>';
            echo '<h1>QR Code Not Found</h1>';
            echo '<p><strong>UUID:</strong> <code>' . htmlspecialchars($uuid, ENT_QUOTES) . '</code></p>';
            echo '<p><strong>Expected Path:</strong> <code>' . htmlspecialchars($path, ENT_QUOTES) . '</code></p>';
            echo '<p><strong>File Exists:</strong> ' . (is_file($path) ? 'YES ✅' : 'NO ❌') . '</p>';
            echo '<p><strong>Directory Exists:</strong> ' . (is_dir(dirname($path)) ? 'YES ✅' : 'NO ❌') . '</p>';
            echo '<p><strong>Storage Base:</strong> ' . (is_dir(__DIR__ . '/storage') ? 'YES ✅' : 'NO ❌') . '</p>';
            echo '<p><strong>Storage QR Codes:</strong> ' . (is_dir(__DIR__ . '/storage/qrcodes') ? 'YES ✅' : 'NO ❌') . '</p>';
            echo '<p><strong>Shard Directory:</strong> ' . (is_dir(__DIR__ . '/storage/qrcodes/' . $shard) ? 'YES ✅' : 'NO ❌') . '</p>';
            
            // List files in shard directory
            $shardDir = __DIR__ . '/storage/qrcodes/' . $shard;
            if (is_dir($shardDir)) {
                $files = scandir($shardDir);
                echo '<p><strong>Files in shard directory:</strong></p><ul>';
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        echo '<li>' . htmlspecialchars($file) . '</li>';
                    }
                }
                echo '</ul>';
            }
            
            echo '</body></html>';
            exit;
        }
        
        http_response_code(404);
        echo 'QR Code Not Found';
        exit;
    }

    // Output the image
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="qr-' . htmlspecialchars($uuid, ENT_QUOTES) . '.png"');
    header('Cache-Control: public, max-age=31536000');
    readfile($path);
    exit;

} catch (Throwable $e) {
    // Error handling
    error_log('QR Code Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    http_response_code(500);
    if (getenv('APP_DEBUG') === 'true') {
        header('Content-Type: text/html');
        echo '<!DOCTYPE html><html><head><title>QR Code Error</title><style>body{font-family:Arial;padding:20px;}code{background:#f4f4f4;padding:2px 6px;}</style></head><body>';
        echo '<h1>QR Code Error</h1>';
        echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES) . '</p>';
        echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES) . '</pre>';
        echo '</body></html>';
    } else {
        echo 'Internal Server Error';
    }
    exit;
}





// // // <?php
// // // declare(strict_types=1);

// // // require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

// // // $uuid = isset($_GET['uuid']) ? (string)$_GET['uuid'] : '';
// // // if ($uuid === '' || !isset($_SESSION['qr_allowed'][$uuid])) {
// // //     http_response_code(403);
// // //     echo 'Forbidden';
// // //     exit;
// // // }

// // // $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'qrcodes' . DIRECTORY_SEPARATOR . substr($uuid,0,2) . DIRECTORY_SEPARATOR . $uuid . '.png';
// // // if (!is_file($path)) {
// // //     http_response_code(404);
// // //     echo 'Not Found';
// // //     exit;
// // // }

// // // header('Content-Type: image/png');
// // // header('Content-Disposition: inline; filename="qr.png"');
// // // readfile($path);


// // <?php
// // declare(strict_types=1);

// // require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

// // $uuid = isset($_GET['uuid']) ? (string)$_GET['uuid'] : '';
// // if ($uuid === '' || !isset($_SESSION['qr_allowed'][$uuid])) {
// //     http_response_code(403);
// //     echo 'Forbidden';
// //     exit;
// // }

// // $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'qrcodes' . DIRECTORY_SEPARATOR . substr($uuid,0,2) . DIRECTORY_SEPARATOR . $uuid . '.png';
// // if (!is_file($path)) {
// //     http_response_code(404);
// //     echo 'Not Found';
// //     exit;
// // }

// // header('Content-Type: image/png');
// // header('Content-Disposition: inline; filename="qr.png"');
// // readfile($path);




// <?php
// declare(strict_types=1);

// // Load bootstrap - try multiple paths
// $bootstrapPaths = [
//     __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php',
//     dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php',
// ];

// $bootstrapLoaded = false;
// foreach ($bootstrapPaths as $bootstrapPath) {
//     if (file_exists($bootstrapPath)) {
//         require $bootstrapPath;
//         $bootstrapLoaded = true;
//         break;
//     }
// }

// if (!$bootstrapLoaded) {
//     http_response_code(500);
//     echo 'Bootstrap not found';
//     exit;
// }

// $uuid = isset($_GET['uuid']) ? (string)$_GET['uuid'] : '';
// if ($uuid === '') {
//     http_response_code(400);
//     echo 'Missing UUID';
//     exit;
// }

// // Check session permission
// $allowed = false;
// if (isset($_SESSION['qr_allowed'][$uuid])) {
//     $allowed = true;
// } elseif (getenv('APP_DEBUG') === 'true') {
//     // Allow in debug mode for testing
//     $allowed = true;
// }

// if (!$allowed) {
//     http_response_code(403);
//     if (getenv('APP_DEBUG') === 'true') {
//         echo 'Forbidden - Session check failed. UUID: ' . htmlspecialchars($uuid, ENT_QUOTES);
//     } else {
//         echo 'Forbidden';
//     }
//     exit;
// }

// // QrService saves to: project_root/storage/qrcodes/{first2chars}/{uuid}.png
// // On Hostinger, project root is public_html, so path is: __DIR__/storage/qrcodes/...
// $shard = substr($uuid, 0, 2);
// $path = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'qrcodes' . DIRECTORY_SEPARATOR . $shard . DIRECTORY_SEPARATOR . $uuid . '.png';

// if (!is_file($path)) {
//     // Debug mode: show diagnostic info
//     if (getenv('APP_DEBUG') === 'true') {
//         http_response_code(404);
//         echo '<!DOCTYPE html><html><head><title>QR Code Not Found</title><style>body{font-family:Arial;padding:20px;}code{background:#f4f4f4;padding:2px 6px;}</style></head><body>';
//         echo '<h1>QR Code Not Found</h1>';
//         echo '<p><strong>UUID:</strong> <code>' . htmlspecialchars($uuid, ENT_QUOTES) . '</code></p>';
//         echo '<p><strong>Expected Path:</strong> <code>' . htmlspecialchars($path, ENT_QUOTES) . '</code></p>';
//         echo '<p><strong>File Exists:</strong> ' . (is_file($path) ? 'YES ✅' : 'NO ❌') . '</p>';
//         echo '<p><strong>Directory Exists:</strong> ' . (is_dir(dirname($path)) ? 'YES ✅' : 'NO ❌') . '</p>';
//         echo '<p><strong>Storage Base:</strong> ' . (is_dir(__DIR__ . '/storage') ? 'YES ✅' : 'NO ❌') . '</p>';
//         echo '<p><strong>Storage QR Codes:</strong> ' . (is_dir(__DIR__ . '/storage/qrcodes') ? 'YES ✅' : 'NO ❌') . '</p>';
//         echo '<p><strong>Shard Directory:</strong> ' . (is_dir(__DIR__ . '/storage/qrcodes/' . $shard) ? 'YES ✅' : 'NO ❌') . '</p>';
        
//         // List files in shard directory
//         $shardDir = __DIR__ . '/storage/qrcodes/' . $shard;
//         if (is_dir($shardDir)) {
//             $files = scandir($shardDir);
//             echo '<p><strong>Files in shard directory:</strong></p><ul>';
//             foreach ($files as $file) {
//                 if ($file !== '.' && $file !== '..') {
//                     echo '<li>' . htmlspecialchars($file) . '</li>';
//                 }
//             }
//             echo '</ul>';
//         }
        
//         echo '</body></html>';
//         exit;
//     }
    
//     http_response_code(404);
//     echo 'QR Code Not Found';
//     exit;
// }

// header('Content-Type: image/png');
// header('Content-Disposition: inline; filename="qr-' . htmlspecialchars($uuid, ENT_QUOTES) . '.png"');
// header('Cache-Control: public, max-age=31536000');
// readfile($path);
