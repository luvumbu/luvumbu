<?php
// OUTIL TEMPORAIRE DE RÉPARATION — À SUPPRIMER DU SERVEUR APRÈS USAGE.
//
// 1. Liste toutes les bases accessibles avec les identifiants déjà présents
//    dans config/config.php, et compte les articles de chacune.
// 2. Permet de rebrancher le site sur la bonne base en un clic
//    (réécrit uniquement DB_NAME dans config/config.php).
//
// Aucune donnée n'est modifiée dans les bases : on ne fait que lire, puis
// réécrire le fichier de configuration.

$configFile = __DIR__ . '/config/config.php';

if (!file_exists($configFile)) {
    exit('config/config.php est absent : lance d\'abord install.php.');
}
require_once $configFile;

header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><style>
body{font-family:system-ui,sans-serif;max-width:900px;margin:40px auto;padding:0 16px;line-height:1.6}
table{border-collapse:collapse;width:100%;margin:18px 0}
th,td{border:1px solid #ddd;padding:9px 12px;text-align:left}
th{background:#f5f5f5}
.ok{background:#dcfce7}
.btn{display:inline-block;padding:7px 14px;border-radius:8px;background:#c98a00;color:#fff;text-decoration:none;font-weight:600}
code{background:#f1f1f1;padding:2px 6px;border-radius:4px}
.warn{background:#fff7e6;border:1px solid #f4c14b;padding:12px 16px;border-radius:10px}
</style>';
echo '<h1>Réparation du blog</h1>';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    exit('<p class="warn">Connexion MySQL impossible : ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// --- Action : rebrancher le site sur la base choisie ---
$switchTo = $_GET['use'] ?? '';
if ($switchTo !== '') {
    $bases = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($switchTo, $bases, true)) {
        exit('<p class="warn">Base inconnue.</p>');
    }
    $cfg  = "<?php\n";
    $cfg .= "define('DB_HOST', " . var_export(DB_HOST, true) . ");\n";
    $cfg .= "define('DB_NAME', " . var_export($switchTo, true) . ");\n";
    $cfg .= "define('DB_USER', " . var_export(DB_USER, true) . ");\n";
    $cfg .= "define('DB_PASS', " . var_export(DB_PASS, true) . ");\n";

    copy($configFile, $configFile . '.bak');           // filet de sécurité
    if (file_put_contents($configFile, $cfg) === false) {
        exit('<p class="warn">Impossible d\'écrire config/config.php (permissions).</p>');
    }
    echo '<p class="warn">✅ Le site est maintenant branché sur <code>' . htmlspecialchars($switchTo) . '</code>.<br>
          Ouvre <a href="index.php">la page d\'accueil</a> pour vérifier que tes articles sont revenus,
          puis <strong>supprime ce fichier <code>_reparer.php</code> du serveur</strong>.</p>';
}

// --- Inventaire des bases ---
echo '<p>Base actuellement utilisée par le site : <code>' . htmlspecialchars(DB_NAME) . '</code></p>';
echo '<table><tr><th>Base de données</th><th>Table <code>articles</code></th><th>Articles</th><th>Quiz</th><th></th></tr>';

$bases = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
$ignore = ['information_schema', 'performance_schema', 'mysql', 'sys'];

foreach ($bases as $b) {
    if (in_array(strtolower($b), $ignore, true)) continue;

    $nbArticles = null;
    $nbQuiz     = null;
    try {
        $pdo->exec("USE `$b`");
        $has = $pdo->query("SHOW TABLES LIKE 'articles'")->fetchColumn();
        if ($has) {
            $nbArticles = (int)$pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
            $hasQz = $pdo->query("SHOW TABLES LIKE 'quizzes'")->fetchColumn();
            if ($hasQz) $nbQuiz = (int)$pdo->query('SELECT COUNT(*) FROM quizzes')->fetchColumn();
        }
    } catch (PDOException $e) {
        // base non lisible : on l'affiche quand même
    }

    $isCurrent = ($b === DB_NAME);
    $good      = ($nbArticles !== null && $nbArticles > 0);

    echo '<tr class="' . ($good ? 'ok' : '') . '">';
    echo '<td><code>' . htmlspecialchars($b) . '</code>' . ($isCurrent ? ' <em>(actuelle)</em>' : '') . '</td>';
    echo '<td>' . ($nbArticles === null ? '—' : 'oui') . '</td>';
    echo '<td>' . ($nbArticles === null ? '—' : $nbArticles) . '</td>';
    echo '<td>' . ($nbQuiz === null ? '—' : $nbQuiz) . '</td>';
    echo '<td>' . ($good && !$isCurrent
        ? '<a class="btn" href="?use=' . urlencode($b) . '">Brancher le site sur cette base</a>'
        : '') . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '<p class="warn">⚠️ Ce fichier donne accès à tes bases : <strong>supprime-le du serveur dès que le blog est réparé</strong>.</p>';
