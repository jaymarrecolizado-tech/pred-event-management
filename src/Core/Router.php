<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;
use App\Services\Logger;

final class Router
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(string $routeName, string $method): void
    {
        $method = strtoupper($method);
        $route = $this->routes[$routeName] ?? null;
        if (!$route) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $handler = $route[$method] ?? null;
        if (!$handler) {
            $fallback = $route['_fallback'] ?? null;
            if (is_callable($fallback)) {
                $fallback();
                return;
            }
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        if (!$this->runGuards($route, $method, $routeName)) {
            return;
        }

        $this->invokeHandler($handler);
    }

    private function runGuards(array $route, string $method, string $routeName): bool
    {
        if (empty($route['_guards']) || !is_array($route['_guards'])) {
            return true;
        }

        foreach ($route['_guards'] as $guard) {
            if (!is_string($guard) || $guard === '') {
                continue;
            }

            if ($guard === 'staff') {
                $ok = !empty($_SESSION['staff']) || AuthService::hasRole(AuthService::ROLE_ADMIN, AuthService::ROLE_CHECKER);
                if (!$ok) {
                    http_response_code(403);
                    echo 'Forbidden';
                    return false;
                }
                continue;
            }

            // Legacy: any authenticated account
            if ($guard === 'admin' || $guard === 'auth') {
                if (!AuthService::check()) {
                    AuthService::deny($method);
                    return false;
                }
                continue;
            }

            if (str_starts_with($guard, 'role:')) {
                if (!AuthService::check()) {
                    AuthService::deny($method);
                    return false;
                }
                $roles = array_values(array_filter(array_map('trim', explode('|', substr($guard, 5)))));
                if ($roles && !AuthService::hasRole(...$roles)) {
                    try {
                        Logger::log(AuthService::id(), 'role_denied', [
                            'route' => $routeName,
                            'role' => AuthService::role(),
                            'required' => $roles,
                        ]);
                    } catch (\Throwable $e) {
                        // ignore logging failures
                    }
                    AuthService::deny($method);
                    return false;
                }
                continue;
            }
        }

        return true;
    }

    /**
     * @param callable|array<int,string|object> $handler
     */
    private function invokeHandler($handler): void
    {
        if (is_array($handler)) {
            $target = $handler[0] ?? null;
            $method = $handler[1] ?? null;
            if (is_string($target)) {
                $instance = new $target();
                $callable = [$instance, $method];
            } else {
                $callable = [$target, $method];
            }
            call_user_func($callable);
            return;
        }

        if (is_callable($handler)) {
            $handler();
            return;
        }

        throw new \RuntimeException('Invalid route handler');
    }
}
