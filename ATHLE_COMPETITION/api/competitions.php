<?php
/**
 * API de lecture : renvoie les compétitions filtrées, les villes qui les
 * portent (avec leurs coordonnées) et quelques compteurs.
 *
 * GET api/competitions.php
 *   from=YYYY-MM-DD    borne de début (défaut : aujourd'hui)
 *   to=YYYY-MM-DD      borne de fin
 *   env=in|out|all     indoor / outdoor
 *   event=<clé>        ne garde que les compétitions proposant cette discipline
 *   country=BE         code pays
 *   city=<id>          restreint à une ville
 *   q=<texte>          recherche sur l'intitulé, le club ou la ville
 *   past=1             inclut les compétitions passées (ignore `from`)
 *   lat=&lon=          point de référence : ajoute la distance à chaque résultat
 *   radius=<km>        ne garde que ce qui est à moins de N km du point
 *   sort=date|distance ordre de tri (distance exige lat/lon)
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = db();

    // --- Point de référence --------------------------------------------------
    $origin = null;
    if (isset($_GET['lat'], $_GET['lon']) && is_numeric($_GET['lat']) && is_numeric($_GET['lon'])) {
        $lat = (float) $_GET['lat'];
        $lon = (float) $_GET['lon'];
        if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            $origin = ['lat' => $lat, 'lon' => $lon];
        }
    }

    $radius = null;
    if ($origin !== null && isset($_GET['radius']) && is_numeric($_GET['radius']) && (float) $_GET['radius'] > 0) {
        $radius = (float) $_GET['radius'];
    }

    $sort = ($_GET['sort'] ?? 'date') === 'distance' && $origin !== null ? 'distance' : 'date';

    // --- Colonne distance (formule de haversine, rayon terrestre 6371 km) ----
    // LEAST(1, …) évite qu'une erreur d'arrondi fasse sortir ACOS de son domaine.
    $selectParams = [];
    if ($origin !== null) {
        $distanceSql = '
            6371 * ACOS(LEAST(1,
                COS(RADIANS(?)) * COS(RADIANS(ci.latitude))
              * COS(RADIANS(ci.longitude) - RADIANS(?))
              + SIN(RADIANS(?)) * SIN(RADIANS(ci.latitude))
            ))';
        $selectParams = [$origin['lat'], $origin['lon'], $origin['lat']];
    } else {
        $distanceSql = 'NULL';
    }

    // --- Filtres -------------------------------------------------------------
    $where  = [];
    $params = [];

    $includePast = isset($_GET['past']) && $_GET['past'] !== '0';

    $from = isset($_GET['from']) && $_GET['from'] !== '' ? parse_date((string) $_GET['from']) : date('Y-m-d');
    if (!$includePast && $from !== null) {
        // Une compétition sur plusieurs jours reste visible tant qu'elle n'est pas terminée.
        $where[] = 'COALESCE(c.end_date, c.start_date) >= ?';
        $params[] = $from;
    }

    if (isset($_GET['to']) && $_GET['to'] !== '') {
        $to = parse_date((string) $_GET['to']);
        if ($to !== null) {
            $where[] = 'c.start_date <= ?';
            $params[] = $to;
        }
    }

    $env = strtolower((string) ($_GET['env'] ?? 'all'));
    if (in_array($env, ['in', 'out'], true)) {
        $where[] = 'c.environment = ?';
        $params[] = $env;
    }

    // Discipline : une compétition la propose si elle figure dans son index.
    // Les compétitions dont les épreuves n'ont pas été récupérées sortent donc
    // du résultat — c'est voulu : on ne peut pas affirmer qu'elles la proposent.
    $discipline = trim((string) ($_GET['event'] ?? ''));
    if ($discipline !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM competition_disciplines cd
                            WHERE cd.competition_id = c.id AND cd.discipline_key = ?)';
        $params[] = $discipline;
    }

    if (isset($_GET['country']) && $_GET['country'] !== '') {
        $where[] = 'c.country_code = ?';
        $params[] = strtoupper(substr((string) $_GET['country'], 0, 2));
    }

    if (isset($_GET['city']) && ctype_digit((string) $_GET['city'])) {
        $where[] = 'c.city_id = ?';
        $params[] = (int) $_GET['city'];
    }

    $search = trim((string) ($_GET['q'] ?? ''));
    if ($search !== '') {
        $where[] = '(c.title LIKE ? OR c.organizer LIKE ? OR ci.name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }

    $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

    // Le rayon porte sur l'alias : il s'applique donc en HAVING, après calcul.
    $having = '';
    $havingParams = [];
    if ($radius !== null) {
        $having = 'HAVING distance_km IS NOT NULL AND distance_km <= ?';
        $havingParams[] = $radius;
    }

    $order = $sort === 'distance'
        ? 'ORDER BY distance_km IS NULL, distance_km, c.start_date IS NULL, c.start_date'
        : 'ORDER BY c.start_date IS NULL, c.start_date, c.title';

    $sql = "
        SELECT
            c.id, c.title, c.start_date, c.end_date, c.environment,
            c.organizer, c.url, c.venue, c.country_code, c.raw,
            ci.id AS city_id, ci.name AS city_name, ci.region,
            ci.latitude, ci.longitude, ci.geocode_status,
            {$distanceSql} AS distance_km
        FROM competitions c
        LEFT JOIN cities ci ON ci.id = c.city_id
        {$clause}
        {$having}
        {$order}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$selectParams, ...$params, ...$havingParams]);

    $competitions = [];
    $cities       = [];
    $unlocated    = 0;

    while ($row = $stmt->fetch()) {
        $extra = json_decode((string) ($row['raw'] ?? ''), true);
        $participants = is_array($extra) ? ($extra['participants'] ?? null) : null;
        $status       = is_array($extra) ? ($extra['status'] ?? null) : null;

        $hasCoords = $row['latitude'] !== null && $row['longitude'] !== null;
        $distance  = $row['distance_km'] !== null ? round((float) $row['distance_km'], 1) : null;

        $competitions[] = [
            'id'           => (int) $row['id'],
            'title'        => $row['title'],
            'start_date'   => $row['start_date'],
            'end_date'     => $row['end_date'],
            'environment'  => $row['environment'],
            'organizer'    => $row['organizer'],
            'url'          => $row['url'],
            'venue'        => $row['venue'],
            'city_id'      => $row['city_id'] !== null ? (int) $row['city_id'] : null,
            'city_name'    => $row['city_name'],
            'region'       => $row['region'],
            'participants' => $participants !== null ? (int) $participants : null,
            'status'       => $status,
            'located'      => $hasCoords,
            'distance_km'  => $distance,
        ];

        if (!$hasCoords) {
            $unlocated++;
            continue;
        }

        $cityId = (int) $row['city_id'];
        if (!isset($cities[$cityId])) {
            $cities[$cityId] = [
                'id'          => $cityId,
                'name'        => $row['city_name'],
                'region'      => $row['region'],
                'country'     => $row['country_code'],
                'latitude'    => (float) $row['latitude'],
                'longitude'   => (float) $row['longitude'],
                'distance_km' => $distance,
                'count'       => 0,
                'indoor'      => 0,
                'outdoor'     => 0,
                'next_date'   => null,
            ];
        }

        $cities[$cityId]['count']++;
        if ($row['environment'] === 'in') {
            $cities[$cityId]['indoor']++;
        } elseif ($row['environment'] === 'out') {
            $cities[$cityId]['outdoor']++;
        }
        if ($row['start_date'] !== null
            && ($cities[$cityId]['next_date'] === null || $row['start_date'] < $cities[$cityId]['next_date'])
        ) {
            $cities[$cityId]['next_date'] = $row['start_date'];
        }
    }

    $cities = array_values($cities);
    if ($sort === 'distance') {
        usort($cities, static fn(array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']);
    } else {
        usort($cities, static fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));
    }

    $nearest = null;
    if ($origin !== null && $cities !== []) {
        $nearest = min(array_map(static fn(array $c): float => (float) $c['distance_km'], $cities));
    }

    echo json_encode([
        'competitions' => $competitions,
        'cities'       => $cities,
        'stats'        => [
            'competitions' => count($competitions),
            'cities'       => count($cities),
            'unlocated'    => $unlocated,
            'nearest_km'   => $nearest,
        ],
        'origin' => $origin,
        'filters' => [
            'from'   => $includePast ? null : $from,
            'to'     => $_GET['to'] ?? null,
            'env'    => $env,
            'event'  => $discipline !== '' ? $discipline : null,
            'city'   => isset($_GET['city']) ? (int) $_GET['city'] : null,
            'q'      => $search,
            'past'   => $includePast,
            'radius' => $radius,
            'sort'   => $sort,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => "Impossible de lire les données.",
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
