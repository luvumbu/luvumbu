<?php

namespace App\Core;

/**
 * Routeur REST minimaliste avec paramètres nommés ({id}) et middlewares par route.
 *
 * Exemple :
 *   $router->get('/reports/{id}', [ReportController::class, 'show']);
 *   $router->post('/reports', [ReportController::class, 'store'])->middleware('auth');
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:string[],handler:mixed,middleware:string[]}> */
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $prefix = '';

    public function get(string $path, mixed $handler): RouteRegistration
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): RouteRegistration
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): RouteRegistration
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, mixed $handler): RouteRegistration
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, mixed $handler): RouteRegistration
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Groupe de routes partageant un préfixe et/ou des middlewares.
     */
    public function group(array $options, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMw = $this->groupMiddleware;

        $this->prefix .= $options['prefix'] ?? '';
        if (!empty($options['middleware'])) {
            $this->groupMiddleware = array_merge($this->groupMiddleware, (array) $options['middleware']);
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMw;
    }

    private function add(string $method, string $path, mixed $handler): RouteRegistration
    {
        $fullPath = '/' . trim($this->prefix . $path, '/');
        $params = [];

        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $fullPath);

        $index = count($this->routes);
        $this->routes[$index] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $this->groupMiddleware,
        ];

        return new RouteRegistration($this->routes[$index]['middleware']);
    }

    /**
     * Résout une requête vers une route. Retourne [handler, params, middleware].
     * @throws HttpException 404 si aucune route, 405 si méthode non autorisée.
     */
    public function match(Request $request): array
    {
        $path = $request->path();
        $method = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $pathMatched = true;
                if ($route['method'] === $method) {
                    $params = [];
                    foreach ($route['params'] as $name) {
                        $params[$name] = $matches[$name] ?? null;
                    }
                    return [$route['handler'], $params, $route['middleware']];
                }
            }
        }

        if ($pathMatched) {
            throw new HttpException('Méthode non autorisée', 405, [], 'METHOD_NOT_ALLOWED');
        }
        throw HttpException::notFound('Endpoint introuvable');
    }
}

/**
 * Petit objet fluide pour attacher des middlewares à une route après déclaration.
 */
final class RouteRegistration
{
    public function __construct(private array &$middleware) {}

    public function middleware(string ...$names): self
    {
        foreach ($names as $name) {
            $this->middleware[] = $name;
        }
        return $this;
    }
}
