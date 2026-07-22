<?php

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Limiteur de débit simple basé sur des fichiers (fenêtre glissante par IP).
 * Suffisant pour XAMPP / mono-serveur ; remplaçable par Redis en production.
 */
final class RateLimitMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $max    = (int) config('rate_limit.max', 120);
        $window = (int) config('rate_limit.window', 60);

        $dir = storage_path('cache/ratelimit');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $key  = sha1($request->ip() . '|' . $request->path());
        $file = "$dir/$key.json";
        $now  = time();

        $entry = ['count' => 0, 'reset' => $now + $window];
        if (is_file($file)) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded) && ($decoded['reset'] ?? 0) > $now) {
                $entry = $decoded;
            }
        }

        $entry['count']++;
        @file_put_contents($file, json_encode($entry), LOCK_EX);

        $remaining = max(0, $max - $entry['count']);

        if ($entry['count'] > $max) {
            $retry = max(1, $entry['reset'] - $now);
            $response = Response::error('Trop de requêtes, réessayez plus tard.', 429, [], 'RATE_LIMITED');
            return $response
                ->header('Retry-After', (string) $retry)
                ->header('X-RateLimit-Limit', (string) $max)
                ->header('X-RateLimit-Remaining', '0');
        }

        $response = $next($request);
        return $response
            ->header('X-RateLimit-Limit', (string) $max)
            ->header('X-RateLimit-Remaining', (string) $remaining);
    }
}
