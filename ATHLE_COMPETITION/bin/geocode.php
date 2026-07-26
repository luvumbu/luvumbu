<?php
/**
 * Géocode les villes en attente : c'est l'étape qui « lie » chaque ville
 * de compétition à un point sur la carte.
 *
 * Usage :
 *   php bin/geocode.php                    traite les villes 'pending'
 *   php bin/geocode.php --retry-failed     retente aussi les échecs
 *   php bin/geocode.php --limit=50         s'arrête après 50 villes
 *   php bin/geocode.php --city="Gent" --lat=51.0543 --lon=3.7174
 *                                          fixe des coordonnées à la main
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Geocoder.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$options = getopt('', ['retry-failed', 'limit::', 'city::', 'lat::', 'lon::', 'country::']);
$pdo = db();

// --- Mode « correction manuelle » -------------------------------------------
if (isset($options['city'])) {
    $name    = (string) $options['city'];
    $country = strtoupper((string) ($options['country'] ?? 'BE'));

    if (!isset($options['lat'], $options['lon'])) {
        cli_log('[ERREUR] --city exige --lat et --lon.');
        exit(1);
    }

    $stmt = $pdo->prepare(
        "UPDATE cities
         SET latitude = ?, longitude = ?, geocode_status = 'manual',
             geocode_provider = 'manuel', geocoded_at = NOW()
         WHERE name_normalized = ? AND country_code = ?"
    );
    $stmt->execute([
        (float) $options['lat'],
        (float) $options['lon'],
        normalize_city_name($name),
        $country,
    ]);

    if ($stmt->rowCount() === 0) {
        cli_log("[ERREUR] Ville « {$name} » ({$country}) absente de la base.");
        exit(1);
    }
    cli_log("[OK] « {$name} » fixée manuellement à {$options['lat']}, {$options['lon']}.");
    exit(0);
}

// --- Traitement par lot ------------------------------------------------------
$config      = app_config()['geocoder'];
$maxAttempts = (int) ($config['max_attempts'] ?? 3);
$limit       = isset($options['limit']) ? max(1, (int) $options['limit']) : 0;

$statuses = isset($options['retry-failed']) ? ['pending', 'failed'] : ['pending'];
$placeholders = implode(',', array_fill(0, count($statuses), '?'));

$sql = "SELECT id, name, country_code, geocode_attempts
        FROM cities
        WHERE geocode_status IN ({$placeholders}) AND geocode_attempts < ?
        ORDER BY id";
$params = [...$statuses, $maxAttempts];

if ($limit > 0) {
    $sql .= ' LIMIT ' . $limit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cities = $stmt->fetchAll();

if ($cities === []) {
    cli_log('Aucune ville à géocoder.');
    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM cities WHERE geocode_status = 'failed'"
    )->fetchColumn();
    if ($remaining > 0) {
        cli_log("({$remaining} ville(s) en échec — relancez avec --retry-failed ou corrigez avec --city/--lat/--lon)");
    }
    exit(0);
}

cli_log(sprintf('Géocodage de %d ville(s) — environ %d s à 1 req/s.', count($cities), count($cities)));
cli_log('');

$geocoder = new Geocoder($config);

$success = $pdo->prepare(
    "UPDATE cities
     SET latitude = ?, longitude = ?, region = ?, postal_code = ?,
         geocode_status = 'ok', geocode_provider = ?, geocode_query = ?,
         geocode_attempts = geocode_attempts + 1, geocoded_at = NOW()
     WHERE id = ?"
);
$failure = $pdo->prepare(
    "UPDATE cities
     SET geocode_status = CASE WHEN geocode_attempts + 1 >= ? THEN 'failed' ELSE 'pending' END,
         geocode_query = ?, geocode_attempts = geocode_attempts + 1
     WHERE id = ?"
);

$ok = 0;
$ko = 0;

foreach ($cities as $index => $city) {
    $label = sprintf('[%d/%d] %s (%s)', $index + 1, count($cities), $city['name'], $city['country_code']);

    $result = $geocoder->lookup($city['name'], $city['country_code']);

    if ($result === null) {
        $failure->execute([$maxAttempts, $city['name'], $city['id']]);
        $ko++;
        cli_log("{$label} → introuvable");
        continue;
    }

    $success->execute([
        $result['lat'],
        $result['lon'],
        $result['region'],
        $result['postal_code'],
        (string) $config['provider'],
        $result['query'],
        $city['id'],
    ]);
    $ok++;
    cli_log(sprintf('%s → %.5f, %.5f  %s', $label, $result['lat'], $result['lon'], $result['region'] ?? ''));
}

cli_log('');
cli_log("[OK] Géocodées : {$ok}");
if ($ko > 0) {
    cli_log("[!]  Échecs    : {$ko}");
    cli_log('     Listez-les : php bin/geocode.php --retry-failed');
    cli_log('     Ou fixez à la main : php bin/geocode.php --city="Nom" --lat=50.8 --lon=4.3');
}

$linked = (int) $pdo->query(
    'SELECT COUNT(*) FROM competitions c
     JOIN cities ci ON ci.id = c.city_id
     WHERE ci.latitude IS NOT NULL'
)->fetchColumn();
$total = (int) $pdo->query('SELECT COUNT(*) FROM competitions')->fetchColumn();

cli_log('');
cli_log("{$linked}/{$total} compétition(s) placées sur la carte.");
cli_log('Ouvrez : http://localhost/ATHLE_COMPETITION/');
