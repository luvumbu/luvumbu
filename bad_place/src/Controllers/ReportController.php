<?php

namespace App\Controllers;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\GeocodingService;
use App\Services\MediaService;
use App\Services\OrganizationService;

/**
 * Signalements : création (réservée aux membres), consultation publique, détail.
 */
final class ReportController
{
    /** POST /api/v1/reports  (auth requise) */
    public function store(Request $request): Response
    {
        $userId = $request->userId();
        $in = $request->all();

        $data = Validator::make($in, [
            'org_name'    => 'required|string|min:2|max:255',
            'org_type'    => 'nullable|in:place,company,brand,online_service,other',
            'category_id' => 'required|integer',
            'description' => 'required|string|min:20|max:6000',
            'title'       => 'nullable|string|max:200',
            'city'        => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:20',
            'address'     => 'nullable|string|max:255',
            'country'     => 'nullable|string|max:100',
            'latitude'    => 'nullable|latitude',
            'longitude'   => 'nullable|longitude',
            'incident_date' => 'nullable|date',
            'is_anonymous'  => 'nullable|boolean',
        ])->validate();

        // --- Catégorie existante ---
        $categoryId = (int) $data['category_id'];
        if (!Database::selectOne('SELECT id FROM categories WHERE id = ? AND is_active = 1', [$categoryId])) {
            throw HttpException::validation(['category_id' => ['Catégorie invalide.']]);
        }

        // --- Motifs & types (choix multiples) ---
        $motifIds = self::intList($in['motifs'] ?? []);
        $typeIds  = self::intList($in['discrimination_types'] ?? []);
        if (!$motifIds) throw HttpException::validation(['motifs' => ['Sélectionnez au moins un motif.']]);
        if (!$typeIds)  throw HttpException::validation(['discrimination_types' => ['Sélectionnez au moins un type de discrimination.']]);
        self::assertExist('motifs', $motifIds, 'motifs');
        self::assertExist('discrimination_types', $typeIds, 'discrimination_types');

        // --- Coordonnées : géocodage si absentes mais adresse fournie ---
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        if ((!$lat || !$lng) && (!empty($data['address']) || !empty($data['city']))) {
            $geo = GeocodingService::geocode(
                (string) ($data['address'] ?? ''),
                $data['city'] ?? null,
                $data['postal_code'] ?? null,
                $data['country'] ?? 'France'
            );
            if ($geo) { $lat = $geo['lat']; $lng = $geo['lng']; }
        }

        $isAnonymous = self::boolVal($data['is_anonymous'] ?? false);
        $autoPublish = (bool) config('reports.auto_publish', false);
        $status = $autoPublish ? 'published' : 'pending';

        $user = $request->user();
        $reporterDisplay = $isAnonymous
            ? null
            : (trim((string) ($in['reporter_display'] ?? '')) ?: $user['display_name']);

        $result = Database::transaction(function () use (
            $data, $in, $userId, $categoryId, $motifIds, $typeIds,
            $lat, $lng, $isAnonymous, $status, $reporterDisplay, $request
        ) {
            // 1) Entité signalée
            $orgId = OrganizationService::findOrCreate([
                'name'        => $data['org_name'],
                'type'        => $data['org_type'] ?? 'place',
                'brand_name'  => $in['brand_name'] ?? null,
                'category_id' => $categoryId,
                'address'     => $data['address'] ?? null,
                'city'        => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'department'  => $in['department'] ?? null,
                'region'      => $in['region'] ?? null,
                'country'     => $data['country'] ?? 'France',
                'latitude'    => $lat,
                'longitude'   => $lng,
            ]);

            // 2) Signalement
            $uuid = str_uuid();
            $reportId = Database::insert(
                'INSERT INTO reports
                    (uuid, user_id, organization_id, category_id, title, description,
                     incident_date, incident_time, is_anonymous, reporter_display, status,
                     ip_hash, language, published_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, ' . ($status === 'published' ? 'NOW()' : 'NULL') . ')',
                [
                    $uuid, $userId, $orgId, $categoryId,
                    self::nz($in['title'] ?? null), $data['description'],
                    self::nz($data['incident_date'] ?? null), self::nz($in['incident_time'] ?? null),
                    $isAnonymous ? 1 : 0, $reporterDisplay, $status,
                    Crypto::pseudonymize($request->ip()), 'fr',
                ]
            );

            // 3) Pivots
            foreach ($motifIds as $mid) {
                Database::execute('INSERT IGNORE INTO report_motifs (report_id, motif_id) VALUES (?, ?)', [$reportId, $mid]);
            }
            foreach ($typeIds as $tid) {
                Database::execute('INSERT IGNORE INTO report_discrimination_types (report_id, type_id) VALUES (?, ?)', [$reportId, $tid]);
            }

            // 4) Pièces jointes
            $mediaCount = MediaService::handleUploads($request->files(), 'media', $reportId);

            // 5) Niveau d'activité de l'entité (si publié)
            if ($status === 'published') {
                OrganizationService::recomputeActivity($orgId);
            }

            return ['uuid' => $uuid, 'report_id' => $reportId, 'media' => $mediaCount, 'org_id' => $orgId];
        });

        return Response::success([
            'uuid'    => $result['uuid'],
            'status'  => $status,
            'message' => $status === 'published'
                ? 'Votre signalement a été publié.'
                : 'Votre signalement a été envoyé et sera publié après modération.',
        ], 201);
    }

