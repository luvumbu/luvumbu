<?php
/**
 * Création et mise à jour du schéma.
 *
 * Ce code était dans bin/setup.php, réservé à la ligne de commande. Il est ici
 * pour que l'assistant web (install.php) applique EXACTEMENT le même schéma :
 * une base installée depuis le navigateur est identique à une base installée
 * en CLI, et il n'y a qu'un seul endroit à modifier quand le schéma évolue.
 */

declare(strict_types=1);

/**
 * Crée la base si elle n'existe pas. Renvoie une chaîne d'erreur, ou null.
 *
 * Beaucoup d'hébergements mutualisés interdisent CREATE DATABASE : dans ce cas
 * la base doit être créée depuis le panneau de l'hébergeur, et l'échec ici
 * n'est pas fatal tant que la base existe déjà.
 */
function schema_create_database(PDO $server, string $name): ?string
{
    try {
        $server->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '', $name)
        ));
        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

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

/** Colonnes ajoutées après la première version de sql/schema.sql. */
function schema_late_columns(): array
{
    return [
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
    ];
}

/**
 * Applique sql/schema.sql puis les correctifs de colonnes. Idempotent.
 *
 * @return array{statements:int,columns:int,disciplines:int,tables:string[]}
 * @throws RuntimeException si le fichier de schéma est illisible
 * @throws PDOException     si une instruction est refusée
 */
function schema_apply(PDO $pdo): array
{
    $file = __DIR__ . '/../sql/schema.sql';
    $sql  = @file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('sql/schema.sql introuvable.');
    }

    // Retire les commentaires de ligne avant le découpage en instructions.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

    $statements = array_filter(array_map('trim', explode(';', $sql)), static fn(string $s): bool => $s !== '');

    $applied = 0;
    foreach ($statements as $statement) {
        $pdo->exec($statement);
        $applied++;
    }

    $columns = ensure_columns($pdo, 'competitions', schema_late_columns());

    // Catalogue des disciplines : construit ici s'il est vide alors que des
    // épreuves sont déjà en base. Sans ça, une installation antérieure à
    // l'ajout du filtre « Épreuve » verrait le menu rester absent.
    $disciplines = 0;
    if ((int) $pdo->query('SELECT COUNT(*) FROM competition_disciplines')->fetchColumn() === 0) {
        $index = rebuild_discipline_index($pdo);
        $disciplines = (int) $index['disciplines'];
    }

    return [
        'statements'  => $applied,
        'columns'     => $columns,
        'disciplines' => $disciplines,
        'tables'      => $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),
    ];
}
