<?php

namespace App\Core;

use App\Middleware\AuthenticateMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RequireRoleMiddleware;
use Dotenv\Dotenv;
use Throwable;

/**
 * Conteneur applicatif : amorçage (env, config, erreurs) puis dispatch de la requête
 * à travers la pile de middlewares et le contrôleur résolu par le routeur.
 */
final class App
{
    private Router $router;
    /** Middlewares globaux appliqués à toutes les routes API. */
    private array $globalMiddleware = ['cors', 'ratelimit'];

    public function __construct()
    {
        $this->boot();
        $this->router = new Router();
    }

    private function boot(): void
    {
        // 1. Variables d'environnement
        $envDir = base_path('config');
        if (is_file("$envDir/.env")) {
            Dotenv::createImmutable($envDir)->safeLoad();
        }

        // 2. Configuration
        Config::load(require base_path('config/config.php'));

        // 3. Fuseau horaire & locale
        date_default_timezone_set(config('app.timezone', 'Europe/Paris'));

        // 4. Gestion des erreurs
        $debug = (bool) config('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);
        set_exception_handler([$this, 'handleException']);
    }

    public function router(): Router
    {
        return $this->router;
    }

    /** Charge les définitions de routes depuis un fichier. */
    public function loadRoutes(string $file): void
    {
        $router = $this->router;
        require $file;
    }

    public function run(): void
    {
        try {
            $request = new Request();
            [$handler, $params, $routeMiddleware] = $this->router->match($request);
            $request->setParams($params);

            $middlewareChain = array_merge($this->globalMiddleware, $routeMiddleware);
            $response = $this->runPipeline($request, $middlewareChain, $handler);
            $response->send();
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Construit et exécute la pile de middlewares (pattern "onion"),
     * le contrôleur constituant le maillon final.
     */
    private function runPipeline(Request $request, array $middlewareNames, mixed $handler): Response
    {
        $core = fn(Request $req): Response => $this->dispatch($handler, $req);

        $pipeline = array_reduce(
            array_reverse($middlewareNames),
            function (callable $next, string $name): callable {
                $middleware = $this->resolveMiddleware($name);
                return fn(Request $req): Response => $middleware->handle($req, $next);
            },
            $core
        );

        return $pipeline($request);
    }

    private function resolveMiddleware(string $name): Middleware
    {
        // Support de "role:admin"
        if (str_starts_with($name, 'role:')) {
            return new RequireRoleMiddleware(substr($name, 5));
        }

        return match ($name) {
            'cors'          => new CorsMiddleware(),
            'ratelimit'     => new RateLimitMiddleware(),
            'auth'          => new AuthenticateMiddleware(true),
            'auth.optional' => new AuthenticateMiddleware(false),
            default         => throw new \RuntimeException("Middleware inconnu : $name"),
        };
    }

    /** Invoque le contrôleur ([Classe::class, 'methode']) ou une closure. */
    private function dispatch(mixed $handler, Request $request): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $result = $controller->$method($request);
        } elseif (is_callable($handler)) {
            $result = $handler($request);
        } else {
            throw new \RuntimeException('Handler de route invalide.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        // Tolérance : un contrôleur peut retourner un tableau => succès JSON
        return Response::success($result);
    }

    /** Gestionnaire global d'exceptions -> réponse JSON cohérente. */
    public function handleException(Throwable $e): void
    {
        if ($e instanceof HttpException) {
            $response = Response::error(
                $e->getMessage(),
                $e->getStatusCode(),
                $e->getErrors(),
                $e->getErrorCode()
            );
            $response->send();
            return;
        }

        logger($e->getMessage(), 'ERROR', [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ]);

        $debug = (bool) config('app.debug', false);
        $response = Response::error(
            $debug ? $e->getMessage() : 'Erreur interne du serveur',
            500,
            $debug ? ['file' => $e->getFile() . ':' . $e->getLine()] : [],
            'INTERNAL_ERROR'
        );
        $response->send();
    }
}
