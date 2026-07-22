<?php

/**
 * Runner de migrations & seeds.
 *
 *   php database/migrate.php            # crée la base + applique les migrations en attente
 *   php database/migrate.php --seed     # + exécute les seeders (référentiels)
 *   php database/migrate.php --fresh     # SUPPRIME puis recrée toute la base (destructif)
 *   php database/migrate.php --fresh --seed
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use Dotenv\Dotenv;

// --- Bootstrap minimal (env + config) ---
$envDir = dirname(__DIR__) . '/config';
if (is_file("$envDir/.env")) {
    Dotenv::createImmutable($envDir)->safeLoad();
}
Config::load(require dirname(__DIR__) . '/config/config.php');

$args   = $argv ?? [];
$seed   = in_array('--seed', $args, true);
$fresh  = in_array('--fresh', $args, true);
$dbName = config('database.database');

function out(string $msg, string $color = ''): void
{
    $colors = ['green' => "\033[32m", 'yellow' => "\033[33m", 'red' => "\033[31m", 'cyan' => "\033[36m"];
    $reset  = "\033[0m";
    echo ($colors[$color] ?? '') . $msg . ($color ? $reset : '') . PHP_EOL;
}

try {
    // --- 1. Création (ou recréation) de la base ---
    $server = Database::serverConnection();

    if ($fresh) {
        out("⚠  Suppression de la base « $dbName »...", 'yellow');
        $server->exec("DROP DATABASE IF EXISTS `$dbName`");
    }

    $server->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("✓ Base « $dbName » prête.", 'green');

    $pdo = Database::connection();

    // --- 2. Table de suivi des migrations ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            batch INT UNSIGNED NOT NULL,
            executed_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $applied = array_column(
        Database::select('SELECT filename FROM migrations'),
        'filename'
    );

    $batch = (int) (Database::selectOne('SELECT COALESCE(MAX(batch),0)+1 AS b FROM migrations')['b'] ?? 1);

    // --- 3. Application des migrations SQL ---
    $files = glob(config('paths.migrations') . '/*.sql');
    sort($files);

    $ran = 0;
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            continue;
        }

        $sql = file_get_contents($file);
        out("→ Migration $name ...", 'cyan');

        // Pas de transaction : le DDL MySQL provoque des commits implicites.
        $pdo->exec($sql);
        Database::execute(
            'INSERT INTO migrations (filename, batch, executed_at) VALUES (?, ?, NOW())',
            [$name, $batch]
        );

        $ran++;
    }

    out($ran === 0 ? "✓ Aucune migration en attente." : "✓ $ran migration(s) appliquée(s).", 'green');

    // --- 4. Seeders ---
    if ($seed) {
        out("→ Exécution des seeders...", 'cyan');
        $seeders = glob(config('paths.seeds') . '/*.php');
        sort($seeders);
        foreach ($seeders as $seeder) {
            out("  · " . basename($seeder), 'cyan');
            require $seeder;
        }
        out("✓ Seeders terminés.", 'green');
    }

    out("🎉 Terminé.", 'green');
    exit(0);

} catch (\Throwable $e) {
    out("✗ ERREUR : " . $e->getMessage(), 'red');
    out($e->getFile() . ':' . $e->getLine(), 'red');
    exit(1);
}
