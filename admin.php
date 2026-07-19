<?php
/* ═══════════════════════════════════════════════════════════
   ESPACE ADMIN DU PORTFOLIO — connexion + changement d'apparence.
   Sauvegarde dans config/appearance.json, appliqué par index.php.
   Autonome : ne dépend pas de luvumbu.
   ═══════════════════════════════════════════════════════════ */
session_start();
$CFG     = require __DIR__ . '/config/portfolio.php';
$CFG_PW  = $CFG['admin']['password'] ?? '';   // mot de passe de secours (config)

/* Identifiants MySQL BOKONZI (core/credentials.php → $dbname, $username, $password) */
$BK_DB = $BK_USER = $BK_PASS = '';
$credFile = __DIR__ . '/../core/credentials.php';
if (is_file($credFile)) {
    (function () use (&$BK_DB, &$BK_USER, &$BK_PASS, $credFile) {
        include $credFile;                                 // prod : $dbname,$username,$password
        $localFile = dirname($credFile) . '/credentials_local.php';
        if (is_file($localFile)) include $localFile;       // override LOCAL (comme core/db.php)
        $BK_DB   = $dbname   ?? '';
        $BK_USER = $username ?? '';
        $BK_PASS = $password ?? '';
    })();
}

$appFile = __DIR__ . '/config/appearance.json';
$app     = is_file($appFile) ? (json_decode(file_get_contents($appFile), true) ?: []) : [];
function ea($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$hexok = fn($v) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v);

/* Déconnexion */
if (isset($_GET['logout'])) { unset($_SESSION['pf_admin']); header('Location: admin.php'); exit; }

/* Connexion */
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pw'])) {
    $tryUser = trim((string)($_POST['login_user'] ?? ''));
    $tryPw   = trim((string)$_POST['login_pw']);   // tolère espaces accidentels (copier-coller)
    $ok = false;
    // 1) connexion MySQL réelle (utilisateur + mot de passe) — comme LUVUMBU LAND
    if (function_exists('mysqli_connect') && $tryUser !== '') {
        try {                                          // PHP 8.1+ : mysqli lève une exception si échec
            $c = @mysqli_connect('localhost', $tryUser, $tryPw, $BK_DB ?: '');
            if ($c) { $ok = true; @mysqli_close($c); }
        } catch (\Throwable $e) { /* connexion refusée → on essaie les méthodes de secours */ }
    }
    // 2) comparaison directe aux identifiants BOKONZI (utilisateur + mot de passe)
    if (!$ok && $BK_USER !== '' && $tryUser === $BK_USER && hash_equals((string)$BK_PASS, $tryPw)) $ok = true;
    // 3) secours : mot de passe du config
    if (!$ok && $CFG_PW !== '' && hash_equals(trim($CFG_PW), $tryPw)) $ok = true;

    if ($ok) { $_SESSION['pf_admin'] = true; header('Location: admin.php'); exit; }
    $err = 'Utilisateur ou mot de passe incorrect.';
}
$authed = !empty($_SESSION['pf_admin']);

/* Listes de la carte (modes + univers) — lues depuis config/portfolio.php */
$CARTE_VUES = $CFG['carte']['vues'] ?? [];
$CARTE_APPS = $CFG['carte']['apparences'] ?? [];
$modeKeys   = array_map(fn($v) => $v['mode'], $CARTE_VUES);
$biomeKeys  = array_map(fn($a) => $a['biome'], $CARTE_APPS);

/* Enregistrement de l'apparence */
$saved = false; $saveErr = '';
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_appearance'])) {
    $cm = (string)($_POST['carte_mode'] ?? 'default');
    $cb = (string)($_POST['carte_biome'] ?? '');
    $new = [
        'accent'      => $hexok($_POST['accent'] ?? '')     ? $_POST['accent']     : ($CFG['theme']['accent']),
        'accent_dim'  => $hexok($_POST['accent_dim'] ?? '') ? $_POST['accent_dim'] : ($CFG['theme']['accent_dim']),
        'dark'        => !empty($_POST['dark']),
        'particules'  => !empty($_POST['particules']),
        'carte_mode'  => ($cm === 'default' || in_array($cm, $modeKeys, true))  ? $cm : 'default',
        'carte_biome' => in_array($cb, $biomeKeys, true) ? $cb : '',
    ];
    if (@file_put_contents($appFile, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
        $app = $new; $saved = true;
    } else { $saveErr = "Impossible d'écrire config/appearance.json (droits ?)."; }
}

