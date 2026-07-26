<?php
/**
 * (Re)construit le catalogue des disciplines à partir des épreuves déjà en base
 * (colonne `competitions.events`). Aucun appel réseau, aucun fichier requis.
 *
 * bin/import-details.php le fait déjà à chaque import : ce script sert quand on
 * veut rafraîchir l'index seul — après une modification des règles de
 * regroupement, ou sur une base installée avant l'ajout de la fonctionnalité.
 *
 * Usage : php bin/index-disciplines.php [--list]
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Disciplines.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$pdo = db();

try {
    $pdo->query('SELECT 1 FROM competition_disciplines LIMIT 1');
} catch (PDOException $e) {
    cli_log('[ERREUR] Tables absentes. Lancez d\'abord : php bin/setup.php');
    exit(1);
}

$index = rebuild_discipline_index($pdo);

cli_log("[OK] {$index['disciplines']} discipline(s), {$index['links']} rattachement(s).");

if (in_array('--list', $argv, true)) {
    cli_log('');
    foreach (discipline_catalogue($pdo, false) as $family => $disciplines) {
        cli_log($family);
        foreach ($disciplines as $discipline) {
            cli_log(sprintf('  %-22s %-40s %d compét.', $discipline['key'], $discipline['label'], $discipline['count']));
        }
    }
} else {
    cli_log('     Détail : php bin/index-disciplines.php --list');
}