    /** GET /api/v1/reports  (public, uniquement les publiés) */
    public function index(Request $request): Response
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 12)));
        $offset  = ($page - 1) * $perPage;

        $where = ["r.status = 'published'"];
        $params = [];

        if ($q = trim((string) $request->query('q', ''))) {
            $where[] = '(MATCH(r.title, r.description) AGAINST (? IN NATURAL LANGUAGE MODE) OR o.name LIKE ?)';
            $params[] = $q;
            $params[] = '%' . $q . '%';
        }
        if ($city = trim((string) $request->query('city', ''))) {
            $where[] = 'o.city = ?'; $params[] = $city;
        }
        if ($cat = (int) $request->query('category_id', 0)) {
            $where[] = 'r.category_id = ?'; $params[] = $cat;
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::selectOne(
            "SELECT COUNT(*) c FROM reports r JOIN organizations o ON o.id = r.organization_id WHERE $whereSql",
            $params
        )['c'] ?? 0);

        $rows = Database::select(
            "SELECT r.uuid, r.title, r.description, r.incident_date, r.is_anonymous, r.reporter_display,
                    r.similar_count, r.not_observed_count, r.comments_count, r.published_at,
                    o.name AS org_name, o.city, o.type AS org_type,
                    c.name AS category_name, cg.name AS group_name
             FROM reports r
             JOIN organizations o ON o.id = r.organization_id
             JOIN categories c ON c.id = r.category_id
             JOIN category_groups cg ON cg.id = c.group_id
             WHERE $whereSql
             ORDER BY r.published_at DESC, r.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $items = array_map([self::class, 'formatListItem'], $rows);

        return Response::success($items, 200, [
            'page' => $page, 'per_page' => $perPage, 'total' => $total,
            'pages' => (int) ceil($total / $perPage),
        ]);
    }

    /** GET /api/v1/reports/{uuid}  (public si publié, sinon propriétaire/modération) */
    public function show(Request $request): Response
    {
        $uuid = (string) $request->param('uuid');
        $r = Database::selectOne(
            "SELECT r.*, o.name AS org_name, o.uuid AS org_uuid, o.city, o.postal_code, o.address,
                    o.latitude, o.longitude, o.type AS org_type, o.brand_name, o.activity_level, o.reports_count,
                    c.name AS category_name, c.slug AS category_slug, cg.name AS group_name
             FROM reports r
             JOIN organizations o ON o.id = r.organization_id
             JOIN categories c ON c.id = r.category_id
             JOIN category_groups cg ON cg.id = c.group_id
             WHERE r.uuid = ? LIMIT 1",
            [$uuid]
        );

        if (!$r) {
            throw HttpException::notFound('Signalement introuvable.');
        }

        // Contrôle d'accès si non publié
        if ($r['status'] !== 'published') {
            $user = $request->user();
            $isOwner = $user && (int) $user['id'] === (int) $r['user_id'];
            $isStaff = $user && in_array($user['role'], ['moderator', 'admin'], true);
            if (!$isOwner && !$isStaff) {
                throw HttpException::notFound('Signalement introuvable.');
            }
        }

        $motifs = Database::select(
            'SELECT m.name FROM report_motifs rm JOIN motifs m ON m.id = rm.motif_id WHERE rm.report_id = ?',
            [$r['id']]
        );
        $types = Database::select(
            'SELECT t.name FROM report_discrimination_types rt JOIN discrimination_types t ON t.id = rt.type_id WHERE rt.report_id = ?',
            [$r['id']]
        );
        $media = Database::select(
            'SELECT uuid, type, original_name, width, height FROM report_media WHERE report_id = ?',
            [$r['id']]
        );

        return Response::success([
            'uuid'          => $r['uuid'],
            'title'         => $r['title'],
            'description'   => $r['description'],
            'incident_date' => $r['incident_date'],
            'incident_time' => $r['incident_time'],
            'status'        => $r['status'],
            'author'        => $r['is_anonymous'] ? 'Anonyme' : ($r['reporter_display'] ?: 'Utilisateur'),
            'is_anonymous'  => (bool) $r['is_anonymous'],
            'published_at'  => $r['published_at'],
            'created_at'    => $r['created_at'],
            'similar_count'      => (int) $r['similar_count'],
            'not_observed_count' => (int) $r['not_observed_count'],
            'comments_count'     => (int) $r['comments_count'],
            'category'      => ['name' => $r['category_name'], 'group' => $r['group_name'], 'slug' => $r['category_slug']],
            'organization'  => [
                'uuid' => $r['org_uuid'], 'name' => $r['org_name'], 'type' => $r['org_type'],
                'brand_name' => $r['brand_name'], 'city' => $r['city'], 'postal_code' => $r['postal_code'],
                'address' => $r['address'],
                'latitude' => $r['latitude'] !== null ? (float) $r['latitude'] : null,
                'longitude' => $r['longitude'] !== null ? (float) $r['longitude'] : null,
                'activity_level' => $r['activity_level'], 'reports_count' => (int) $r['reports_count'],
            ],
            'motifs'        => array_column($motifs, 'name'),
            'types'         => array_column($types, 'name'),
            'media'         => array_map(fn($m) => [
                'uuid' => $m['uuid'], 'type' => $m['type'], 'name' => $m['original_name'],
            ], $media),
        ]);
    }

    // ---------- Helpers ----------

    private static function formatListItem(array $r): array
    {
        $desc = (string) $r['description'];
        return [
            'uuid'        => $r['uuid'],
            'title'       => $r['title'],
            'excerpt'     => mb_strlen($desc) > 180 ? mb_substr($desc, 0, 180) . '…' : $desc,
            'author'      => $r['is_anonymous'] ? 'Anonyme' : ($r['reporter_display'] ?: 'Utilisateur'),
            'incident_date' => $r['incident_date'],
            'published_at'  => $r['published_at'],
            'similar_count' => (int) $r['similar_count'],
            'organization'  => ['name' => $r['org_name'], 'city' => $r['city'], 'type' => $r['org_type']],
            'category'      => ['name' => $r['category_name'], 'group' => $r['group_name']],
        ];
    }

    private static function intList(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== '');
        }
        if (!is_array($value)) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $value), fn($v) => $v > 0)));
        return $ids;
    }

    private static function assertExist(string $table, array $ids, string $field): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $found = (int) (Database::selectOne(
            "SELECT COUNT(*) c FROM `$table` WHERE id IN ($placeholders) AND is_active = 1",
            $ids
        )['c'] ?? 0);
        if ($found !== count($ids)) {
            throw HttpException::validation([$field => ['Sélection invalide.']]);
        }
    }

    private static function boolVal(mixed $v): bool
    {
        return in_array($v, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private static function nz(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);
        return ($v === null || $v === '') ? null : $v;
    }
}
