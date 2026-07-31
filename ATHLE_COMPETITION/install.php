<?php
/**
 * Assistant de configuration — à ouvrir dans un navigateur.
 *
 * Le dépôt ne contient pas config/config.local.php (il porte le mot de passe de
 * la base, donc il est ignoré par Git et n'est jamais déployé). Sans lui,
 * l'application retombait sur les valeurs de développement — root sans mot de
 * passe — et affichait une erreur SQLSTATE 1045 renvoyant vers XAMPP et une
 * commande CLI, injouables depuis un hébergement mutualisé.
 *
 * Cette page remplace ce cul-de-sac : elle demande les identifiants, vérifie la
 * connexion, crée la base au besoin, applique le schéma et écrit le fichier de
 * configuration. index.php y renvoie automatiquement tant que la base n'est pas
 * joignable.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/Disciplines.php';
require __DIR__ . '/src/Schema.php';

const LOCAL_CONFIG = __DIR__ . '/config/config.local.php';

/* ─────────────────────────────────────────────────────────────────────────
   Connexions d'essai (indépendantes de db(), qui met sa poignée en cache)
   ───────────────────────────────────────────────────────────────────────── */

/** @param array{host:string,port:int,name:string,user:string,password:string} $c */
function try_connect(array $c, bool $withDatabase = true): PDO
{
    $dsn = $withDatabase
        ? sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name'])
        : sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], $c['port']);

    return new PDO($dsn, $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/** Requête depuis la machine du serveur ? Sert d'unique exception au verrou. */
function is_local_request(): bool
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
    return in_array($ip, ['127.0.0.1', '::1', ''], true)
        && in_array($host, ['localhost', '127.0.0.1', ''], true);
}

/* ─────────────────────────────────────────────────────────────────────────
   État actuel
   ───────────────────────────────────────────────────────────────────────── */

$cfg = app_config()['db'];
$current = [
    'host'     => (string) $cfg['host'],
    'port'     => (int) $cfg['port'],
    'name'     => (string) $cfg['name'],
    'user'     => (string) $cfg['user'],
    'password' => (string) $cfg['password'],
];

$connected = false;
$connError = '';
$tables    = [];
$counts    = ['competitions' => null, 'cities' => null];

try {
    $pdo = try_connect($current);
    $connected = true;
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('competitions', $tables, true)) {
        $counts['competitions'] = (int) $pdo->query('SELECT COUNT(*) FROM competitions')->fetchColumn();
        $counts['cities']       = (int) $pdo->query('SELECT COUNT(*) FROM cities')->fetchColumn();
    }
} catch (Throwable $e) {
    $connError = $e->getMessage();
}

/* Tables attendues par l'application. */
$REQUIRED = ['competitions', 'cities', 'city_aliases', 'places', 'import_runs', 'disciplines', 'competition_disciplines'];
$missing  = $connected ? array_values(array_diff($REQUIRED, $tables)) : $REQUIRED;
$ready    = $connected && !$missing;

/* Verrou : une fois l'application opérationnelle, on ne laisse pas n'importe
   qui la rebrancher sur une autre base. Preuve demandée = le mot de passe
   actuel de la base. S'il est vide (poste de développement), seule une requête
   locale peut reconfigurer. */
$locked = $ready && !($current['password'] === '' && is_local_request());

$error    = '';
$notice   = '';
$form     = $current;
$unlocked = !$locked;

/* ─────────────────────────────────────────────────────────────────────────
   Actions
   ───────────────────────────────────────────────────────────────────────── */

$action = (string) ($_POST['action'] ?? '');

