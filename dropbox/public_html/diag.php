<?php
// === Diagnostic PhotoSync (LECTURE SEULE) ===
// Affiche en JSON l'état du déploiement : fichiers présents, base, tables, droits.
// N'expose AUCUN identifiant (ni mot de passe, ni nom de base).
// À ouvrir : https://luvumbu.com/diag.php
// ⚠️ À SUPPRIMER après usage (comme install.php).

header('Content-Type: application/json; charset=utf-8');

$root = __DIR__;

// 1) Fichiers/dossiers attendus
$expected = [
    'index.php', 'install.php',
    'lib/bootstrap.php', 'lib/config.php', 'lib/Db.php', 'lib/Api.php', 'lib/Auth.php', 'lib/Photos.php',
    'api/register.php', 'api/login.php', 'api/upload.php', 'api/check.php',
    'api/feed.php', 'api/media.php', 'api/setup.php',
    'web/gallery.php', 'web/upload_web.php',
    'uploads',
];
$structure = [];
foreach ($expected as $p) {
    $structure[$p] = file_exists("$root/$p");
}

$report = [
    'php'                => PHP_VERSION,
    'extensions'         => ['pdo_mysql' => extension_loaded('pdo_mysql'), 'gd' => extension_loaded('gd')],
    'structure'          => $structure,
    'all_files_present'  => !in_array(false, $structure, true),
];

// 2) Base de données (sans exposer les identifiants)
try {
    require_once "$root/lib/bootstrap.php";
    $report['db_config_file'] = is_file("$root/lib/db.config.php");
    $report['table_prefix']   = DB_PREFIX;

    try {
        Db::pdo()->query('SELECT 1');
        $report['db_connected'] = true;

        $tables = [];
        foreach ([TBL_USERS, TBL_PHOTOS] as $t) {
            try {
                $n = Db::pdo()->query("SELECT COUNT(*) c FROM `$t`")->fetch(PDO::FETCH_ASSOC)['c'];
                $tables[$t] = (int) $n;
            } catch (Throwable $e) {
                $tables[$t] = 'ABSENTE';
            }
        }
        $report['tables'] = $tables;
        $report['uploads_writable'] = is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR);
    } catch (Throwable $e) {
        $report['db_connected'] = false;
        $report['db_error'] = $e->getMessage();
    }
} catch (Throwable $e) {
    $report['bootstrap_error'] = $e->getMessage();
}

$report['verdict'] = ($report['all_files_present'] ?? false)
    && ($report['db_connected'] ?? false)
    && ($report['tables'][TBL_USERS ?? ''] ?? 'ABSENTE') !== 'ABSENTE'
        ? 'OK — déploiement complet et fonctionnel'
        : 'INCOMPLET — voir les détails ci-dessus';

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
