<?php
/**
 * Endpoint « état de santé » — renvoie en JSON l'état vivant de l'application :
 * configuration, connexion à la base, tables (présence + nombre de lignes),
 * modules métier et endpoints API. Sert à alimenter la « matrice de santé »
 * affichée à la demande sur architecture.php.
 *
 * Authentification : session (réservé aux utilisateurs connectés, comme les pages).
 * Réponse : { ok, generated_at, groups: [ { title, items: [ {name, state, detail} ] } ] }
 *           state ∈ "ok" | "warn" | "error"
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

/* Réservé aux personnes connectées (pas de redirection : on répond en JSON). */
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifié.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Construit un élément de la matrice. */
function item(string $name, string $state, string $detail = ''): array
{
    return ['name' => $name, 'state' => $state, 'detail' => $detail];
}

/* ---- 1. Configuration ---- */
$config = [
    is_installed()
        ? item('config/config.php', 'ok', 'Application configurée')
        : item('config/config.php', 'error', 'Non configurée'),
];

/* ---- 2. Connexion base de données ---- */
$conn = db_can_connect();
$database = [
    $conn['ok']
        ? item('Connexion MySQL', 'ok', 'Base joignable')
        : item('Connexion MySQL', 'error', $conn['error'] ?: 'Injoignable'),
];

/* ---- 3. Tables (présence + nombre de lignes) ---- */
$expectedTables = [
    'users'           => 'Comptes de connexion',
    'cvs'             => 'CV créés',
    'applications'    => 'Candidatures',
    'api_keys'        => 'Clés API',
    'settings'        => 'Réglages (Google…)',
    'password_resets' => 'Liens de réinitialisation',
];
$tables = [];
if ($conn['ok']) {
    foreach ($expectedTables as $name => $label) {
        try {
            $count = (int) db()->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
            $tables[] = item($name, 'ok', $count . ' ligne' . ($count > 1 ? 's' : '') . ' · ' . $label);
        } catch (Throwable $e) {
            // Table absente : avertissement (certaines se créent à la demande).
            $tables[] = item($name, 'warn', 'Absente (créée au besoin)');
        }
    }
} else {
    foreach ($expectedTables as $name => $label) {
        $tables[] = item($name, 'error', 'Base injoignable');
    }
}

/* ---- 4. Modules métier (includes/) ---- */
$expectedModules = [
    'db.php', 'guard.php', 'auth.php', 'account.php', 'cv.php',
    'applications.php', 'api_keys.php', 'settings.php',
    'google_auth.php', 'password_reset.php',
];
$modules = [];
foreach ($expectedModules as $f) {
    $path = __DIR__ . '/../includes/' . $f;
    $modules[] = file_exists($path)
        ? item($f, 'ok', 'Présent')
        : item($f, 'error', 'Manquant');
}

/* ---- 5. Endpoints API (api/) ---- */
$expectedApi = ['cv.php' => 'CV (lecture/écriture)', 'cv_profile.php' => 'Profil riche', 'health.php' => 'État de santé'];
$api = [];
foreach ($expectedApi as $f => $label) {
    $path = __DIR__ . '/' . $f;
    $api[] = file_exists($path)
        ? item('api/' . $f, 'ok', $label)
        : item('api/' . $f, 'error', 'Manquant');
}

$groups = [
    ['title' => 'Configuration', 'items' => $config],
    ['title' => 'Base de données', 'items' => $database],
    ['title' => 'Tables',          'items' => $tables],
    ['title' => 'Modules métier',  'items' => $modules],
    ['title' => 'Endpoints API',   'items' => $api],
];

/* État global : error si une erreur existe, sinon warn si un avertissement, sinon ok. */
$worst = 'ok';
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        if ($it['state'] === 'error') { $worst = 'error'; break 2; }
        if ($it['state'] === 'warn')  { $worst = 'warn'; }
    }
}

echo json_encode([
    'ok'           => $worst !== 'error',
    'state'        => $worst,
    'generated_at' => date('c'),
    'groups'       => $groups,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
