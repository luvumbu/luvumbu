<?php
/**
 * Importe data/competitions.json (produit par scraper/scrape.js) dans MySQL.
 *
 * - normalise le libellé de lieu en nom de ville ;
 * - crée la ville si elle n'existe pas (statut de géocodage « pending ») ;
 * - insère ou met à jour la compétition, dédoublonnée par empreinte sha1.
 *
 * Usage :
 *   php bin/import.php                     importe data/competitions.json
 *   php bin/import.php chemin/fichier.json importe un autre fichier
 *   php bin/import.php --dry-run           simule sans écrire
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$args   = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$args   = array_values(array_filter($args, static fn(string $a): bool => !str_starts_with($a, '--')));
$file   = $args[0] ?? (app_config()['paths']['data'] . '/competitions.json');

if (!is_file($file)) {
    cli_log("[ERREUR] Fichier introuvable : {$file}");
    cli_log('         Lancez d\'abord : cd scraper && npm run scrape');
    exit(1);
}

$payload = json_decode((string) file_get_contents($file), true);
if (!is_array($payload)) {
    cli_log("[ERREUR] JSON illisible : {$file}");
    exit(1);
}

$rows          = $payload['competitions'] ?? (isset($payload[0]) ? $payload : []);
$source        = (string) ($payload['source'] ?? 'athletisme.app');
$defaultCountry = strtoupper((string) ($payload['country'] ?? 'BE'));

if (!is_array($rows) || $rows === []) {
    cli_log('[ERREUR] Aucune compétition dans le fichier.');
    exit(1);
}

cli_log(sprintf('Import de %d enregistrement(s) depuis %s%s', count($rows), basename($file), $dryRun ? ' [SIMULATION]' : ''));

$pdo = db();

$runId = null;
if (!$dryRun) {
    $stmt = $pdo->prepare('INSERT INTO import_runs (source, source_file, rows_read) VALUES (?, ?, ?)');
    $stmt->execute([$source, basename($file), count($rows)]);
    $runId = (int) $pdo->lastInsertId();
}

// --- Requêtes préparées -----------------------------------------------------

$findCity = $pdo->prepare(
    'SELECT c.id FROM cities c WHERE c.name_normalized = ? AND c.country_code = ?
     UNION
     SELECT a.city_id FROM city_aliases a WHERE a.alias_normalized = ? AND a.country_code = ?
     LIMIT 1'
);
$insertCity = $pdo->prepare(
    'INSERT INTO cities (name, name_normalized, country_code) VALUES (?, ?, ?)'
);
$findCompetition = $pdo->prepare('SELECT id FROM competitions WHERE fingerprint = ?');
$insertCompetition = $pdo->prepare(
    'INSERT INTO competitions
        (source, external_id, fingerprint, title, city_id, city_raw, venue, country_code,
         start_date, end_date, environment, categories, events, organizer, url, raw)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$updateCompetition = $pdo->prepare(
    'UPDATE competitions SET
        external_id = ?, title = ?, city_id = ?, city_raw = ?, venue = ?, country_code = ?,
        start_date = ?, end_date = ?, environment = ?, categories = ?, events = ?,
        organizer = ?, url = ?, raw = ?
     WHERE id = ?'
);

/** @var array<string,int> cache mémoire des villes déjà résolues */
$cityCache = [];

