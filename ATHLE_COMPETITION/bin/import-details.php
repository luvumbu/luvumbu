<?php
/**
 * Importe data/details.json (produit par scraper/details.js) : épreuves par
 * catégorie, horaire, adresse du stade, période d'inscription.
 *
 * Les compétitions sont retrouvées par leur identifiant source (external_id).
 *
 * Usage : php bin/import-details.php [chemin/details.json]
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Disciplines.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$file = $argv[1] ?? (app_config()['paths']['data'] . '/details.json');

if (!is_file($file)) {
    cli_log("[ERREUR] Fichier introuvable : {$file}");
    cli_log('         Lancez d\'abord : cd scraper && node details.js');
    exit(1);
}

$payload = json_decode((string) file_get_contents($file), true);
$rows = $payload['details'] ?? null;

if (!is_array($rows) || $rows === []) {
    cli_log('[ERREUR] Aucun détail dans le fichier.');
    exit(1);
}

$pdo = db();

try {
    $pdo->query('SELECT schedule FROM competitions LIMIT 1');
    $pdo->query('SELECT 1 FROM competition_disciplines LIMIT 1');
} catch (PDOException $e) {
    cli_log('[ERREUR] Colonnes ou tables de détail absentes. Lancez d\'abord : php bin/setup.php');
    exit(1);
}

// COALESCE(?, colonne) : une fiche qui ne renvoie rien cette fois-ci ne doit
// pas effacer ce qu'un passage précédent avait récupéré (le site tronque
// parfois ses pages sous charge, ou répond 429 en cours de route).
// Exception : registration_url est écrasé sans filet — quand le site retire le
// lien, c'est que les inscriptions sont closes, et l'info doit disparaître.
$update = $pdo->prepare(
    'UPDATE competitions SET
        start_time        = COALESCE(?, start_time),
        end_time          = COALESCE(?, end_time),
        venue_address     = COALESCE(?, venue_address),
        maps_url          = COALESCE(?, maps_url),
        contact_email     = COALESCE(?, contact_email),
        conditions        = COALESCE(?, conditions),
        registration_from = COALESCE(?, registration_from),
        registration_to   = COALESCE(?, registration_to),
        registration_url  = ?,
        entrants_url      = COALESCE(?, entrants_url),
        schedule_url      = COALESCE(?, schedule_url),
        categories        = COALESCE(?, categories),
        events            = COALESCE(?, events),
        schedule          = COALESCE(?, schedule),
        details_fetched_at = NOW()
     WHERE external_id = ? AND source = ?'
);

$updated = 0;
$missing = 0;
$totalEvents = 0;

$pdo->beginTransaction();

foreach ($rows as $detail) {
    if (!is_array($detail) || empty($detail['external_id'])) {
        continue;
    }

    // Résumé plat des catégories, pour l'affichage en liste et la recherche.
    $codes = [];
    foreach ($detail['categories'] ?? [] as $block) {
        foreach ($block['categories'] ?? [] as $category) {
            $code = trim((string) ($category['code'] ?? ''));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }
        foreach ($block['groups'] ?? [] as $group) {
            $totalEvents += count($group['events'] ?? []);
        }
    }

    $update->execute([
        $detail['start_time'] ?: null,
        $detail['end_time'] ?: null,
        $detail['address'] ? mb_substr((string) $detail['address'], 0, 255) : null,
        $detail['maps_url'] ? mb_substr((string) $detail['maps_url'], 0, 512) : null,
        $detail['email'] ? mb_substr((string) $detail['email'], 0, 160) : null,
        $detail['conditions'] ? mb_substr((string) $detail['conditions'], 0, 255) : null,
        $detail['registration_from'] ?: null,
        $detail['registration_to'] ?: null,
        !empty($detail['registration_url']) ? mb_substr((string) $detail['registration_url'], 0, 512) : null,
        !empty($detail['entrants_url']) ? mb_substr((string) $detail['entrants_url'], 0, 512) : null,
        !empty($detail['schedule_url']) ? mb_substr((string) $detail['schedule_url'], 0, 512) : null,
        $codes !== [] ? mb_substr(implode(', ', array_keys($codes)), 0, 255) : null,
        !empty($detail['categories']) ? json_encode($detail['categories'], JSON_UNESCAPED_UNICODE) : null,
        !empty($detail['schedule']) ? json_encode($detail['schedule'], JSON_UNESCAPED_UNICODE) : null,
        (string) $detail['external_id'],
        'athletisme.app',
    ]);

    if ($update->rowCount() > 0) {
        $updated++;
    } else {
        $missing++;
    }
}

$pdo->commit();

// Index des disciplines : reconstruit sur toute la base, pas seulement sur les
// fiches de ce passage — une compétition absente du fichier garde les siennes.
$index = rebuild_discipline_index($pdo);

cli_log("[OK] Compétitions enrichies : {$updated}");
cli_log("[OK] Épreuves enregistrées  : {$totalEvents}");
cli_log("[OK] Disciplines indexées   : {$index['disciplines']} ({$index['links']} rattachements)");
if ($missing > 0) {
    cli_log("[!]  Fiches sans compétition correspondante : {$missing}");
    cli_log('     (relancez php bin/import.php si le calendrier a changé)');
}

$withEvents = (int) $pdo->query('SELECT COUNT(*) FROM competitions WHERE events IS NOT NULL')->fetchColumn();
$total      = (int) $pdo->query('SELECT COUNT(*) FROM competitions')->fetchColumn();
cli_log('');
cli_log("{$withEvents}/{$total} compétition(s) ont leurs épreuves.");
