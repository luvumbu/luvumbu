<?php

namespace App\Core;

/**
 * Encapsule la requête HTTP entrante (méthode, chemin, en-têtes, corps JSON, query, fichiers).
 */
final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;
    private array $headers;
    private array $files;
    /** Paramètres de route extraits par le routeur (ex: {id}). */
    private array $params = [];
    /** Utilisateur authentifié attaché par le middleware Auth. */
    private ?array $user = null;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path    = $this->resolvePath();
        $this->query   = $_GET;
        $this->files   = $_FILES;
        $this->headers = $this->resolveHeaders();
        $this->body    = $this->resolveBody();
    }

    private function resolvePath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = '/' . ltrim(rawurldecode($uri), '/');

        // Détermine le préfixe de sous-dossier (déploiement XAMPP, ex: /bad_place).
        // Priorité à APP_BASE_PATH ; sinon auto-détection via SCRIPT_NAME.
        $base = trim((string) env('APP_BASE_PATH', ''), '/');
        if ($base === '') {
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $scriptDir = preg_replace('#/public$#', '', (string) $scriptDir); // .../bad_place/public -> .../bad_place
            $base = trim((string) $scriptDir, '/');
        }

        $trimmed = ltrim($uri, '/');
        if ($base !== '' && ($trimmed === $base || str_starts_with($trimmed, $base . '/'))) {
            $uri = '/' . ltrim(substr($trimmed, strlen($base)), '/');
        }

        // Retire un éventuel /public résiduel (accès direct à public/)
        $uri = preg_replace('#^/public(?=/|$)#', '', $uri);

        return '/' . trim($uri, '/');
    }

    private function resolveHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        // Récupération robuste de l'en-tête Authorization (souvent filtré par Apache)
        if (empty($headers['Authorization'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? $_SERVER['HTTP_AUTHORIZATION']
                ?? null;
            if (!$auth && function_exists('apache_request_headers')) {
                $apache = apache_request_headers();
                foreach ($apache as $k => $v) {
                    if (strcasecmp($k, 'Authorization') === 0) {
                        $auth = $v;
                        break;
                    }
                }
            }
            if ($auth) {
                $headers['Authorization'] = $auth;
            }
        }

        return $headers;
    }

    private function resolveBody(): array
    {
        $contentType = $this->headers['Content-Type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new HttpException('Corps JSON invalide', 400, [], 'INVALID_JSON');
            }
            return is_array($decoded) ? $decoded : [];
        }

        // multipart/form-data ou x-www-form-urlencoded
        return $_POST ?: [];
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[ucwords(strtolower($name), '-')] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /** Fusion body + query, utile pour la validation. */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization', '');
        if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user['id'] ?? null;
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
