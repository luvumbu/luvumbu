<?php
/**
 * Crée la base de données et applique sql/schema.sql.
 * Idempotent : peut être relancé sans risque (CREATE ... IF NOT EXISTS).
 *
 * Usage : php bin/setup.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$cfg = app_config()['db'];

try {
    $server = db(false);
} catch (PDOException $e) {
    cli_log('[ERREUR] Connexion au serveur MySQL impossible : ' . $e->getMessage());
    cli_log('         Vérifiez que MySQL est démarré dans le panneau XAMPP.');
    exit(1);
}

$server->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    str_replace('`', '', $cfg['name'])
));
cli_log("[OK] Base `{$cfg['name']}` prête.");

$sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
if ($sql === false) {
    cli_log('[ERREUR] sql/schema.sql introuvable.');
    exit(1);
}

// Retire les commentaires de ligne avant le découpage en instructions.
$sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

$pdo = db();
$statements = array_filter(array_map('trim', explode(';', $sql)), static fn(string $s): bool => $s !== '');

$applied = 0;
foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        $applied++;
    } catch (PDOException $e) {
        cli_log('[ERREUR] Instruction SQL refusée : ' . $e->getMessage());
        cli_log('         ' . mb_substr(preg_replace('/\s+/', ' ', $statement) ?? '', 0, 120) . '…');
        exit(1);
    }
}

cli_log("[OK] Schéma appliqué ({$applied} instructions).");

/**
 * Ajoute les colonnes manquantes sur une table déjà créée.
 * `CREATE TABLE IF NOT EXISTS` ne fait rien sur une table existante : sans ça,
 * une base installée avant l'ajout d'un champ resterait incomplète.
 *
 * @param array<string,string> $columns nom => définition SQL
 */
function ensure_columns(PDO $pdo, string $table, array $columns): int
{
    $existing = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $existing->execute([$table]);
    $present = array_map('strtolower', $existing->fetchAll(PDO::FETCH_COLUMN));

    $added = 0;
    foreach ($columns as $name => $definition) {
        if (in_array(strtolower($name), $present, true)) {
            continue;
        }
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
        $added++;
    }
    return $added;
}

$added = ensure_columns($pdo, 'competitions', [
    'start_time'         => 'TIME NULL COMMENT "Heure de début"',
    'end_time'           => 'TIME NULL',
    'venue_address'      => 'VARCHAR(255) NULL COMMENT "Adresse exacte du stade"',
    'maps_url'           => 'VARCHAR(512) NULL',
    'contact_email'      => 'VARCHAR(160) NULL',
    'conditions'         => 'VARCHAR(255) NULL COMMENT "Indoor/Outdoor, chronométrage, reconnaissance"',
    'registration_from'  => 'DATE NULL',
    'registration_to'    => 'DATE NULL',
    'registration_url'   => 'VARCHAR(512) NULL COMMENT "Lien direct d\'inscription, présent uniquement si ouvert"',
    'entrants_url'       => 'VARCHAR(512) NULL COMMENT "Liste des inscrits"',
    'schedule_url'       => 'VARCHAR(512) NULL COMMENT "Horaire complet sur le site source"',
    'schedule'           => 'LONGTEXT NULL COMMENT "Chronologie heure/épreuve (JSON)"',
    'details_fetched_at' => 'DATETIME NULL COMMENT "Dernière consultation de la fiche"',
]);

if ($added > 0) {
    cli_log("[OK] {$added} colonne(s) ajoutée(s) à `competitions`.");
}

// Catalogue des disciplines : construit ici s'il est vide alors que des
// épreuves sont déjà en base. Sans ça, une installation antérieure à l'ajout du
// filtre « Épreuve » verrait le menu rester absent jusqu'au prochain import.
require __DIR__ . '/../src/Disciplines.php';

if ((int) $pdo->query('SELECT COUNT(*) FROM competition_disciplines')->fetchColumn() === 0) {
    $index = rebuild_discipline_index($pdo);
    if ($index['disciplines'] > 0) {
        cli_log("[OK] {$index['disciplines']} discipline(s) indexée(s) depuis les épreuves existantes.");
    }
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
cli_log('[OK] Objets présents : ' . implode(', ', $tables));

$data = app_config()['paths']['data'];
if (!is_dir($data) && !mkdir($data, 0777, true) && !is_dir($data)) {
    cli_log("[AVERTISSEMENT] Impossible de créer le dossier {$data}");
} else {
    cli_log("[OK] Dossier de données : {$data}");
}

cli_log('');
cli_log('Étape suivante : cd scraper && npm install && npm run scrape');