if ($action !== '' && $locked) {
    // Reconfiguration d'une installation qui marche : on exige le mot de passe
    // actuel de la base. Si ce mot de passe est VIDE, la preuve ne prouve rien
    // (hash_equals('','') est vrai) : on refuse alors toute reconfiguration par
    // le web — il reste à supprimer config/config.local.php par FTP.
    $proof = (string) ($_POST['current_password'] ?? '');
    if ($current['password'] !== '' && hash_equals($current['password'], $proof)) {
        $unlocked = true;
    } else {
        $error  = $current['password'] === ''
            ? 'Le mot de passe de la base est vide : impossible de vérifier ton identité. '
            . 'Supprime config/config.local.php sur le serveur pour repartir de zéro.'
            : 'Mot de passe actuel de la base incorrect.';
        $action = '';
    }
}

/* L'utilisateur vient de déverrouiller : on lui montre le formulaire, même si
   l'installation est par ailleurs opérationnelle. */
$justUnlocked = $locked && $unlocked;

if ($action === 'save' && $unlocked) {
    $form = [
        'host'     => trim((string) ($_POST['host'] ?? '')) ?: 'localhost',
        'port'     => (int) ($_POST['port'] ?? 3306) ?: 3306,
        'name'     => trim((string) ($_POST['name'] ?? '')),
        'user'     => trim((string) ($_POST['user'] ?? '')),
        'password' => (string) ($_POST['password'] ?? ''),
    ];

    if ($form['name'] === '' || $form['user'] === '') {
        $error = 'Le nom de la base et l’utilisateur sont obligatoires.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $form['name'])) {
        $error = 'Nom de base invalide : lettres, chiffres et « _ » uniquement.';
    } else {
        $ok = false;
        try {
            try_connect($form);
            $ok = true;
        } catch (Throwable $e) {
            // La base n'existe peut-être pas encore : on essaie de la créer.
            try {
                $server = try_connect($form, false);
                if (($why = schema_create_database($server, $form['name'])) !== null) {
                    $error = "La base « {$form['name']} » n’existe pas et n’a pas pu être créée "
                           . "(beaucoup d’hébergeurs l’interdisent). Crée-la depuis le panneau de "
                           . "ton hébergeur, puis reviens ici.\n\nDétail : " . $why;
                } else {
                    try_connect($form);
                    $ok = true;
                }
            } catch (Throwable $e2) {
                if ($error === '') {
                    $error = "Connexion refusée : vérifie l’hôte, l’utilisateur et le mot de passe.\n\nDétail : "
                           . $e2->getMessage();
                }
            }
        }

        if ($ok) {
            $code = "<?php\n"
                  . "/* Configuration serveur — GÉNÉRÉ par install.php, hors dépôt.\n"
                  . "   Pour la modifier : rouvre install.php. */\n"
                  . 'return ' . var_export(['db' => $form + ['charset' => 'utf8mb4']], true) . ";\n";

            if (@file_put_contents(LOCAL_CONFIG, $code, LOCK_EX) === false) {
                $error = "Connexion réussie, mais impossible d’écrire config/config.local.php "
                       . "(droits du dossier). Crée le fichier toi-même avec ce contenu :\n\n" . $code;
            } else {
                if (function_exists('opcache_invalidate')) @opcache_invalidate(LOCAL_CONFIG, true);
                header('Location: install.php?saved=1');
                exit;
            }
        }
    }
}

if ($action === 'schema' && $unlocked && $connected) {
    try {
        $r = schema_apply(try_connect($current));
        $bits = ["{$r['statements']} instructions appliquées"];
        if ($r['columns'] > 0)     $bits[] = "{$r['columns']} colonne(s) ajoutée(s)";
        if ($r['disciplines'] > 0) $bits[] = "{$r['disciplines']} discipline(s) indexée(s)";
        header('Location: install.php?schema=' . rawurlencode(implode(', ', $bits)));
        exit;
    } catch (Throwable $e) {
        $error = "Le schéma n’a pas pu être appliqué.\n\nDétail : " . $e->getMessage();
    }
}

