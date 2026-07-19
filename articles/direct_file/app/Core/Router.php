<?php
/**
 * Routeur minimaliste : associe (méthode HTTP + chemin) à un couple
 * [Contrôleur, action]. Correspondance exacte sur le chemin.
 */
class Router
{
    /** @var array<int, array{0:string,1:string,2:array}> */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, array $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    /**
     * Dispatch la requête. $path est déjà normalisé par le front controller
     * (sans sous-dossier, sans suffixe .php, ex: "/chat").
     */
    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$m, $p, $handler]) {
            if ($m === $method && $p === $path) {
                [$class, $action] = $handler;
                (new $class())->$action();
                return;
            }
        }
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1 style="font-family:sans-serif">404 — Page introuvable</h1>';
        echo '<p style="font-family:sans-serif"><a href="' . e(base_url()) . '">← Accueil</a></p>';
    }
}
