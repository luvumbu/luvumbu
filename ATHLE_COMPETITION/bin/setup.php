<?php
/**
 * Crée la base de données et applique sql/schema.sql.
 * Idempotent : peut être relancé sans risque (CREATE ... IF NOT EXISTS).
 *
 * Usage : php bin/setup.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Disciplines.php';
require __DIR__ . '/../src/Schema.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n"
       . "Depuis un navigateur, utilisez plutôt install.php (même travail, avec un formulaire).\n");
}

$cfg = app_config()['db'];

try {
    $server = db(false);
} catch (PDOException $e) {
    cli_log('[ERREUR] Connexion au serveur MySQL impossible : ' . $e->getMessage());
    cli_log('         Vérifiez que MySQL est démarré dans le panneau XAMPP.');
    exit(1);
}

if (($dbErr = schema_create_database($server, (string) $cfg['name'])) !== null) {
    cli_log('[ERREUR] Création de la base impossible : ' . $dbErr);
    exit(1);
}
cli_log("[OK] Base `{$cfg['name']}` prête.");

try {
    $result = schema_apply(db());
} catch (Throwable $e) {
    cli_log('[ERREUR] ' . $e->getMessage());
    exit(1);
}

cli_log("[OK] Schéma appliqué ({$result['statements']} instructions).");
if ($result['columns'] > 0) {
    cli_log("[OK] {$result['columns']} colonne(s) ajoutée(s) à `competitions`.");
}
if ($result['disciplines'] > 0) {
    cli_log("[OK] {$result['disciplines']} discipline(s) indexée(s) depuis les épreuves existantes.");
}
cli_log('[OK] Objets présents : ' . implode(', ', $result['tables']));

$data = app_config()['paths']['data'];
if (!is_dir($data) && !mkdir($data, 0777, true) && !is_dir($data)) {
    cli_log("[AVERTISSEMENT] Impossible de créer le dossier {$data}");
} else {
    cli_log("[OK] Dossier de données : {$data}");
}

cli_log('');
cli_log('Étape suivante : cd scraper && npm install && npm run scrape');
