<?php
/**
 * Autocomplétion d'adresse, servie depuis le répertoire local `places`.
 *
 * Aucun appel réseau : la politique d'usage de Nominatim interdit
 * explicitement l'autocomplétion au clavier. La réponse est donc immédiate
 * et sans quota.
 *
 * La recherche porte par défaut sur TOUS les pays importés : le domicile de
 * l'utilisateur n'est pas forcément dans le pays des compétitions consultées
 * (habiter Lille et courir en Belgique est un cas courant).
 *
 * GET api/suggest.php?q=4020            tous pays
 * GET api/suggest.php?q=4020&country=BE restreint à un pays
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$query   = trim((string) ($_GET['q'] ?? ''));
$country = strtoupper(trim((string) ($_GET['country'] ?? '')));
if ($country === 'ALL') {
    $country = '';
}

if (mb_strlen($query) < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Sépare la partie numérique (code postal) de la partie texte (commune).
    $postal = '';
    if (preg_match('/\b(\d{4,6})\b/', $query, $m)) {
        $postal = $m[1];
    }
    $text = normalize_city_name(preg_replace('/\b\d{4,6}\b/', ' ', $query) ?? $query);

    if ($postal === '' && $text === '') {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $where  = [];
    $params = [];

    if ($country !== '') {
        $where[]  = 'country_code = ?';
        $params[] = substr($country, 0, 2);
    }
    if ($postal !== '') {
        $where[]  = 'postal_code LIKE ?';
        $params[] = $postal . '%';
    }
    if ($text !== '') {
        $where[]  = 'name_normalized LIKE ?';
        $params[] = '%' . $text . '%';
    }

    // Classement : correspondance exacte, puis début de chaîne, puis le reste.
    $rank = 'CASE
                WHEN postal_code = ? THEN 0
                WHEN name_normalized = ? THEN 1
                WHEN name_normalized LIKE ? THEN 2
                WHEN postal_code LIKE ? THEN 3
                ELSE 4
             END';
    $rankParams = [$postal, $text, $text . '%', $postal . '%'];

    // À rang égal, la commune la plus « notoire » passe devant : un chef-lieu
    // couvre plusieurs codes postaux (Liège 4000/4020), un quartier un seul.
    $notoriety = '(SELECT COUNT(*) FROM places p2
                   WHERE p2.country_code = places.country_code
                     AND p2.name_normalized = places.name_normalized)';

    $sql = 'SELECT country_code, postal_code, name, region, province, latitude, longitude, ' . $rank . ' AS rk
            FROM places
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY rk, ' . $notoriety . ' DESC, name, postal_code
            LIMIT 8';

    $stmt = db()->prepare($sql);
    $stmt->execute([...$rankParams, ...$params]);

    $countries = app_config()['countries'];

    $suggestions = [];
    foreach ($stmt->fetchAll() as $row) {
        $suggestions[] = [
            'country'      => $row['country_code'],
            'country_name' => $countries[$row['country_code']] ?? $row['country_code'],
            'postal_code'  => $row['postal_code'],
            'name'         => $row['name'],
            'region'       => $row['province'] ?: $row['region'],
            'lat'          => (float) $row['latitude'],
            'lon'          => (float) $row['longitude'],
            'label'        => $row['postal_code'] . ' ' . $row['name'],
        ];
    }

    echo json_encode($suggestions, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
