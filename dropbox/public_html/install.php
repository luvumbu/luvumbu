<?php
// === Assistant d'installation / configuration PhotoSync ===
// 1) Détecte si le serveur est correctement paramétré (connexion BD + tables).
// 2) Sinon, propose un FORMULAIRE pour saisir les identifiants MySQL, puis écrit
//    automatiquement db.config.php (aucune édition de fichier à la main).
// 3) Crée les tables (préfixées, donc compatibles avec une base partagée) et vérifie tout.
//   À ouvrir : https://luvumbu.com/install.php
//   ⚠️ SÉCURITÉ : ce fichier n'est PAS protégé par mot de passe. SUPPRIME-LE
//   du serveur juste après l'installation (sinon n'importe qui pourrait reconfigurer la base).

require __DIR__ . '/lib/bootstrap.php';

/** Tente une connexion PDO sur une base précise ; renvoie l'objet ou null. */
function pdoTry(string $host, string $name, string $user, string $pass): ?PDO {
    try {
        return new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable $e) {
        return null;
    }
}

/** Connexion au serveur MySQL SANS choisir de base (pour pouvoir la créer). */
function pdoServer(string $host, string $user, string $pass): ?PDO {
    try {
        return new PDO(
            "mysql:host=$host;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable $e) {
        return null;
    }
}

// Valeurs du formulaire (pré-remplies avec la config actuelle).
$form = [
    'host'   => DB_HOST,
    'name'   => DB_NAME,
    'user'   => DB_USER,
    'pass'   => DB_PASS,
    'prefix' => DB_PREFIX,
];
$formError = '';

// --- Étape « Enregistrer la configuration » ---
if (($_POST['action'] ?? '') === 'save') {
    $form['host']   = trim($_POST['db_host'] ?? '');
    $form['name']   = trim($_POST['db_name'] ?? '');
    $form['user']   = trim($_POST['db_user'] ?? '');
    $form['pass']   = (string) ($_POST['db_pass'] ?? '');
    $form['prefix'] = preg_replace('/[^A-Za-z0-9_]/', '', $_POST['db_prefix'] ?? '') ?: 'photosync_';

    $canSave = false;
    if ($form['name'] === '' || $form['user'] === '') {
        $formError = 'Renseigne au moins le nom de la base et l’utilisateur.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $form['name'])) {
        $formError = 'Nom de base invalide : lettres, chiffres et « _ » uniquement.';
    } else {
        // 1) La base existe-t-elle déjà avec ces identifiants ?
        $ok = (bool) pdoTry($form['host'], $form['name'], $form['user'], $form['pass']);

        // 2) Sinon, on tente de la CRÉER automatiquement — aucune manipulation manuelle de la base.
        if (!$ok) {
            $srv = pdoServer($form['host'], $form['user'], $form['pass']);
            if (!$srv) {
                $formError = "Connexion refusée : vérifie l'utilisateur, le mot de passe et l'hôte.";
            } else {
                try {
                    $srv->exec("CREATE DATABASE IF NOT EXISTS `{$form['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $ok = (bool) pdoTry($form['host'], $form['name'], $form['user'], $form['pass']);
                    if (!$ok) {
                        $formError = "Base « {$form['name']} » créée, mais cet utilisateur n'y a pas accès. Donne-lui les droits sur cette base.";
                    }
                } catch (Throwable $e) {
                    $formError = "La base « {$form['name']} » n'existe pas et n'a pas pu être créée automatiquement "
                               . "(ton hébergeur ne l'autorise peut-être pas). Crée-la côté hébergeur, puis relance.\n\nDétail : " . $e->getMessage();
                }
            }
        }
        $canSave = $ok && $formError === '';
    }

    if ($canSave) {
        // Connexion OK → on écrit db.config.php puis on recharge la page (config fraîche).
        $conf = [
            'host'   => $form['host'],
            'name'   => $form['name'],
            'user'   => $form['user'],
            'pass'   => $form['pass'],
            'prefix' => $form['prefix'],
        ];
        $code = "<?php\n// Généré automatiquement par install.php — identifiants de la base.\n"
              . "// Ne pas committer ce fichier. Pour le régénérer : relance install.php.\n"
              . "return " . var_export($conf, true) . ";\n";

        if (@file_put_contents(__DIR__ . '/lib/db.config.php', $code) === false) {
            $formError = "Impossible d'écrire lib/db.config.php (droits du dossier). "
                       . "Crée ce fichier dans le dossier lib/ avec ce contenu :\n\n" . $code;
        } else {
            header('Location: install.php');
            exit;
        }
    }
}

// --- Détection : la base est-elle joignable avec la config actuelle ? ---
$connected = false;
$connErr = '';
if (DB_NAME !== '') {
    try {
        Db::pdo()->query('SELECT 1');
        $connected = true;
    } catch (Throwable $e) {
        $connErr = $e->getMessage();
    }
}

// On montre le formulaire si : non connecté, OU reconfiguration demandée, OU erreur de saisie.
$showForm = !$connected || isset($_GET['reconfig']) || $formError !== '';

// ============================================================================
//  VUE 1 — Formulaire de configuration
// ============================================================================
if ($showForm):
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?= Pwa::head('.') ?>
<title>PhotoSync — Configuration</title>
<style>
  body { font-family:system-ui,sans-serif; max-width:560px; margin:24px auto; padding:0 16px; background:#0b1220; color:#e2e8f0; }
  h1 { font-size:22px; } p.sub { color:#8aa0bd; }
  label { display:block; margin-top:14px; font-size:14px; color:#cbd5e1; }
  input { width:100%; box-sizing:border-box; padding:11px; margin-top:5px; border-radius:9px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:15px; }
  .row2 { display:flex; gap:12px; } .row2 > div { flex:1; }
  button { width:100%; margin-top:20px; padding:13px; border:0; border-radius:11px; background:#1565C0; color:#fff; font-size:16px; font-weight:700; cursor:pointer; }
  .err { white-space:pre-wrap; background:#3b0d0d; border:1px solid #7f1d1d; color:#fca5a5; padding:12px; border-radius:9px; margin-top:14px; font-size:13px; }
  .info { background:#16213a; padding:12px 14px; border-radius:10px; font-size:13px; color:#9fb3cd; margin-top:10px; }
  code { background:#0f172a; padding:1px 5px; border-radius:4px; }
</style>
</head>
<body>
  <h1>⚙️ Configuration de PhotoSync</h1>
  <p class="sub">Renseigne ta base de données MySQL. Les réglages seront enregistrés automatiquement.</p>

  <?php if (!$connected && DB_NAME !== '' && $formError === ''): ?>
    <div class="err">Le serveur n'est pas encore configuré (connexion à la base impossible).<?php if ($connErr): ?>

Détail : <?= $h($connErr) ?><?php endif; ?></div>
  <?php elseif (DB_NAME === '' && $formError === ''): ?>
    <div class="info">👋 Première installation : indique juste les identifiants MySQL.
      <b>Si la base n'existe pas encore, elle sera créée automatiquement</b> — tu n'as rien à faire
      dans phpMyAdmin. (En local XAMPP : hôte <code>localhost</code>, utilisateur <code>root</code>,
      mot de passe vide.)</div>
  <?php endif; ?>

  <?php if ($formError): ?><div class="err"><?= $h($formError) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="save">

    <label>Hôte de la base
      <input type="text" name="db_host" value="<?= $h($form['host']) ?>" placeholder="localhost">
    </label>
    <label>Nom de la base
      <input type="text" name="db_name" value="<?= $h($form['name']) ?>" placeholder="u489596434_photos" required>
    </label>
    <label>Utilisateur
      <input type="text" name="db_user" value="<?= $h($form['user']) ?>" placeholder="u489596434_photos" required>
    </label>
    <label>Mot de passe
      <input type="text" name="db_pass" value="<?= $h($form['pass']) ?>" placeholder="mot de passe MySQL">
    </label>
    <label>Préfixe des tables <small>(évite les conflits si la base est partagée)</small>
      <input type="text" name="db_prefix" value="<?= $h($form['prefix']) ?>" placeholder="photosync_">
    </label>

    <button type="submit">Enregistrer et vérifier</button>
  </form>
  <div class="info">Tables qui seront utilisées :
    <code><?= $h($form['prefix']) ?>users</code> et <code><?= $h($form['prefix']) ?>photos</code>.</div>
</body>
</html>
<?php
    exit;
endif;

// ============================================================================
//  VUE 2 — Vérification & installation (connexion OK)
// ============================================================================
$checks = [];
function check(array &$c, string $label, bool $ok, string $detail = ''): bool {
    $c[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    return $ok;
}

check($checks, 'Version de PHP', version_compare(PHP_VERSION, '7.4', '>='), 'PHP ' . PHP_VERSION);
check($checks, 'Extension PDO MySQL', extension_loaded('pdo_mysql'));
check($checks, 'Connexion MySQL', true, 'Base : ' . DB_NAME);

// Tables (préfixées) : photos via install, users + colonnes via ensureSchema.
try {
    Db::pdo()->exec(
        'CREATE TABLE IF NOT EXISTS ' . TBL_PHOTOS . ' (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sha256        CHAR(64)        NOT NULL,
            original_name VARCHAR(255)    NOT NULL,
            stored_path   VARCHAR(512)    NOT NULL,
            size_bytes    BIGINT UNSIGNED NOT NULL,
            taken_at      DATETIME        NULL,
            uploaded_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at    DATETIME        NULL DEFAULT NULL,
            INDEX idx_taken (taken_at),
            INDEX idx_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    Auth::ensureSchema();   // table users + colonnes user_id/deleted_at + index par compte
    Albums::ensureSchema(); // tables des albums partageables (dossiers + liaisons)
    $count = (int) Db::pdo()->query('SELECT COUNT(*) c FROM ' . TBL_PHOTOS . ' WHERE deleted_at IS NULL')
                            ->fetch(PDO::FETCH_ASSOC)['c'];
    check($checks, 'Tables « ' . DB_PREFIX . 'users / ' . DB_PREFIX . 'photos »', true, "OK ($count photo(s) active(s))");

    $nAlb = (int) Db::pdo()->query('SELECT COUNT(*) c FROM ' . TBL_ALBUMS)->fetch(PDO::FETCH_ASSOC)['c'];
    check($checks, 'Tables des albums partageables', true, "OK ($nAlb album(s))");
} catch (Throwable $e) {
    check($checks, 'Tables PhotoSync', false, $e->getMessage());
}

// Dossier uploads
$dirOk = is_dir(UPLOAD_DIR) || @mkdir(UPLOAD_DIR, 0775, true);
check($checks, 'Dossier uploads', $dirOk && is_writable(UPLOAD_DIR),
    $dirOk ? (is_writable(UPLOAD_DIR) ? UPLOAD_DIR : 'présent mais NON inscriptible (chmod 775)') : 'impossible à créer');

// Protection .htaccess dans uploads
$ht = UPLOAD_DIR . '/.htaccess';
if (!file_exists($ht) && $dirOk) {
    @file_put_contents($ht, "php_flag engine off\nOptions -Indexes\n");
}
check($checks, 'Protection uploads/.htaccess', file_exists($ht));

// Limites d'upload PHP (informatif)
check($checks, 'Limite upload_max_filesize', true, ini_get('upload_max_filesize'));
check($checks, 'Limite post_max_size', true, ini_get('post_max_size'));

$allOk = array_reduce($checks, fn($acc, $c) => $acc && $c['ok'], true);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= Pwa::head('.') ?>
<title>PhotoSync — Installation</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 680px; margin: 24px auto; padding: 0 16px; }
  h1 { font-size: 22px; }
  .row { display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:8px; margin:6px 0; background:#f4f4f5; }
  .ok { background:#e7f7ec; } .ko { background:#fde8e8; }
  .label { font-weight:600; } .detail { color:#555; font-size:13px; text-align:right; word-break:break-word; }
  .banner { padding:14px; border-radius:10px; font-weight:700; margin:16px 0; }
  .green { background:#1f9d55; color:#fff; } .red { background:#cc1f1a; color:#fff; }
  a.btn { display:inline-block; margin-top:10px; padding:10px 16px; background:#1565C0; color:#fff; border-radius:8px; text-decoration:none; }
  a.link { color:#1565C0; font-size:14px; }
  .warn { background:#fff7e6; padding:12px; border-radius:8px; font-size:14px; margin-top:10px; }
</style>
</head>
<body>
  <h1>PhotoSync — Installation</h1>

  <div class="banner <?= $allOk ? 'green' : 'red' ?>">
    <?= $allOk ? '✅ Tout est prêt ! Le serveur est configuré.' : '⚠️ Il reste un point à corriger (voir en rouge ci-dessous).' ?>
  </div>

  <?php foreach ($checks as $c): ?>
    <div class="row <?= $c['ok'] ? 'ok' : 'ko' ?>">
      <span class="label"><?= $c['ok'] ? '✅' : '❌' ?> <?= htmlspecialchars($c['label']) ?></span>
      <span class="detail"><?= htmlspecialchars($c['detail']) ?></span>
    </div>
  <?php endforeach; ?>

  <p><a class="link" href="install.php?reconfig=1">⚙️ Modifier la configuration de la base</a></p>

  <?php if ($allOk): ?>
    <p><a class="btn" href="web/gallery.php">Voir la galerie</a></p>
    <div class="warn">
      🔐 <b>Important :</b> par sécurité, <b>supprime maintenant <code>install.php</code></b> du serveur
      (la configuration reste, elle, dans <code>db.config.php</code>).
    </div>
  <?php endif; ?>
</body>
</html>