$stats = ['cities' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

$pdo->beginTransaction();

try {
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $stats['skipped']++;
            continue;
        }

        $title = trim((string) ($row['title'] ?? ''));
        $rawLocation = trim((string) ($row['location'] ?? ''));

        if ($title === '' && $rawLocation === '') {
            $stats['skipped']++;
            continue;
        }

        [$cityName, $venueFromLocation] = split_venue($rawLocation);
        $venue = trim((string) ($row['venue'] ?? '')) ?: $venueFromLocation;

        $country = strtoupper(substr(trim((string) ($row['country_code'] ?? $defaultCountry)) ?: $defaultCountry, 0, 2));
        $normalized = normalize_city_name($cityName);

        // --- Résolution / création de la ville ------------------------------
        $cityId = null;
        if ($normalized !== '') {
            $cacheKey = $normalized . '|' . $country;
            if (isset($cityCache[$cacheKey])) {
                $cityId = $cityCache[$cacheKey];
            } else {
                $findCity->execute([$normalized, $country, $normalized, $country]);
                $found = $findCity->fetchColumn();
                if ($found !== false) {
                    $cityId = (int) $found;
                } elseif (!$dryRun) {
                    $insertCity->execute([$cityName, $normalized, $country]);
                    $cityId = (int) $pdo->lastInsertId();
                    $stats['cities']++;
                } else {
                    $stats['cities']++;
                }
                if ($cityId !== null) {
                    $cityCache[$cacheKey] = $cityId;
                }
            }
        }

        // --- Compétition ----------------------------------------------------
        $startDate = parse_date($row['start_date'] ?? null);
        $endDate   = parse_date($row['end_date'] ?? null) ?? $startDate;

        $environment = strtolower(trim((string) ($row['environment'] ?? 'unknown')));
        if (!in_array($environment, ['in', 'out', 'unknown'], true)) {
            $environment = 'unknown';
        }

        $externalId = $row['external_id'] ?? null;
        $externalId = ($externalId === null || $externalId === '') ? null : substr((string) $externalId, 0, 64);

        // L'empreinte privilégie l'identifiant source ; sinon date+titre+ville.
        $fingerprint = sha1($externalId !== null
            ? $source . '|id|' . $externalId
            : implode('|', [$source, (string) $startDate, mb_strtolower($title), $normalized]));

        $values = [
            $externalId,
            mb_substr($title !== '' ? $title : $cityName, 0, 255),
            $cityId,
            mb_substr($rawLocation, 0, 255) ?: null,
            $venue !== null && $venue !== '' ? mb_substr($venue, 0, 255) : null,
            $country,
            $startDate,
            $endDate,
            $environment,
            mb_substr((string) ($row['categories'] ?? ''), 0, 255) ?: null,
            (string) ($row['events'] ?? '') ?: null,
            mb_substr((string) ($row['organizer'] ?? ''), 0, 255) ?: null,
            mb_substr((string) ($row['url'] ?? ''), 0, 512) ?: null,
            json_encode($row['raw'] ?? $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $findCompetition->execute([$fingerprint]);
        $existingId = $findCompetition->fetchColumn();

        if ($existingId !== false) {
            if (!$dryRun) {
                $updateCompetition->execute([...$values, (int) $existingId]);
            }
            $stats['updated']++;
        } else {
            if (!$dryRun) {
                $insertCompetition->execute([
                    $source,
                    $values[0],
                    $fingerprint,
                    ...array_slice($values, 1),
                ]);
            }
            $stats['created']++;
        }
    }

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($runId !== null) {
        $pdo->prepare('UPDATE import_runs SET status = "error", message = ?, finished_at = NOW() WHERE id = ?')
            ->execute([$e->getMessage(), $runId]);
    }
    cli_log('[ERREUR] Import interrompu : ' . $e->getMessage());
    exit(1);
}

if ($runId !== null) {
    $pdo->prepare(
        'UPDATE import_runs SET status = "ok", finished_at = NOW(),
            cities_created = ?, competitions_created = ?, competitions_updated = ?, rows_skipped = ?
         WHERE id = ?'
    )->execute([$stats['cities'], $stats['created'], $stats['updated'], $stats['skipped'], $runId]);
}

cli_log('');
cli_log("[OK] Villes créées        : {$stats['cities']}");
cli_log("[OK] Compétitions créées  : {$stats['created']}");
cli_log("[OK] Compétitions à jour  : {$stats['updated']}");
cli_log("[OK] Lignes ignorées      : {$stats['skipped']}");

if (!$dryRun) {
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM cities WHERE geocode_status = 'pending'")->fetchColumn();
    cli_log('');
    cli_log("{$pending} ville(s) à géocoder.");
    cli_log('Étape suivante : php bin/geocode.php');
}
