<?php
namespace App\Core;

/**
 * Routeur minimaliste.
 * On enregistre des routes (méthode + motif) et le callback à exécuter.
 * Supporte les paramètres dynamiques : /pets/{id}/feed
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler');
    }

    public function get(string $p, callable $h): void  { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }
    public function put(string $p, callable $h): void  { $this->add('PUT', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(string $method, string $uri): void
    {
        // On isole le chemin après /api
        $path = parse_url($uri, PHP_URL_PATH);
        $path = preg_replace('#^.*/api#', '', $path);
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            // Transforme {id} en groupe de capture
            $regex = '#^' . preg_replace('#\{([a-z]+)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';

            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        Response::error('Route not found: ' . $method . ' ' . $path, 404);
    }
}
