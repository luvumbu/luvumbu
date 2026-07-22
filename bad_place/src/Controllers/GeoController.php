<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\GeocodingService;

/**
 * Recherche d'adresses (autocomplétion) — proxy Nominatim côté serveur
 * (évite les problèmes CORS et applique le bon User-Agent / la politique d'usage).
 */
final class GeoController
{
    public function search(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 3) {
            return Response::success([]);
        }
        return Response::success(GeocodingService::search($q, 6));
    }
}