if (isset($_GET['saved']))  $notice = 'Configuration enregistrée et connexion vérifiée.';
if (isset($_GET['schema'])) $notice = 'Schéma à jour : ' . (string) $_GET['schema'] . '.';

/* Le formulaire s'affiche tant que ce n'est pas prêt, ou sur demande explicite. */
$showForm = $unlocked && (!$ready || $justUnlocked || isset($_GET['reconfigure']) || $error !== '');

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Compétitions d'athlétisme — Configuration</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:#0d1117;color:#e6edf3;padding:28px 20px;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;display:flex;justify-content:center}
  .wrap{width:100%;max-width:620px}
  h1{font-size:1.3rem;margin:0 0 6px}
  .sub{color:#8b949e;font-size:.9rem;margin:0 0 24px}
  .panel{background:#161b22;border:1px solid #30363d;border-radius:14px;padding:22px;margin-bottom:18px}
  h2{font-size:1rem;margin:0 0 14px;color:#c9d1d9}
  .msg{padding:11px 13px;border-radius:9px;font-size:.87rem;margin-bottom:16px;white-space:pre-wrap;line-height:1.5}
  .msg.ok{background:rgba(46,160,67,.14);border:1px solid #2ea043;color:#7ee2a8}
  .msg.ko{background:rgba(248,81,73,.12);border:1px solid #f85149;color:#ffb3ae}
  .msg.info{background:rgba(31,111,235,.12);border:1px solid #1f6feb;color:#a5c8ff}
  label{display:block;font-size:.8rem;color:#8b949e;margin:14px 0 5px}
  input{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #30363d;background:#0d1117;
        color:#e6edf3;font-size:14px;font-family:inherit}
  input:focus{outline:none;border-color:#1f6feb}
  .row{display:grid;grid-template-columns:2fr 1fr;gap:12px}
  button{margin-top:20px;width:100%;padding:11px;border-radius:8px;border:1px solid #238636;background:#238636;
         color:#fff;font-weight:600;font-size:14px;cursor:pointer;font-family:inherit}
  button:hover{background:#2ea043}
  button.grey{background:transparent;border-color:#30363d;color:#8b949e}
  button.grey:hover{background:#21262d}
  table{width:100%;border-collapse:collapse;font-size:.87rem}
  td{padding:7px 4px;border-bottom:1px solid #21262d}
  td:first-child{color:#8b949e;width:45%}
  .ok{color:#7ee2a8}.ko{color:#ffb3ae}
  a{color:#58a6ff}
  .go{display:block;text-align:center;margin-top:16px;padding:12px;border-radius:8px;
      background:#1f6feb;color:#fff;text-decoration:none;font-weight:600}
  .go:hover{background:#388bfd}
  code{background:#0d1117;border:1px solid #30363d;border-radius:5px;padding:1px 5px;font-size:.85em}
  .hint{color:#6e7681;font-size:.79rem;margin-top:6px;line-height:1.5}
</style>
</head>
<body>
<div class="wrap">
  <h1>Compétitions d'athlétisme</h1>
  <p class="sub">Configuration de la base de données</p>

  <?php if ($notice): ?><div class="msg ok"><?= $h($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="msg ko"><?= $h($error) ?></div><?php endif; ?>

  <div class="panel">
    <h2>État</h2>
    <table>
      <tr><td>Fichier de configuration</td>
          <td class="<?= is_file(LOCAL_CONFIG) ? 'ok' : 'ko' ?>">
            <?= is_file(LOCAL_CONFIG) ? 'config/config.local.php présent' : 'absent — valeurs de développement utilisées' ?></td></tr>
      <tr><td>Connexion à la base</td>
          <td class="<?= $connected ? 'ok' : 'ko' ?>">
            <?= $connected ? 'établie (' . $h($current['user']) . '@' . $h($current['host']) . ' → ' . $h($current['name']) . ')' : 'impossible' ?></td></tr>
      <?php if (!$connected && $connError): ?>
      <tr><td>Erreur</td><td class="ko"><?= $h($connError) ?></td></tr>
      <?php endif; ?>
      <?php if ($connected): ?>
      <tr><td>Tables</td>
          <td class="<?= $missing ? 'ko' : 'ok' ?>">
            <?= $missing ? 'manquantes : ' . $h(implode(', ', $missing)) : count($tables) . ' tables en place' ?></td></tr>
      <?php endif; ?>
      <?php if ($counts['competitions'] !== null): ?>
      <tr><td>Données</td>
          <td class="<?= $counts['competitions'] > 0 ? 'ok' : '' ?>">
            <?= (int) $counts['competitions'] ?> compétitions, <?= (int) $counts['cities'] ?> villes</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <?php if (!$unlocked): ?>
    <div class="panel">
      <h2>Reconfigurer</h2>
      <div class="msg info">L'application fonctionne. Pour la brancher sur une autre base, saisis le mot de passe actuel de la base — c'est ce qui empêche un visiteur de la détourner.</div>
      <form method="post">
        <input type="hidden" name="action" value="unlock">
        <label for="cp">Mot de passe actuel de la base</label>
        <input type="password" id="cp" name="current_password" autocomplete="off" autofocus>
        <button type="submit">Déverrouiller</button>
      </form>
      <a class="go" href="index.php">Ouvrir l'application →</a>
    </div>

  <?php elseif ($showForm): ?>
    <div class="panel">
      <h2>Identifiants de la base</h2>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <?php /* Reporte la preuve d'identité pour que l'enregistrement passe le verrou. */ ?>
        <input type="hidden" name="current_password" value="<?= $h($_POST['current_password'] ?? '') ?>">
        <div class="row">
          <div><label for="host">Hôte</label>
               <input type="text" id="host" name="host" value="<?= $h($form['host']) ?>" required></div>
          <div><label for="port">Port</label>
               <input type="number" id="port" name="port" value="<?= (int) $form['port'] ?>"></div>
        </div>
        <label for="name">Nom de la base</label>
        <input type="text" id="name" name="name" value="<?= $h($form['name']) ?>" required>
        <label for="user">Utilisateur</label>
        <input type="text" id="user" name="user" value="<?= $h($form['user']) ?>" required>
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" value="<?= $h($form['password']) ?>" autocomplete="off">
        <p class="hint">Chez Hostinger, la base et l'utilisateur portent un préfixe de compte
          (<code>u123456789_athle</code>). Si la base n'existe pas encore, elle sera créée
          automatiquement lorsque l'hébergeur l'autorise.</p>
        <button type="submit">Vérifier et enregistrer</button>
      </form>
    </div>

  <?php endif; ?>

  <?php if ($unlocked && $connected && $missing): ?>
    <div class="panel">
      <h2>Tables manquantes</h2>
      <div class="msg info">La connexion fonctionne mais le schéma n'est pas en place. Cette étape crée les tables ; elle est sans risque sur une base déjà remplie (rien n'est supprimé).</div>
      <form method="post">
        <input type="hidden" name="action" value="schema">
        <input type="hidden" name="current_password" value="<?= $h($_POST['current_password'] ?? '') ?>">
        <button type="submit">Créer les tables</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($ready && !$locked): ?>
    <div class="panel">
      <h2>Prêt</h2>
      <?php if ((int) $counts['competitions'] === 0): ?>
        <div class="msg info">Les tables sont en place mais la base est vide. Importe <code>sql/dump.sql.gz</code> depuis phpMyAdmin, ou lance le scraper — voir <code>DEPLOIEMENT.md</code>.</div>
      <?php endif; ?>
      <a class="go" href="index.php">Ouvrir l'application →</a>
      <form method="post" style="margin-top:6px">
        <input type="hidden" name="action" value="noop">
        <a href="?reconfigure=1"><button type="button" class="grey">Changer les identifiants</button></a>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
