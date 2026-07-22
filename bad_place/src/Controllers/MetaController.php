<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Données publiques légères pour alimenter la page d'accueil (compteurs).
 */
final class MetaController
{
    public function overview(Request $request): Response
    {
        return Response::success([
            'reports'    => (int) (Database::selectOne("SELECT COUNT(*) c FROM reports WHERE status = 'published'")['c'] ?? 0),
            'organizations' => (int) (Database::selectOne('SELECT COUNT(*) c FROM organizations')['c'] ?? 0),
            'categories' => (int) (Database::selectOne('SELECT COUNT(*) c FROM categories')['c'] ?? 0),
            'motifs'     => (int) (Database::selectOne('SELECT COUNT(*) c FROM motifs')['c'] ?? 0),
            'types'      => (int) (Database::selectOne('SELECT COUNT(*) c FROM discrimination_types')['c'] ?? 0),
        ]);
    }
}
