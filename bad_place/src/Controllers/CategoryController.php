<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Référentiel public des catégories (groupes + sous-catégories) et autres taxonomies.
 */
final class CategoryController
{
    public function index(Request $request): Response
    {
        $groups = Database::select(
            'SELECT id, name, slug, icon FROM category_groups WHERE is_active = 1 ORDER BY position, name'
        );
        $categories = Database::select(
            'SELECT id, group_id, name, slug FROM categories WHERE is_active = 1 ORDER BY position, name'
        );

        $byGroup = [];
        foreach ($categories as $c) {
            $byGroup[$c['group_id']][] = ['id' => (int) $c['id'], 'name' => $c['name'], 'slug' => $c['slug']];
        }

        $result = array_map(fn($g) => [
            'id'         => (int) $g['id'],
            'name'       => $g['name'],
            'slug'       => $g['slug'],
            'icon'       => $g['icon'],
            'categories' => $byGroup[$g['id']] ?? [],
        ], $groups);

        return Response::success($result);
    }

    public function motifs(Request $request): Response
    {
        return Response::success(
            Database::select('SELECT id, name, slug FROM motifs WHERE is_active = 1 ORDER BY position, name')
        );
    }

    public function discriminationTypes(Request $request): Response
    {
        return Response::success(
            Database::select('SELECT id, name, slug FROM discrimination_types WHERE is_active = 1 ORDER BY position, name')
        );
    }
}