/* Réinitialisation (retour aux valeurs par défaut du config) */
if ($authed && isset($_GET['reset']) && is_file($appFile)) { @unlink($appFile); $app = []; header('Location: admin.php?ok=reset'); exit; }

/* ═══════════════════════════════════════════════════════════
   PROJETS — point d'entrée (sous-dossier) de chaque dossier de la racine.
   Autonome : scanne la racine du portfolio, enregistre dans config/projets.json.
   inc/carte.php lit CE MÊME fichier pour construire les URL de la carte.
   ═══════════════════════════════════════════════════════════ */
$PROJ_FILE = __DIR__ . '/config/projets.json';
$pfRoot    = __DIR__;                                       // les projets sont à la racine
$pfExclude = array_values(array_unique(array_merge(         // même liste que inc/carte.php
    $CFG['carte']['exclude'] ?? [],
    ['luvumbu', 'config', 'css', 'js', 'inc', 'images', 'vendor', 'node_modules']
)));

/* index présent directement dans ce dossier ? */
$pf_has_index = function ($dir) {
    foreach (['index.php', 'index.html', 'index.htm'] as $f) {
        if (is_file($dir . '/' . $f)) return true;
    }
    return false;
};
/* points d'entrée SUGGÉRÉS : racine, sous-dossiers immédiats, et leurs fichiers index.*
   (le champ reste LIBRE : on peut taper n'importe quel chemin exact fichier/dossier). */
