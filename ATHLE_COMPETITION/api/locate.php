<?php
/**
 * Transforme une adresse libre en coordonnées, pour le calcul de distances.
 *
 * GET api/locate.php?q=Rue du Stade 12, 4020 Liège&country=BE
 *   → {"lat":…,"lon":…,"label":"…","precision":"address|locality","source":"…"}
 *
 * Résolution en cascade — l'ordre dépend de ce que l'utilisateur a tapé :
 *
 *   Saisie « commune » (« 4020 », « Liège », « 4020 Liège ») :
 *     1. répertoire local `places` (instantané, sans quota)
 *     2. Nominatim en dernier recours
 *
 *   Saisie « adresse de rue » (« Rue du Stade 12, 4020 Liège ») :
 *     1. Nominatim, plus précis pour une rue
 *     2. Nominatim sans le numéro de police
 *     3. repli sur le répertoire local via le code postal
 *     4. repli sur le répertoire local via le nom de commune
 *
 * On ne renvoie « introuvable » qu'après avoir épuisé toute la cascade.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Geocoder.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$address = trim((string) ($_GET['q'] ?? ''));
// Pays vide = recherche dans tous les pays connus : le domicile n'est pas
// forcément dans le pays des compétitions consultées.
$country = strtoupper(trim((string) ($_GET['country'] ?? '')));
if ($country === 'ALL') {
    $country = '';
}

if ($address === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Adresse manquante.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($address) > 200) {
    $address = mb_substr($address, 0, 200);
}

/**
 * Cherche une localité dans le répertoire local.
 *
 * @return array{lat:float,lon:float,label:string}|null
 */
function locate_locally(string $postal, string $text, string $country): ?array
{
    if ($postal === '' && $text === '') {
        return null;
    }

    // Sans pays imposé, on cherche dans tous les pays importés : le domicile
    // n'est pas forcément dans le pays des compétitions consultées.
    $where  = [];
    $params = [];

    if ($country !== '') {
        $where[]  = 'country_code = ?';
        $params[] = $country;
    }

    if ($postal !== '') {
        $where[]  = 'postal_code = ?';
        $params[] = $postal;
    }
    if ($text !== '') {
        $where[]  = '(name_normalized = ? OR name_normalized LIKE ?)';
        $params[] = $text;
        $params[] = $text . '%';
    }

    // À rang égal, la commune la plus « notoire » passe devant : un chef-lieu
    // couvre plusieurs codes postaux (Liège 4000/4020), un quartier un seul.
    $sql = 'SELECT country_code, postal_code, name, province, region, latitude, longitude,
                   CASE WHEN name_normalized = ? THEN 0 ELSE 1 END AS rk
            FROM places
            WHERE ' . ($where === [] ? '1' : implode(' AND ', $where)) . '
            ORDER BY rk,
                     (SELECT COUNT(*) FROM places p2
                      WHERE p2.country_code = places.country_code
                        AND p2.name_normalized = places.name_normalized) DESC,
                     postal_code
            LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([$text, ...$params]);
    $row = $stmt->fetch();

    if ($row === false) {
        return null;
    }

    $region = $row['province'] ?: $row['region'];
    $countries = app_config()['countries'];
    $countryName = $countries[$row['country_code']] ?? $row['country_code'];

    return [
        'lat'   => (float) $row['latitude'],
        'lon'   => (float) $row['longitude'],
        'label' => trim($row['postal_code'] . ' ' . $row['name'] . ($region ? ', ' . $region : '') . ' (' . $countryName . ')'),
    ];
}

// --- Découpage de la saisie -------------------------------------------------

$postal = '';
if (preg_match('/\b(\d{4,6})\b/', $address, $m)) {
    $postal = $m[1];
}
$textPart = normalize_city_name(preg_replace('/\b\d{4,6}\b/', ' ', $address) ?? $address);

// Une adresse de rue se reconnaît à un mot-clé de voirie, ou à un numéro de
// police (chiffre court distinct du code postal).
$streetKeywords = '/\b(rue|avenue|av|chauss[ée]e|chemin|boulevard|bd|place|drève|dreve|clos|impasse|quai|route|all[ée]e|square|straat|laan|steenweg|weg|dreef|plein|baan|markt|kaai|pad)\b/iu';
$hasHouseNumber = (bool) preg_match('/\b\d{1,3}[a-z]?\b/i', preg_replace('/\b\d{4,6}\b/', ' ', $address) ?? '');
$looksLikeStreet = preg_match($streetKeywords, $address) === 1 || $hasHouseNumber;

// --- Cache disque -----------------------------------------------------------

$cacheFile = app_config()['paths']['data'] . '/address-cache.json';
$cacheKey  = mb_strtolower($address) . '|' . $country;

/** @var array<string,array<string,mixed>> $cache */
$cache = [];
if (is_file($cacheFile)) {
    $decoded = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($decoded)) {
        $cache = $decoded;
    }
}
if (isset($cache[$cacheKey])) {
    echo json_encode($cache[$cacheKey] + ['cached' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Cascade ----------------------------------------------------------------

$result    = null;
$precision = 'locality';
$source    = 'local';

$tryNominatim = static function (string $query) use ($country): ?array {
    // Sans pays imposé, on borne quand même la recherche aux pays configurés :
    // « rue de la Gare » existe dans le monde entier.
    $scope = $country !== '' ? $country : implode(',', array_keys(app_config()['countries']));
    try {
        $geocoder = new Geocoder(app_config()['geocoder']);
        $hit = $geocoder->search($query, $scope);
    } catch (Throwable $e) {
        return null;
    }
    return $hit === null ? null : ['lat' => $hit['lat'], 'lon' => $hit['lon'], 'label' => $hit['display_name']];
};

if ($looksLikeStreet) {
    $result = $tryNominatim($address);
    if ($result !== null) {
        $precision = 'address';
        $source    = 'nominatim';
    }

    // Sans le numéro de police : « Rue du Stade 12 » → « Rue du Stade »
    if ($result === null) {
        $stripped = trim(preg_replace('/(?<!\d)\d{1,3}[a-z]?\b(?!\d)/i', ' ', $address) ?? $address);
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped) ?? $stripped);
        if ($stripped !== '' && $stripped !== $address) {
            $result = $tryNominatim($stripped);
            if ($result !== null) {
                $precision = 'address';
                $source    = 'nominatim';
            }
        }
    }

    if ($result === null) {
        $result = locate_locally($postal, '', $country) ?? locate_locally('', $textPart, $country);
    }
} else {
    $result = locate_locally($postal, $textPart, $country)
        ?? locate_locally($postal, '', $country)
        ?? locate_locally('', $textPart, $country);

    if ($result === null) {
        $result = $tryNominatim($address);
        if ($result !== null) {
            $source = 'nominatim';
        }
    }
}

if ($result === null) {
    http_response_code(404);
    echo json_encode([
        'error' => "Adresse introuvable. Essayez « code postal + commune », par exemple « 4020 Liège ».",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    'lat'       => $result['lat'],
    'lon'       => $result['lon'],
    'label'     => $result['label'],
    'precision' => $precision,
    'source'    => $source,
];

$cache[$cacheKey] = $payload;
if (count($cache) > 500) {
    $cache = array_slice($cache, -500, null, true);
}
@file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

echo json_encode($payload + ['cached' => false], JSON_UNESCAPED_UNICODE);
