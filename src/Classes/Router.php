<?php

declare(strict_types=1);

namespace App\Classes;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $callback): void
    {
        $this->routes['GET'][$path] = $callback;
    }

    public function post(string $path, callable|array $callback): void
    {
        $this->routes['POST'][$path] = $callback;
    }

    public function resolve(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Fix for subfolder deployment (e.g. localhost/ClutchData/public/index.php)
        // This is a simple fix; in production, document root is usually mapped directly.
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
        }
        if ($path === '') {
            $path = '/';
        }

        $callback = $this->routes[$method][$path] ?? false;

        if ($callback === false) {
            http_response_code(404);
            echo "404 - Not Found";
            return;
        }

        if (is_array($callback)) {
            $controller = new $callback[0]();
            $method = $callback[1];

            // Pass any query params or other data if needed
            call_user_func([$controller, $method]);
        } else {
            call_user_func($callback);
        }
    }
}
