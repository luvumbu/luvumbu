<?php

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Contrôle d'accès basé sur les rôles (RBAC). À placer après AuthenticateMiddleware.
 * Hiérarchie : visitor < member < moderator < admin.
 */
final class RequireRoleMiddleware implements Middleware
{
    private const HIERARCHY = ['visitor' => 0, 'member' => 1, 'moderator' => 2, 'admin' => 3];

    public function __construct(private string $minRole = 'member') {}

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user();
        if (!$user) {
            throw HttpException::unauthorized();
        }

        $userLevel = self::HIERARCHY[$user['role']] ?? 0;
        $needed    = self::HIERARCHY[$this->minRole] ?? 99;

        if ($userLevel < $needed) {
            throw HttpException::forbidden('Privilèges insuffisants');
        }

        return $next($request);
    }
}