$pf_targets = function ($absDir) use ($pf_has_index) {
    $idx = ['index.php', 'index.html', 'index.htm'];
    $out = [['path' => '', 'file' => false, 'hasIndex' => $pf_has_index($absDir)]];
    foreach ($idx as $f) if (is_file($absDir . '/' . $f)) $out[] = ['path' => $f, 'file' => true, 'hasIndex' => true];
    foreach (glob($absDir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
        $b = basename($sub);
        $out[] = ['path' => $b, 'file' => false, 'hasIndex' => $pf_has_index($sub)];
        foreach ($idx as $f) if (is_file($sub . '/' . $f)) $out[] = ['path' => $b . '/' . $f, 'file' => true, 'hasIndex' => true];
    }
    return $out;
};
/* auto-détection (identique à inc/carte.php) — sert de valeur par défaut du menu */
$pf_auto = function ($cands) {
    if (!empty($cands[0]['hasIndex'])) return '';                 // index à la racine
    foreach ($cands as $c) { if ($c['path'] !== '' && !empty($c['hasIndex'])) return $c['path']; }
    return '';
};

/* dossiers-projets de la racine */
$pfProjects = [];
foreach (scandir($pfRoot) ?: [] as $e) {
    if ($e === '.' || $e === '..' || $e[0] === '.') continue;
    if (in_array($e, $pfExclude, true)) continue;
    if (!is_dir($pfRoot . '/' . $e)) continue;
    $pfProjects[] = $e;
}
sort($pfProjects, SORT_NATURAL | SORT_FLAG_CASE);

/* choix déjà enregistrés */
$pfChoice = is_file($PROJ_FILE) ? (json_decode(file_get_contents($PROJ_FILE), true) ?: []) : [];

/* enregistrement du point d'entrée par projet */
$savedProj = false; $projErr = '';
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_projects'])) {
    $tsel = (array)($_POST['target'] ?? []);
    $new  = [];
    foreach ($pfProjects as $p) {
        $path = isset($tsel[$p]) ? trim(str_replace('\\', '/', (string)$tsel[$p]), '/') : '';
        if ($path !== '') {
            // sécurité : la cible (fichier OU dossier) doit exister SOUS le projet, sans en sortir
            $base = realpath($pfRoot . '/' . $p);
            $full = realpath($pfRoot . '/' . $p . '/' . $path);
            $inside = $base && $full && strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0;
            if (!$inside || !(is_file($full) || is_dir($full))) $path = '';   // invalide → racine
        }
        $new[$p] = $path;                                   // '' = on entre à la racine
    }
    if (@file_put_contents($PROJ_FILE, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
        $pfChoice = $new; $savedProj = true;
    } else {
        $projErr = "Impossible d'écrire config/projets.json (droits ?).";
    }
}

/* candidats + valeur courante de chaque projet (pour le menu) */
$pfCandidates = $pfCurrent = [];
foreach ($pfProjects as $p) {
    $pfCandidates[$p] = $pf_targets($pfRoot . '/' . $p);
    $pfCurrent[$p]    = array_key_exists($p, $pfChoice) ? (string)$pfChoice[$p] : $pf_auto($pfCandidates[$p]);
}

/* Valeurs courantes */
$accent     = $app['accent']     ?? $CFG['theme']['accent'];
$dim        = $app['accent_dim'] ?? $CFG['theme']['accent_dim'];
$dark       = $app['dark']       ?? $CFG['theme']['defaut_sombre'];
$particules = $app['particules'] ?? $CFG['theme']['particules'];
$carteMode  = $app['carte_mode']  ?? 'default';
$carteBiome = $app['carte_biome'] ?? '';
$hasLuvumbu = is_dir(__DIR__ . '/luvumbu');

/* Thèmes complets prêts à l'emploi : [clé, label, accent, accent_dim, sombre] */
$themes = [
    ['violet',   'Violet Nuit',  '#8b78ff', '#6c5ce7', true],
    ['ocean',    'Océan',        '#38bdf8', '#0ea5e9', true],
    ['emeraude', 'Émeraude',     '#34d399', '#10b981', true],
    ['ambre',    'Ambre',        '#fbbf24', '#f59e0b', true],
    ['rose',     'Rubis',        '#fb7185', '#e11d48', true],
    ['cyan',     'Cyan',         '#22d3ee', '#0891b2', true],
    ['fuchsia',  'Fuchsia',      '#e879f9', '#c026d3', true],
    ['clair',    'Clair (jour)', '#6c5ce7', '#5b45d0', false],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Espace admin — Portfolio</title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--acc:<?= ea($accent) ?>;--acc2:<?= ea($dim) ?>;}
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Sora',system-ui,sans-serif;background:#080a10;color:#eef1f8;min-height:100vh;
    display:grid;place-items:center;padding:24px;
    background-image:radial-gradient(600px circle at 50% 0%,color-mix(in srgb,var(--acc) 18%,transparent),transparent 60%);}
  .card{width:min(560px,100%);background:#121620;border:1px solid rgba(255,255,255,.08);border-radius:20px;
    padding:clamp(24px,5vw,40px);box-shadow:0 40px 90px -30px rgba(0,0,0,.7)}
  h1{font-size:1.5rem;font-weight:800;letter-spacing:-.5px;margin-bottom:6px}
  h1 span{color:var(--acc)}
  .sub{color:#8b93a7;font-size:.92rem;margin-bottom:26px}
  label{display:block;font-weight:600;font-size:.9rem;margin:18px 0 8px}
  input[type=text],input[type=password]{width:100%;padding:13px 15px;border-radius:12px;font-size:1rem;
    background:#0d1018;border:1px solid rgba(255,255,255,.1);color:#eef1f8;font-family:inherit}
  input:focus{outline:none;border-color:var(--acc)}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:13px 24px;border-radius:12px;font-weight:700;
    font-size:.95rem;cursor:pointer;border:0;font-family:inherit;color:#fff;
    background:linear-gradient(135deg,var(--acc),var(--acc2));box-shadow:0 10px 30px -10px var(--acc)}
  .btn:hover{transform:translateY(-2px)}
  .btn.ghost{background:#0d1018;border:1px solid rgba(255,255,255,.1);color:#c9d1d9;box-shadow:none}
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
  .msg{padding:12px 15px;border-radius:12px;font-size:.9rem;margin-bottom:18px}
  .msg.err{background:rgba(251,113,133,.12);border:1px solid rgba(251,113,133,.4);color:#fb7185}
  .msg.ok{background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.4);color:#4ade80}
  .themes{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:6px}
  .theme-card{cursor:pointer;font-family:inherit;text-align:left;border:2px solid rgba(255,255,255,.09);
    border-radius:14px;padding:12px;background:#0d1018;color:#eef1f8;transition:.2s;display:flex;flex-direction:column;gap:4px}
  .theme-card:hover{border-color:rgba(255,255,255,.25);transform:translateY(-2px)}
  .theme-card.sel{border-color:var(--acc);box-shadow:0 0 0 1px var(--acc) inset}
  .tc-sw{height:26px;border-radius:8px;margin-bottom:4px}
  .theme-card b{font-size:.9rem}
  .theme-card small{color:#8b93a7;font-size:.74rem}
  .swatch{display:flex;align-items:center;gap:10px}
  .swatch input[type=color]{width:52px;height:44px;border:1px solid rgba(255,255,255,.1);border-radius:10px;background:#0d1018;cursor:pointer}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:6px}
  .sub2{display:block;font-size:.82rem;color:#8b93a7;margin-bottom:6px}
  select.sel{width:100%;padding:12px 14px;border-radius:12px;font-size:.95rem;background:#0d1018;border:1px solid rgba(255,255,255,.1);color:#eef1f8;font-family:inherit;cursor:pointer}
  select.sel:focus{outline:none;border-color:var(--acc)}
  input.pin{width:100%;padding:9px 10px;border-radius:12px;font-size:.82rem;background:#0d1018;border:1px solid rgba(255,255,255,.1);color:#eef1f8;font-family:inherit}
  input.pin:focus{outline:none;border-color:var(--acc)}
  input.pin::placeholder{color:#5b647a}
  .proj-section{margin-top:26px}
  .proj-scroll{overflow-x:auto;margin-top:10px;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:10px}
  .proj-table{display:flex;flex-direction:column;gap:8px;min-width:520px}
  .proj-row{display:grid;grid-template-columns:70px 1fr 118px 1.5fr 40px;gap:8px;align-items:center}
  .proj-head-row{font-size:.7rem;color:#8b93a7;text-transform:uppercase;letter-spacing:.04em;padding:0 2px}
  .proj-head-row span:last-child,.vis-cell{text-align:center;justify-self:center}
  .proj-row .proj-name{font-weight:600;font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .proj-row select.sel{padding:9px 8px;font-size:.8rem}
  .vis-cell{display:flex;justify-content:center;align-items:center}
  .vis-cell input{width:18px;height:18px;accent-color:var(--acc);cursor:pointer}
  @media(max-width:520px){.row2{grid-template-columns:1fr}}
  .carte-preview{margin-top:12px;border:1px solid rgba(255,255,255,.1);border-radius:14px;overflow:hidden;background:#0b1d33;position:relative;height:210px}
  .cp-label{position:absolute;top:8px;left:10px;z-index:2;font-size:.72rem;color:#eef1f8;background:rgba(0,0,0,.55);padding:4px 9px;border-radius:7px;pointer-events:none}
  #cartePreviewFrame{width:100%;height:100%;border:0;display:none;background:#0b1d33}
  .cp-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px;color:#8b93a7;font-size:.88rem}
  .toggle{display:flex;align-items:center;gap:10px;font-weight:500;cursor:pointer;color:#c9d1d9;margin-top:6px}
  .toggle input{width:18px;height:18px;accent-color:var(--acc)}
  .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px;align-items:center}
  .foot{margin-top:22px;font-size:.82rem;color:#6b7488;text-align:center}
  .foot a{color:var(--acc)}
  a.back{color:#8b93a7;text-decoration:none;font-size:.88rem}
</style>
</head>
<body>
<div class="card">

<?php if (!$authed): ?>
  <h1>🔒 Espace <span>admin</span></h1>
  <p class="sub">Connexion avec les identifiants MySQL (utilisateur + mot de passe).</p>
  <?php if ($err): ?><div class="msg err"><?= ea($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label for="user">Utilisateur MySQL</label>
    <input type="text" id="user" name="login_user" value="<?= ea($BK_USER) ?>" autocomplete="off">
    <label for="pw">Mot de passe MySQL</label>
    <input type="password" id="pw" name="login_pw" autofocus>
    <div class="actions">
      <button class="btn" type="submit">Connexion →</button>
      <a class="back" href="index.php">⮜ Retour au portfolio</a>
    </div>
  </form>

<?php else: ?>
  <h1>🎨 <span>Apparence</span> du portfolio</h1>
  <p class="sub">Les changements s'appliquent à tout le site, pour tous les visiteurs.</p>
  <?php if ($saved): ?><div class="msg ok">✔ Apparence enregistrée.</div><?php endif; ?>
  <?php if (($_GET['ok'] ?? '') === 'reset'): ?><div class="msg ok">✔ Réinitialisé aux valeurs par défaut.</div><?php endif; ?>
  <?php if ($saveErr): ?><div class="msg err"><?= ea($saveErr) ?></div><?php endif; ?>

  <form method="post" id="f">
    <input type="hidden" name="save_appearance" value="1">

    <label>Thème</label>
    <div class="themes">
      <?php foreach ($themes as $t):
        $isSel = (strtolower($accent) === strtolower($t[2])); ?>
      <button type="button" class="theme-card<?= $isSel ? ' sel' : '' ?>" data-a="<?= ea($t[2]) ?>" data-d="<?= ea($t[3]) ?>" data-dark="<?= $t[4] ? '1' : '0' ?>">
        <span class="tc-sw" style="background:linear-gradient(135deg,<?= ea($t[2]) ?>,<?= ea($t[3]) ?>)"></span>
        <b><?= ea($t[1]) ?></b>
        <small><?= $t[4] ? '🌙 sombre' : '☀ clair' ?></small>
      </button>
      <?php endforeach; ?>
    </div>
    <p class="sub" style="margin:14px 0 0;font-size:.82rem">…ou personnalise les couleurs ci-dessous :</p>

    <label>Couleur d'accent principale</label>
    <div class="swatch">
      <input type="color" id="accP" value="<?= ea($accent) ?>">
      <input type="text" name="accent" id="accT" value="<?= ea($accent) ?>" pattern="#[0-9a-fA-F]{6}">
    </div>

    <label>Couleur d'accent secondaire (dégradés)</label>
    <div class="swatch">
      <input type="color" id="dimP" value="<?= ea($dim) ?>">
      <input type="text" name="accent_dim" id="dimT" value="<?= ea($dim) ?>" pattern="#[0-9a-fA-F]{6}">
    </div>

    <label class="toggle"><input type="checkbox" name="dark" <?= $dark ? 'checked' : '' ?>> Thème sombre par défaut</label>
    <label class="toggle"><input type="checkbox" name="particules" <?= $particules ? 'checked' : '' ?>> Fond animé à particules</label>

    <?php if ($hasLuvumbu && $CARTE_VUES): ?>
    <label style="margin-top:26px">🎮 Carte des projets (affichage par défaut)</label>
    <div class="row2">
      <div>
        <span class="sub2">Mode</span>
        <select name="carte_mode" class="sel">
          <option value="default"<?= $carteMode === 'default' ? ' selected' : '' ?>>🗺️ Carte (intégrée)</option>
          <?php foreach ($CARTE_VUES as $v): ?>
          <option value="<?= ea($v['mode']) ?>"<?= $carteMode === $v['mode'] ? ' selected' : '' ?>><?= ea($v['icon'] . ' ' . $v['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <span class="sub2">Univers / style</span>
        <select name="carte_biome" class="sel">
          <option value="">— par défaut —</option>
          <?php foreach ($CARTE_APPS as $a): ?>
          <option value="<?= ea($a['biome']) ?>"<?= $carteBiome === $a['biome'] ? ' selected' : '' ?>><?= ea($a['icon'] . ' ' . $a['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="carte-preview">
      <div class="cp-label">👁 Aperçu</div>
      <iframe id="cartePreviewFrame" title="Aperçu de la carte" loading="lazy"></iframe>
      <div class="cp-empty" id="cpEmpty">🗺️ Vue intégrée — l'aperçu s'affiche directement sur le portfolio</div>
    </div>
    <a class="back" href="luvumbu/?admin=1" target="_blank" style="display:inline-block;margin-top:10px">⚙ Configuration avancée (mondes, projets, ordre, descriptions) ↗</a>
    <?php endif; ?>

    <div class="actions">
      <button class="btn" type="submit">💾 Enregistrer</button>
      <a class="btn ghost" href="admin.php?reset=1" onclick="return confirm('Revenir aux valeurs par défaut ?')">↺ Réinitialiser</a>
      <a class="back" href="index.php" target="_blank">Voir le portfolio ↗</a>
    </div>
  </form>

  <?php if ($pfProjects): ?>
  <div class="proj-section">
    <label>📂 Projets — point d'entrée (chemin exact)</label>
    <p class="sub2">Pour chaque dossier de la racine, la page ouverte <b>au clic</b> : laisse <b>vide</b> pour la racine, ou tape le <b>chemin exact</b> d'un dossier <u>ou d'un fichier</u> (ex. <code>public_html/app.php</code>, <code>api/index.php</code>). La liste te suggère les entrées détectées (<b>★</b> = un index s'y trouve). Le choix s'applique à la carte et aux fiches.</p>
    <?php if ($savedProj): ?><div class="msg ok">✔ Points d'entrée enregistrés.</div><?php endif; ?>
    <?php if ($projErr): ?><div class="msg err"><?= ea($projErr) ?></div><?php endif; ?>
    <form method="post" id="fp">
      <input type="hidden" name="save_projects" value="1">
      <div class="proj-scroll">
        <div class="proj-table" style="min-width:420px">
          <div class="proj-row proj-head-row" style="grid-template-columns:1fr 1.5fr">
            <span>Projet</span><span>Point d'entrée</span>
          </div>
          <?php foreach ($pfProjects as $i => $p):
            $cands = $pfCandidates[$p];
            $cur   = (string)$pfCurrent[$p];
            $dlId  = 'cd-' . $i;
          ?>
          <div class="proj-row" style="grid-template-columns:1fr 1.5fr">
            <span class="proj-name" title="<?= ea($p) ?>"><?= ea($p) ?></span>
            <input type="text" name="target[<?= ea($p) ?>]" value="<?= ea($cur) ?>" list="<?= $dlId ?>"
                   class="pin" autocomplete="off" spellcheck="false"
                   placeholder="(racine) — ou chemin exact fichier/dossier"
                   title="Vide = racine, ou chemin exact (ex. public_html/app.php)">
            <datalist id="<?= $dlId ?>">
              <?php foreach ($cands as $o):
                $lbl = $o['path'] === '' ? "(racine) /$p/" : "/$p/{$o['path']}" . (empty($o['file']) ? '/' : '');
                if (!empty($o['hasIndex'])) $lbl .= '  ★'; ?>
              <option value="<?= ea($o['path']) ?>"><?= ea($lbl) ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="actions">
        <button class="btn" type="submit">💾 Enregistrer les points d'entrée</button>
        <a class="back" href="index.php" target="_blank">Voir la carte ↗</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="foot"><a href="admin.php?logout=1">Déconnexion</a></div>
<?php endif; ?>

</div>

<script>
  // sync color pickers <-> text + presets
  var aP=document.getElementById('accP'),aT=document.getElementById('accT'),
      dP=document.getElementById('dimP'),dT=document.getElementById('dimT');
  function bind(picker,text){ if(!picker||!text)return;
    picker.addEventListener('input',function(){text.value=picker.value;live();});
    text.addEventListener('input',function(){ if(/^#[0-9a-fA-F]{6}$/.test(text.value)){picker.value=text.value;live();} }); }
  bind(aP,aT); bind(dP,dT);
  function live(){ document.documentElement.style.setProperty('--acc',aT.value); document.documentElement.style.setProperty('--acc2',dT.value); }
  /* Aperçu en direct de la carte (mode + univers) */
  var cmSel=document.querySelector('select[name="carte_mode"]'),
      cbSel=document.querySelector('select[name="carte_biome"]'),
      cpFrame=document.getElementById('cartePreviewFrame'),
      cpEmpty=document.getElementById('cpEmpty');
  function updateCartePreview(){
    if(!cmSel||!cpFrame) return;
    var mode=cmSel.value, biome=cbSel?cbSel.value:'';
    if(mode==='default'){ cpFrame.style.display='none'; if(cpEmpty)cpEmpty.style.display='flex'; cpFrame.removeAttribute('src'); return; }
    if(cpEmpty)cpEmpty.style.display='none';
    cpFrame.style.display='block';
    cpFrame.src='luvumbu/?embed=1&mode='+encodeURIComponent(mode)+(biome?'&biome='+encodeURIComponent(biome):'')+'&v=5';
  }
  if(cmSel)cmSel.addEventListener('change',updateCartePreview);
  if(cbSel)cbSel.addEventListener('change',updateCartePreview);
  updateCartePreview();

  var darkBox=document.querySelector('input[name="dark"]');
  document.querySelectorAll('.theme-card').forEach(function(el){ el.addEventListener('click',function(){
    if(aP){aP.value=el.dataset.a;} if(aT){aT.value=el.dataset.a;}
    if(dP){dP.value=el.dataset.d;} if(dT){dT.value=el.dataset.d;}
    if(darkBox){ darkBox.checked = (el.dataset.dark==='1'); }
    document.querySelectorAll('.theme-card').forEach(function(x){x.classList.remove('sel');});
    el.classList.add('sel');
    live(); }); });
</script>
<?php if ($saved || $savedProj): ?>
<script>
  /* Après enregistrement : le portfolio (fenêtre parente) se recharge pour refléter
     les changements tout de suite — pas besoin de recharger à la main. */
  try{
    if(window.parent && window.parent!==window && window.parent.location){
      try{ window.parent.sessionStorage.setItem('pf_admin_reopen','1'); }catch(e){}  // rouvrir la modale après reload
      window.parent.location.reload();
    }
  }catch(e){}
</script>
<?php endif; ?>
</body>
</html>
