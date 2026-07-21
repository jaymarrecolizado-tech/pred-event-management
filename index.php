<?php
/**
 * ISSP Solo - Main Entry Point
 * Hostinger Deployment Version
 * 
 * This file is in the root (public_html) directory
 */

declare(strict_types=1);

// Bootstrap configuration (must be first)
require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

// Custom autoloader for App namespace (register before use statements)
spl_autoload_register(function($class){
    $prefix = 'App\\';
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $rel = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = $base . $rel . '.php';
    if (is_file($file)) require $file;
});

// Composer autoloader
if (is_file(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

// Now we can use the Router class
use App\Core\Router;

try {
    // Load routes and dispatch
    $routes = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.php';
    $router = new Router($routes);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $routeName = trim((string)($_GET['r'] ?? ''));
    if ($routeName === '') {
        $routeName = 'register';
        if ($method === 'GET' && !isset($_GET['r'])) {
            header('Location: ?r=register');
            exit;
        }
    }
    $router->dispatch($routeName, $method);
} catch (Throwable $e) {
    // Error handling for production
    error_log('ISSP Solo Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // Show user-friendly error page
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Application Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; }
            h1 { color: #dc3545; }
        </style>
    </head>
    <body>
        <h1>Application Error</h1>
        <div class="error">
            <p><strong>An error occurred while processing your request.</strong></p>
            <p>Please contact the administrator if this problem persists.</p>
        </div>
        <?php if (getenv('APP_DEBUG') === 'true'): ?>
        <div style="margin-top: 20px; padding: 15px; background: #f4f4f4; border-radius: 5px;">
            <strong>Debug Information:</strong><br>
            <pre><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></pre>
            <pre><?= htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}
