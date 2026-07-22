<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Point de contrôle de l'état du service (utile pour le monitoring et la vérification du socle).
 */
final class HealthController
{
    public function index(Request $request): Response
    {
        $db = 'unknown';
        try {
            Database::selectOne('SELECT 1');
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'error';
        }

        return Response::success([
            'service'   => config('app.name'),
            'status'    => $db === 'ok' ? 'healthy' : 'degraded',
            'env'       => config('app.env'),
            'php'       => PHP_VERSION,
            'database'  => $db,
            'timestamp' => date('c'),
        ]);
    }
}
