<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Jwt;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Exige un access token JWT valide. Charge l'utilisateur et l'attache à la requête.
 */
final class AuthenticateMiddleware implements Middleware
{
    public function __construct(private bool $required = true) {}

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            if ($this->required) {
                throw HttpException::unauthorized('Jeton d\'accès manquant');
            }
            return $next($request);
        }

        $payload = Jwt::decode($token);

        if (($payload['type'] ?? null) !== 'access') {
            throw HttpException::unauthorized('Type de jeton invalide');
        }

        $user = Database::selectOne(
            'SELECT id, uuid, email, display_name, role, status, avatar_url FROM users WHERE id = ? LIMIT 1',
            [$payload['sub']]
        );

        if (!$user || $user['status'] === 'banned' || $user['status'] === 'deleted') {
            throw HttpException::unauthorized('Compte inaccessible');
        }

        $request->setUser($user);
        return $next($request);
    }
}
