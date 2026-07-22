<?php

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Gère les en-têtes CORS et les requêtes préliminaires OPTIONS.
 * Autorise les origines déclarées dans CORS_ALLOWED_ORIGINS (dont l'extension Chrome).
 */
final class CorsMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $origin  = $request->header('Origin', '');
        $allowed = config('cors.allowed_origins', []);

        $allowOrigin = null;
        if ($origin && in_array($origin, $allowed, true)) {
            $allowOrigin = $origin;
        } elseif ($origin && str_starts_with($origin, 'chrome-extension://')) {
            // Autorise les extensions Chrome (téléchargées hors store)
            $allowOrigin = $origin;
        }

        $headers = [];
        if ($allowOrigin) {
            $headers['Access-Control-Allow-Origin']      = $allowOrigin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
            $headers['Vary']                             = 'Origin';
            $headers['Access-Control-Allow-Methods']     = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
            $headers['Access-Control-Allow-Headers']     = 'Content-Type, Authorization, X-Requested-With';
            $headers['Access-Control-Max-Age']           = '86400';
        }

        // Requête préliminaire : on répond immédiatement
        if ($request->method() === 'OPTIONS') {
            $response = new Response(null, 204);
            foreach ($headers as $k => $v) {
                $response->header($k, $v);
            }
            return $response;
        }

        $response = $next($request);
        foreach ($headers as $k => $v) {
            $response->header($k, $v);
        }
        return $response;
    }
}
