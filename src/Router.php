<?php
namespace App;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'handler' => $handler,
            'pattern' => $this->pathToPattern($path),
        ];
    }

    private function pathToPattern(string $path): string
    {
        $escaped = preg_quote($path, '#');
        $pattern = preg_replace('/\\\{(\w+)\\\}/', '(?P<$1>[^/]+)', $escaped);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $method, string $uri): void
    {
        // Strip query string and trailing slash
        $uri = strtok(parse_url($uri, PHP_URL_PATH) ?? '/', '?');
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $params  = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request = [
                'params' => $params,
                'body'   => json_decode(file_get_contents('php://input') ?: '{}', true) ?? [],
                'query'  => $_GET,
            ];

            ($route['handler'])($request);
            return;
        }

        Response::error('Not Found', 404);
    }
}
