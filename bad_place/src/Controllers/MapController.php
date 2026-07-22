<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Données de la carte : points d'entités signalées, colorés par niveau de gravité,
 * et couche thermique (heatmap). Ne renvoie que les signalements publiés.
 */
final class MapController
{
    /**
     * GET /api/v1/map/points
     * Filtres optionnels : bbox (min_lat,min_lng,max_lat,max_lng), category_id, city, level.
     */
    public function points(Request $request): Response
    {
        $where = [
            'o.latitude IS NOT NULL',
            'o.longitude IS NOT NULL',
            'o.reports_count > 0',
            "o.status = 'active'",
        ];
        $params = [];

        // Cadre visible (chargement par zone)
        $bbox = array_map('floatval', array_filter([
            $request->query('min_lat'), $request->query('min_lng'),
            $request->query('max_lat'), $request->query('max_lng'),
        ], fn($v) => $v !== null && $v !== ''));
        if (count($bbox) === 4) {
            $where[] = 'o.latitude BETWEEN ? AND ?';
            $where[] = 'o.longitude BETWEEN ? AND ?';
            $params[] = min($bbox[0], $bbox[2]); $params[] = max($bbox[0], $bbox[2]);
            $params[] = min($bbox[1], $bbox[3]); $params[] = max($bbox[1], $bbox[3]);
        }
        if ($cat = (int) $request->query('category_id', 0)) {
            $where[] = 'o.category_id = ?'; $params[] = $cat;
        }
        if ($city = trim((string) $request->query('city', ''))) {
            $where[] = 'o.city = ?'; $params[] = $city;
        }
        if (in_array($request->query('level'), ['low', 'medium', 'high'], true)) {
            $where[] = 'o.activity_level = ?'; $params[] = $request->query('level');
        }

        $whereSql = implode(' AND ', $where);

        $rows = Database::select(
            "SELECT o.uuid, o.name, o.city, o.type, o.brand_name,
                    o.latitude, o.longitude, o.reports_count, o.activity_level,
                    c.name AS category_name,
                    (SELECT r.uuid FROM reports r
                       WHERE r.organization_id = o.id AND r.status = 'published'
                       ORDER BY r.published_at DESC, r.id DESC LIMIT 1) AS report_uuid
             FROM organizations o
             LEFT JOIN categories c ON c.id = o.category_id
             WHERE $whereSql
             ORDER BY o.reports_count DESC
             LIMIT 5000",
            $params
        );

        $points = array_map(fn($r) => [
            'uuid'        => $r['uuid'],
            'report_uuid' => $r['report_uuid'],
            'name'        => $r['name'],
            'city'        => $r['city'],
            'type'        => $r['type'],
            'category'    => $r['category_name'],
            'lat'         => (float) $r['latitude'],
            'lng'         => (float) $r['longitude'],
            'count'       => (int) $r['reports_count'],
            'level'       => $r['activity_level'],
        ], $rows);

        return Response::success($points, 200, [
            'total' => count($points),
            'generated_at' => date('c'),
        ]);
    }

    /**
     * GET /api/v1/map/zones
     * Agrège les témoignages par ville. Une ville très signalée devient une "zone sous vigilance".
     */
    public function zones(Request $request): Response
    {
        $rows = Database::select(
            "SELECT o.city, o.department, o.region,
                    COUNT(*) AS places,
                    SUM(o.reports_count) AS total,
                    AVG(o.latitude) AS lat, AVG(o.longitude) AS lng
             FROM organizations o
             WHERE o.latitude IS NOT NULL AND o.longitude IS NOT NULL
               AND o.reports_count > 0 AND o.status = 'active' AND o.city <> ''
             GROUP BY o.city, o.department, o.region
             HAVING total > 0
             ORDER BY total DESC
             LIMIT 2000"
        );

        $medium = (int) config('reports.zone_medium_at', 5);
        $high   = (int) config('reports.zone_high_at', 15);

        $zones = array_map(function ($r) use ($medium, $high) {
            $total = (int) $r['total'];
            $level = $total >= $high ? 'high' : ($total >= $medium ? 'medium' : 'low');
            return [
                'city'       => $r['city'],
                'department' => $r['department'],
                'region'     => $r['region'],
                'lat'        => (float) $r['lat'],
                'lng'        => (float) $r['lng'],
                'places'     => (int) $r['places'],
                'total'      => $total,
                'level'      => $level,
                'under_watch' => $level !== 'low',
            ];
        }, $rows);

        return Response::success($zones, 200, [
            'total' => count($zones),
            'thresholds' => ['medium' => $medium, 'high' => $high],
            'under_watch' => count(array_filter($zones, fn($z) => $z['under_watch'])),
        ]);
    }

    /** GET /api/v1/map/heatmap → [[lat, lng, intensity], ...] */
    public function heatmap(Request $request): Response
    {
        $rows = Database::select(
            "SELECT latitude, longitude, reports_count
             FROM organizations
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND reports_count > 0 AND status = 'active'
             LIMIT 10000"
        );

        $heat = array_map(fn($r) => [
            (float) $r['latitude'],
            (float) $r['longitude'],
            (int) $r['reports_count'],
        ], $rows);

        return Response::success($heat);
    }
}
