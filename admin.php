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

/* ─── Connexion : déléguée au hub « Luvumbu ID » ───────────────────────────
   Cette page ne demande plus d'identifiants : l'écosystème n'a qu'une seule
   porte d'entrée (sso/index.php), qui vérifie l'identité, contrôle le rôle sur
   l'application « admin », puis renvoie ici. On ne conserve le formulaire
   MySQL qu'en secours, si le hub n'est pas configuré sur ce serveur. */
require_once __DIR__ . '/sso/client.php';
$SSO_ON = sso_ready();

/* Déconnexion : globale quand on est passé par le hub (un seul point de
   sortie comme il n'y a qu'un seul point d'entrée). */
if (isset($_GET['logout'])) {
    $viaSso = !empty($_SESSION['pf_admin_sso']);
    unset($_SESSION['pf_admin'], $_SESSION['pf_admin_user'], $_SESSION['pf_admin_sso']);
    if ($viaSso) luvumbu_logout(true, luvumbu_url('admin.php'));
    header('Location: admin.php'); exit;
}

$err = '';

/* Identité venant du hub (consomme aussi le ?sso=… du retour). */
$ssoUser = $SSO_ON ? luvumbu_user() : null;
if ($ssoUser) {
    if (luvumbu_is_admin($ssoUser, 'admin')) {
        $_SESSION['pf_admin']      = true;
        $_SESSION['pf_admin_user'] = $ssoUser;      // nom/e-mail pour l'affichage
        $_SESSION['pf_admin_sso']  = true;
    } else {
        $err = 'Ton compte Luvumbu ID n\'a pas le rôle administrateur sur cet espace.';
    }
}

/* Pas encore identifié et le hub est disponible → on y va (point d'entrée unique). */
if (empty($_SESSION['pf_admin']) && $SSO_ON && !$ssoUser) {
    luvumbu_require_login('admin');
}

/* Connexion de SECOURS (hub indisponible) : identifiants MySQL, comme avant. */
if (!$SSO_ON && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pw'])) {
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
        // Mondes de la carte : nb de zones par monde + noms optionnels.
        'world_size'  => max(2, min(12, (int)($_POST['world_size'] ?? 6))),
        'world_names' => array_values(array_filter(array_map('trim',
                            explode(',', (string)($_POST['world_names'] ?? ''))), fn($x) => $x !== '')),
        // Apparence de la carte (choisie par l'admin) : '' = neutre par défaut.
        'carte_apparence' => in_array((string)($_POST['carte_apparence'] ?? ''),
                                ['', 'auto', '0', '1', '2', '3', '4', '5'], true)
                                ? (string)($_POST['carte_apparence'] ?? '') : '',
    ];
    if (@file_put_contents($appFile, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
        $app = $new; $saved = true;
    } else { $saveErr = "Impossible d'écrire config/appearance.json (droits ?)."; }
}

/* Réinitialisation (retour aux valeurs par défaut du config) */
if ($authed && isset($_GET['reset']) && is_file($appFile)) { @unlink($appFile); $app = []; header('Location: admin.php?ok=reset'); exit; }

/* ═══════════════════════════════════════════════════════════
   CLÉ D'API du gestionnaire distant (_gestion/apikey.local.php).
   C'est ICI que TU génères / révoques la clé. Elle n'est affichée
   qu'UNE fois, au moment de la génération. Toi seul la contrôles.
   ═══════════════════════════════════════════════════════════ */
$KEY_FILE   = __DIR__ . '/_gestion/apikey.local.php';
$keySet     = is_file($KEY_FILE);
$newKey     = null;        // clé en clair affichée une seule fois après génération
$keyMsg     = '';
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_apikey'])) {
    $k = bin2hex(random_bytes(24));                     // 48 caractères hexa
    if (@file_put_contents($KEY_FILE, "<?php return '$k';\n") !== false) {
        $newKey = $k; $keySet = true;
        $keyMsg = 'ok';
    } else {
        $keyMsg = 'err';                                // dossier _gestion/ absent ou non inscriptible
    }
}
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_apikey'])) {
    if (is_file($KEY_FILE)) @unlink($KEY_FILE);
    $keySet = false; $keyMsg = 'revoked';
}
/* Afficher la clé active (lecture à la demande) — pour la retrouver quand on veut. */
$showKey = null;
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['show_apikey'])) {
    if (is_file($KEY_FILE)) { $k = require $KEY_FILE; $showKey = is_string($k) ? $k : ''; }
}

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
/* points d'entrée SUGGÉRÉS : la racine + TOUS les dossiers/sous-dossiers (récursif)
   et leurs fichiers index.* — parcours complet de l'arborescence du projet.
   (le champ reste LIBRE : on peut taper n'importe quel chemin exact fichier/dossier). */
$PF_SKIP_DIRS = ['node_modules', 'vendor', '.git', '.svn', '.hg', 'cache', '.cache',
                 'tmp', 'temp', '.idea', '.vscode', 'dist', 'build', '.next', 'bower_components'];
$PF_MAX_DEPTH = 8;      // profondeur maximale explorée
$PF_MAX_ITEMS = 600;    // nb max de suggestions par projet (garde-fou)
$pf_targets = function ($absDir) use ($pf_has_index, $PF_SKIP_DIRS, $PF_MAX_DEPTH, $PF_MAX_ITEMS) {
    $idx = ['index.php', 'index.html', 'index.htm'];
    $out = [['path' => '', 'file' => false, 'hasIndex' => $pf_has_index($absDir)]];
    foreach ($idx as $f) if (is_file($absDir . '/' . $f)) $out[] = ['path' => $f, 'file' => true, 'hasIndex' => true];
    $walk = function ($dir, $rel, $depth) use (&$walk, $idx, $pf_has_index, $PF_SKIP_DIRS, $PF_MAX_DEPTH, $PF_MAX_ITEMS, &$out) {
        if ($depth > $PF_MAX_DEPTH || count($out) >= $PF_MAX_ITEMS) return;
        $subs = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        natcasesort($subs);
        foreach ($subs as $sub) {
            if (count($out) >= $PF_MAX_ITEMS) return;
            $b = basename($sub);
            if ($b === '' || $b[0] === '.' || in_array(strtolower($b), $PF_SKIP_DIRS, true)) continue;
            $r = ($rel === '' ? '' : $rel . '/') . $b;
            $out[] = ['path' => $r, 'file' => false, 'hasIndex' => $pf_has_index($sub)];
            foreach ($idx as $f) if (is_file($sub . '/' . $f)) $out[] = ['path' => $r . '/' . $f, 'file' => true, 'hasIndex' => true];
            $walk($sub, $r, $depth + 1);
        }
    };
    $walk($absDir, '', 1);
    return $out;
};
/* auto-détection : index à la racine, sinon le dossier avec index le MOINS profond */
$pf_auto = function ($cands) {
    if (!empty($cands[0]['hasIndex'])) return '';                 // index à la racine
    $best = ''; $bestDepth = PHP_INT_MAX;
    foreach ($cands as $c) {
        if ($c['path'] === '' || empty($c['hasIndex']) || !empty($c['file'])) continue;
        $d = substr_count($c['path'], '/');
        if ($d < $bestDepth) { $bestDepth = $d; $best = $c['path']; }
    }
    return $best;
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

/* choix déjà enregistrés (points d'entrée) */
$pfChoice = is_file($PROJ_FILE) ? (json_decode(file_get_contents($PROJ_FILE), true) ?: []) : [];

/* habillage par projet (icône / nom / image / description) — surcharges admin */
$PMETA_FILE = __DIR__ . '/config/projets_meta.json';
$pfMeta     = is_file($PMETA_FILE) ? (json_decode(file_get_contents($PMETA_FILE), true) ?: []) : [];
$cfgMeta    = $CFG['carte']['meta'] ?? [];          // valeurs par défaut (config/portfolio.php)
$IMG_DIR    = __DIR__ . '/images/projets';           // destination des images uploadées
$IMG_EXTS   = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
$IMG_MAX    = 5 * 1024 * 1024;                        // 5 Mo max par image

/* Palette d'émojis proposés pour l'icône d'un projet (cliquables dans l'admin) */
$EMOJI_SUGGESTIONS = [
    '🕹️','🎮','📱','💻','🖥️','🌐','🔌','🛰️','📡','🔗',
    '📸','🎨','🖼️','🎬','🎵','🎧','📷','🎥','🖌️','✨',
    '📊','📈','📉','🗂️','📁','📂','📄','📝','🗒️','📋',
    '🛠️','⚙️','🧰','🔧','🔨','🧪','🔬','🧲','🧮','🤖',
    '🚀','🛸','⚡','🔥','💡','🌟','⭐','🎯','🏆','🥇',
    '🔒','🔑','🛡️','👁️','🧠','💳','💰','🏢','🏟️','🏰',
    '📦','🧩','🎓','📚','🌱','🐧','🐳','🍄','🎲','♟️',
];

/* enregistrement du point d'entrée + habillage (icône/nom/image/desc) par projet */
$savedProj = false; $projErr = '';
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_projects'])) {
    $tsel     = (array)($_POST['target'] ?? []);
    $iconsIn  = (array)($_POST['icon'] ?? []);
    $nomsIn   = (array)($_POST['nom'] ?? []);
    $descsIn  = (array)($_POST['desc'] ?? []);
    $ordreIn  = (array)($_POST['ordre'] ?? []);
    $detailsIn = (array)($_POST['details'] ?? []);
    $urlAppIn  = (array)($_POST['url_app'] ?? []);
    $lienLabIn = (array)($_POST['lien_label'] ?? []);
    $lienUrlIn = (array)($_POST['lien_url'] ?? []);
    $imgClear = (array)($_POST['imgclear'] ?? []);
    $newEntry = [];
    $newMeta  = [];
    foreach ($pfProjects as $p) {
        /* — 1) point d'entrée (identique à avant) — */
        $path = isset($tsel[$p]) ? trim(str_replace('\\', '/', (string)$tsel[$p]), '/') : '';
        if ($path !== '') {
            // sécurité : la cible (fichier OU dossier) doit exister SOUS le projet, sans en sortir
            $base = realpath($pfRoot . '/' . $p);
            $full = realpath($pfRoot . '/' . $p . '/' . $path);
            $inside = $base && $full && strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0;
            if (!$inside || !(is_file($full) || is_dir($full))) $path = '';   // invalide → racine
        }
        $newEntry[$p] = $path;                              // '' = on entre à la racine

        /* — 2) habillage — */
        $row  = [];
        $ic   = trim((string)($iconsIn[$p] ?? ''));
        $nm   = trim((string)($nomsIn[$p] ?? ''));
        $ds   = trim((string)($descsIn[$p] ?? ''));
        if ($ic !== '') $row['icon'] = mb_substr($ic, 0, 16);
        if ($nm !== '') $row['nom']  = mb_substr($nm, 0, 80);
        if ($ds !== '') $row['desc'] = mb_substr($ds, 0, 800);
        $or = (int)($ordreIn[$p] ?? 0);                    // position choisie (0 = automatique)
        if ($or > 0) $row['ordre'] = $or;
        /* — page détaillée : texte long, lien appli, liens plus d'infos — */
        $dt = trim((string)($detailsIn[$p] ?? ''));
        if ($dt !== '') $row['details'] = mb_substr($dt, 0, 8000);
        $ua = trim((string)($urlAppIn[$p] ?? ''));
        if ($ua !== '' && preg_match('~^https?://~i', $ua)) $row['url_app'] = mb_substr($ua, 0, 300);
        $labs = (array)($lienLabIn[$p] ?? []); $urls = (array)($lienUrlIn[$p] ?? []);
        $liens = [];
        foreach ($urls as $k => $u) {
            $u = trim((string)$u);
            if ($u === '' || !preg_match('~^https?://~i', $u)) continue;
            $l = trim((string)($labs[$k] ?? ''));
            $liens[] = ['label' => ($l !== '' ? mb_substr($l, 0, 80) : $u), 'url' => mb_substr($u, 0, 300)];
        }
        if ($liens) $row['liens'] = $liens;

        /* image : on repart de l'existante, sauf si on la retire ou qu'on en envoie une neuve */
        $imgPath = (string)($pfMeta[$p]['img'] ?? '');
        if (!empty($imgClear[$p])) $imgPath = '';
        if (isset($_FILES['img']) && ($_FILES['img']['error'][$p] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['img']['tmp_name'][$p];
            $ext  = strtolower(pathinfo((string)$_FILES['img']['name'][$p], PATHINFO_EXTENSION));
            $size = (int)($_FILES['img']['size'][$p] ?? 0);
            if (in_array($ext, $IMG_EXTS, true) && $size > 0 && $size <= $IMG_MAX && is_uploaded_file($tmp)) {
                if (!is_dir($IMG_DIR)) @mkdir($IMG_DIR, 0775, true);
                $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p);
                $dest = $IMG_DIR . '/' . $safe . '.' . $ext;
                // supprime les anciennes versions (autre extension) du même projet
                foreach ($IMG_EXTS as $e) { $old = $IMG_DIR . '/' . $safe . '.' . $e; if ($old !== $dest && is_file($old)) @unlink($old); }
                if (@move_uploaded_file($tmp, $dest)) $imgPath = 'images/projets/' . $safe . '.' . $ext . '?v=' . time();
            } else {
                $projErr = "Image ignorée pour « $p » : formats acceptés " . implode(', ', $IMG_EXTS) . " · 5 Mo max.";
            }
        }
        if ($imgPath !== '') $row['img'] = $imgPath;

        if ($row) $newMeta[$p] = $row;
    }

    $okE = @file_put_contents($PROJ_FILE,  json_encode($newEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
    $okM = @file_put_contents($PMETA_FILE, json_encode($newMeta,  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
    if ($okE && $okM) {
        $pfChoice = $newEntry; $pfMeta = $newMeta; $savedProj = true;
    } elseif (!$projErr) {
        $projErr = "Impossible d'écrire config/projets.json ou projets_meta.json (droits ?).";
    }
}

/* candidats + valeur courante de chaque projet (pour le menu) */
$pfCandidates = $pfCurrent = [];
foreach ($pfProjects as $p) {
    $pfCandidates[$p] = $pf_targets($pfRoot . '/' . $p);
    $pfCurrent[$p]    = array_key_exists($p, $pfChoice) ? (string)$pfChoice[$p] : $pf_auto($pfCandidates[$p]);
}

/* habillage courant de chaque projet (surcharge admin → défaut config → repli) */
$pfIcon = $pfNom = $pfDesc = $pfImg = [];
foreach ($pfProjects as $p) {
    $ov = $pfMeta[$p]  ?? [];
    $cf = $cfgMeta[$p] ?? [];
    $pfIcon[$p] = (string)($ov['icon'] ?? $cf['icon'] ?? '');
    $pfNom[$p]  = (string)($ov['nom']  ?? $cf['nom']  ?? '');
    $pfDesc[$p] = (string)($ov['desc'] ?? $cf['desc'] ?? '');
    $pfImg[$p]  = (string)($ov['img']  ?? $cf['img']  ?? '');
}

/* Valeurs courantes */
$accent     = $app['accent']     ?? $CFG['theme']['accent'];
$dim        = $app['accent_dim'] ?? $CFG['theme']['accent_dim'];
$dark       = $app['dark']       ?? $CFG['theme']['defaut_sombre'];
$particules = $app['particules'] ?? $CFG['theme']['particules'];
$carteMode  = $app['carte_mode']  ?? 'default';
$carteBiome = $app['carte_biome'] ?? '';
$worldSize  = (int)($app['world_size'] ?? 6);
$worldNames = is_array($app['world_names'] ?? null) ? $app['world_names'] : [];
$carteApp   = (string)($app['carte_apparence'] ?? '');   // '' = neutre par défaut
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
  /* Éditeur d'habillage par projet — cartes repliables compactes */
  .pm-list{display:flex;flex-direction:column;gap:8px;margin-top:12px}
  .pm-card{border:1px solid rgba(255,255,255,.09);border-radius:12px;background:#0d1018;overflow:hidden}
  .pm-card[open]{border-color:color-mix(in srgb,var(--acc) 45%,transparent);background:#0f1420}
  .pm-sum{display:flex;align-items:center;gap:12px;padding:10px 12px;cursor:pointer;list-style:none;user-select:none}
  .pm-sum::-webkit-details-marker{display:none}
  .pm-sum:hover{background:rgba(255,255,255,.03)}
  .pm-thumb{flex:0 0 auto;width:40px;height:40px;border-radius:10px;overflow:hidden;display:flex;
    align-items:center;justify-content:center;background:#0b0e15;border:1px solid rgba(255,255,255,.1);font-size:1.3rem}
  .pm-thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .pm-sumtxt{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:2px}
  .pm-sumname{font-weight:600;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .pm-folder{font-size:.72rem;color:#8b93a7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .pm-chev{flex:0 0 auto;color:#8b93a7;font-size:.9rem;transition:transform .2s}
  .pm-card[open] .pm-chev{transform:rotate(180deg)}
  .pm-body{padding:2px 12px 14px;display:flex;flex-direction:column;gap:12px;border-top:1px solid rgba(255,255,255,.06)}
  .pm-field{display:block}
  .pm-lab{display:block;font-size:.78rem;font-weight:600;color:#c9d1d9;margin:0 0 5px}
  .pm-hint{font-weight:400;color:#8b93a7}
  .pm-grid{display:grid;grid-template-columns:1fr 120px;gap:12px;margin-top:10px}
  .pm-field-icon input{text-align:center;font-size:1.05rem}
  .pm-emojis{display:flex;flex-wrap:wrap;gap:6px;max-height:120px;overflow-y:auto;padding:8px;
    border:1px solid rgba(255,255,255,.09);border-radius:12px;background:#0b0e15}
  .pm-emo{width:36px;height:36px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;
    font-size:1.15rem;line-height:1;border-radius:9px;cursor:pointer;
    border:1px solid rgba(255,255,255,.08);background:#121826;transition:.12s}
  .pm-emo:hover{border-color:var(--acc);transform:translateY(-1px);background:#1a2233}
  .pm-emo.sel{border-color:var(--acc);box-shadow:0 0 0 1px var(--acc) inset;background:#1a2233}
  .pm-file{width:100%;font-size:.78rem;color:#c9d1d9}
  .pm-file::file-selector-button{font-family:inherit;font-size:.78rem;padding:7px 12px;margin-right:10px;border-radius:9px;
    border:1px solid rgba(255,255,255,.14);background:#151a26;color:#eef1f8;cursor:pointer}
  .pm-file::file-selector-button:hover{border-color:var(--acc)}
  .pm-clear{display:flex;align-items:center;gap:8px;font-size:.78rem;color:#c9d1d9;margin-top:8px;cursor:pointer}
  .pm-clear input{width:16px;height:16px;accent-color:var(--acc);cursor:pointer}
  .pm-desc{width:100%;padding:10px 12px;border-radius:12px;font-size:.85rem;line-height:1.4;
    background:#0b0e15;border:1px solid rgba(255,255,255,.1);color:#eef1f8;font-family:inherit;resize:vertical}
  .pm-desc:focus{outline:none;border-color:var(--acc)}
  .pm-savebar{position:sticky;bottom:0;display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:14px;
    padding:12px 0 4px;background:linear-gradient(to top,#121620 70%,transparent)}
  @media(max-width:520px){.row2{grid-template-columns:1fr}.pm-grid{grid-template-columns:1fr}}
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

<?php if (!$authed && $SSO_ON): ?>
  <?php /* Le hub gère la connexion : on n'arrive ici que si l'identité est
           valide mais sans le rôle administrateur sur cet espace. */ ?>
  <h1>🔒 Espace <span>admin</span></h1>
  <p class="sub">Connexion assurée par <b>Luvumbu ID</b>, le point d'entrée unique du site.</p>
  <?php if ($err): ?><div class="msg err"><?= ea($err) ?></div><?php endif; ?>
  <div class="actions">
    <a class="btn" href="<?= ea(luvumbu_hub()) ?>?app=admin&amp;return=<?= ea(urlencode(luvumbu_url('admin.php'))) ?>">Changer de compte →</a>
    <a class="back" href="index.php">⮜ Retour au portfolio</a>
  </div>

<?php elseif (!$authed): ?>
  <?php /* SECOURS : hub non configuré sur ce serveur (secret SSO manquant). */ ?>
  <h1>🔒 Espace <span>admin</span></h1>
  <p class="sub">Luvumbu ID indisponible — connexion de secours avec les identifiants MySQL.</p>
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

  <a href="_gestion/index.php" target="_blank" rel="noopener"
     style="display:flex;align-items:center;gap:12px;margin:16px 0;padding:14px 16px;
            border:1px solid rgba(255,255,255,.15);border-radius:12px;text-decoration:none;
            color:inherit;background:linear-gradient(135deg,rgba(91,140,255,.18),rgba(58,107,255,.10))">
    <span style="font-size:1.6rem">🗂️</span>
    <span style="flex:1">
      <b style="display:block;font-size:.98rem">Gérer tous les fichiers du site</b>
      <small style="opacity:.8">Parcourir, envoyer, éditer, renommer, supprimer — tous les dossiers de luvumbu.com</small>
    </span>
    <span style="font-size:1.2rem;opacity:.7">↗</span>
  </a>

  <div style="margin:16px 0;padding:14px 16px;border:1px solid rgba(255,255,255,.15);border-radius:12px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
      <span style="font-size:1.3rem">🔑</span>
      <b>Accès API distant (gestion en ligne)</b>
    </div>
    <p class="sub" style="margin:0 0 12px;font-size:.82rem">
      Clé secrète qui autorise la gestion des fichiers à distance. <b>Toi seul la contrôles.</b>
      Génère-la, copie-la, et ne la donne qu'à qui doit s'en servir. Régénère pour couper l'accès.
    </p>

    <?php if ($keyMsg === 'err'): ?>
      <div class="msg err">Impossible d'écrire la clé (le dossier <code>_gestion/</code> est-il en ligne et inscriptible ?).</div>
    <?php elseif ($keyMsg === 'revoked'): ?>
      <div class="msg ok">✔ Clé révoquée — tout accès par API est coupé.</div>
    <?php endif; ?>

    <?php if ($newKey): ?>
      <div class="msg ok" style="margin-bottom:10px">
        ✔ Nouvelle clé générée. <b>Copie-la maintenant</b> — elle ne sera plus jamais réaffichée :
      </div>
      <input type="text" readonly value="<?= ea($newKey) ?>" onclick="this.select()"
             style="width:100%;font-family:monospace;font-size:.9rem;padding:10px;border-radius:8px;
                    border:1px solid rgba(255,255,255,.25);background:rgba(0,0,0,.25);color:#fff">
    <?php endif; ?>

    <?php if ($showKey !== null): ?>
      <div class="msg ok" style="margin-bottom:8px">🔑 Clé active — copie-la pour la donner quand tu veux :</div>
      <input type="text" readonly value="<?= ea($showKey) ?>" onclick="this.select()"
             style="width:100%;font-family:monospace;font-size:.9rem;padding:10px;border-radius:8px;
                    border:1px solid rgba(255,255,255,.25);background:rgba(0,0,0,.25);color:#fff;margin-bottom:6px">
      <?php if ($showKey === ''): ?><div class="sub" style="font-size:.8rem">(aucune clé enregistrée)</div><?php endif; ?>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px">
      <span class="sub" style="font-size:.82rem">
        État : <b><?= $keySet ? '🟢 Clé active' : '🔴 Aucune clé (accès API désactivé)' ?></b>
      </span>
      <span style="flex:1"></span>
      <?php if ($keySet): ?>
      <form method="post" style="margin:0">
        <button class="btn" type="submit" name="show_apikey" value="1">👁 Voir la clé active</button>
      </form>
      <?php endif; ?>
      <form method="post" style="margin:0" onsubmit="return confirm('<?= $keySet
        ? 'Générer une NOUVELLE clé ? L\'ancienne cessera aussitôt de fonctionner.'
        : 'Générer une clé d\'accès API ?' ?>')">
        <button class="btn" type="submit" name="gen_apikey" value="1">
          <?= $keySet ? '↻ Régénérer la clé' : '＋ Générer une clé' ?>
        </button>
      </form>
      <?php if ($keySet): ?>
      <form method="post" style="margin:0" onsubmit="return confirm('Révoquer la clé ? Tout accès API sera coupé.')">
        <button class="btn" type="submit" name="revoke_apikey" value="1"
                style="border-color:#ff5d6c;color:#ff5d6c;background:transparent">🗑 Révoquer</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
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

    <label style="margin-top:26px">🎨 Apparence de la carte</label>
    <?php $appOpts = ['' => '⬜ Défaut (neutre)', 'auto' => '🎲 Auto (un biome par monde)',
      '0' => '🌳 Plaine', '1' => '🏜️ Désert', '2' => '🌊 Océan', '3' => '🏰 Château',
      '4' => '🌙 Nuit', '5' => '☁️ Ciel']; ?>
    <select name="carte_apparence" class="sel">
      <?php foreach ($appOpts as $val => $lbl): ?>
      <option value="<?= ea($val) ?>"<?= (string)$carteApp === (string)$val ? ' selected' : '' ?>><?= ea($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="sub" style="margin:6px 0 0;font-size:.78rem">
      « Défaut » = look d'origine neutre. Les autres appliquent une ambiance (couleurs douces) à toute la carte, pour tous les visiteurs.
    </p>

    <label style="margin-top:26px">🗺️ Mondes de la carte (façon Mario)</label>
    <div class="row2">
      <div>
        <span class="sub2">Zones (projets) par monde</span>
        <input type="number" name="world_size" class="sel" min="2" max="12" step="1" value="<?= (int)$worldSize ?>">
      </div>
      <div>
        <span class="sub2">Noms des mondes <small>(optionnel, séparés par des virgules)</small></span>
        <input type="text" name="world_names" class="sel" value="<?= ea(implode(', ', $worldNames)) ?>" placeholder="Web, Jeux, Outils…">
      </div>
    </div>
    <p class="sub" style="margin:6px 0 0;font-size:.78rem">
      Les projets sont répartis en mondes de ce nombre de zones. Si tu nommes les mondes,
      le titre affiche « ★ WEB » au lieu de « ★ WORLD 1 » ; sinon la numérotation est automatique.
    </p>

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
    <label>📂 Projets — icône, image &amp; page d'entrée</label>
    <p class="sub2">Clique un projet pour le <b>déplier</b> et changer son icône (émoji), son image, son nom, sa description ou la page ouverte au clic. Tout est optionnel. Puis <b>💾 Enregistrer</b> en bas.</p>
    <?php if ($savedProj): ?><div class="msg ok">✔ Projets enregistrés.</div><?php endif; ?>
    <?php if ($projErr): ?><div class="msg err"><?= ea($projErr) ?></div><?php endif; ?>
    <form method="post" id="fp" enctype="multipart/form-data">
      <input type="hidden" name="save_projects" value="1">
      <div class="pm-list">
        <?php foreach ($pfProjects as $i => $p):
          $cands = $pfCandidates[$p];
          $cur   = (string)$pfCurrent[$p];
          $dlId  = 'cd-' . $i;
          $img   = $pfImg[$p];
          $dispNom = $pfNom[$p] !== '' ? $pfNom[$p] : $p;
        ?>
        <details class="pm-card">
          <summary class="pm-sum">
            <span class="pm-thumb" id="thumb-<?= $i ?>">
              <?php if ($img !== ''): ?><img src="<?= ea($img) ?>" alt=""><?php else: ?><span class="pm-emoji"><?= ea($pfIcon[$p] !== '' ? $pfIcon[$p] : '🕹️') ?></span><?php endif; ?>
            </span>
            <span class="pm-sumtxt">
              <span class="pm-sumname" id="sumname-<?= $i ?>"><?= ea($dispNom) ?></span>
              <span class="pm-folder">📁 <?= ea($p) ?></span>
            </span>
            <span class="pm-chev" aria-hidden="true">▾</span>
          </summary>

          <div class="pm-body">
            <div class="pm-grid">
              <label class="pm-field">
                <span class="pm-lab">Nom affiché</span>
                <input type="text" name="nom[<?= ea($p) ?>]" value="<?= ea($pfNom[$p]) ?>" class="pin" data-sum="sumname-<?= $i ?>" data-folder="<?= ea($p) ?>"
                       placeholder="<?= ea($p) ?>" maxlength="80" autocomplete="off">
              </label>
              <label class="pm-field pm-field-icon">
                <span class="pm-lab">Icône (émoji)</span>
                <input type="text" name="icon[<?= ea($p) ?>]" id="icon-<?= $i ?>" value="<?= ea($pfIcon[$p]) ?>" class="pin"
                       placeholder="🕹️" maxlength="16" autocomplete="off" data-thumb="thumb-<?= $i ?>">
              </label>
            </div>

            <div class="pm-field">
              <span class="pm-lab">🔢 Position (n° sur la carte) <span class="pm-hint">— « Auto » = ordre alphabétique</span></span>
              <select name="ordre[<?= ea($p) ?>]" class="pin">
                <option value="0"<?= (int)($pfMeta[$p]['ordre'] ?? 0) === 0 ? ' selected' : '' ?>>Auto</option>
                <?php for ($k = 1; $k <= count($pfProjects); $k++): ?>
                <option value="<?= $k ?>"<?= (int)($pfMeta[$p]['ordre'] ?? 0) === $k ? ' selected' : '' ?>><?= $k ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="pm-field">
              <span class="pm-lab">Ou choisis une icône <span class="pm-hint">— clique pour l'appliquer</span></span>
              <div class="pm-emojis">
                <?php foreach ($EMOJI_SUGGESTIONS as $emo): ?>
                <button type="button" class="pm-emo" data-emo="<?= ea($emo) ?>" data-target="icon-<?= $i ?>" data-thumb="thumb-<?= $i ?>" title="<?= ea($emo) ?>"><?= ea($emo) ?></button>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="pm-field">
              <span class="pm-lab">🖼️ Image <span class="pm-hint">— remplace l'émoji · png, jpg, webp, gif, svg · 5 Mo max</span></span>
              <input type="file" name="img[<?= ea($p) ?>]" accept="image/*" class="pm-file" data-thumb="thumb-<?= $i ?>">
              <?php if ($img !== ''): ?>
              <label class="pm-clear"><input type="checkbox" name="imgclear[<?= ea($p) ?>]" value="1"> Retirer l'image actuelle</label>
              <?php endif; ?>
            </div>

            <div class="pm-field">
              <span class="pm-lab">🎯 Page d'entrée <span class="pm-hint">— au clic · vide = racine du projet</span></span>
              <input type="text" name="target[<?= ea($p) ?>]" value="<?= ea($cur) ?>" list="<?= $dlId ?>"
                     class="pin" autocomplete="off" spellcheck="false"
                     placeholder="(racine) — ou chemin exact fichier/dossier">
              <datalist id="<?= $dlId ?>">
                <?php foreach ($cands as $o):
                  $lbl = $o['path'] === '' ? "(racine) /$p/" : "/$p/{$o['path']}" . (empty($o['file']) ? '/' : '');
                  if (!empty($o['hasIndex'])) $lbl .= '  ★'; ?>
                <option value="<?= ea($o['path']) ?>"><?= ea($lbl) ?></option>
                <?php endforeach; ?>
              </datalist>
            </div>

            <div class="pm-field">
              <span class="pm-lab">📝 Description <span class="pm-hint">— texte de la fiche</span></span>
              <textarea name="desc[<?= ea($p) ?>]" class="pm-desc" rows="3" maxlength="800"
                        placeholder="Laisse vide pour la description par défaut"><?= ea($pfDesc[$p]) ?></textarea>
            </div>

            <div class="pm-field">
              <span class="pm-lab">📖 Détails — page complète <span class="pm-hint">— fonctionnement, fonctionnalités… · Markdown léger : <code>## Titre</code>, <code>- liste</code>, <code>**gras**</code></span></span>
              <textarea name="details[<?= ea($p) ?>]" class="pm-desc" rows="7" maxlength="8000"
                        placeholder="Explique le projet en grand détail — s'affiche sur projet.php?p=<?= ea($p) ?>"><?= ea((string)($pfMeta[$p]['details'] ?? '')) ?></textarea>
            </div>

            <div class="pm-field">
              <span class="pm-lab">🚀 Lien direct de l'application <span class="pm-hint">— vide = page d'entrée du projet</span></span>
              <input type="url" name="url_app[<?= ea($p) ?>]" value="<?= ea((string)($pfMeta[$p]['url_app'] ?? '')) ?>"
                     class="pin" autocomplete="off" placeholder="https://…  (optionnel)">
            </div>

            <div class="pm-field">
              <span class="pm-lab">🔗 Liens « plus d'infos » <span class="pm-hint">— libellé + URL (doc, vidéo, article…)</span></span>
              <div class="lien-list">
                <?php $ll = is_array($pfMeta[$p]['liens'] ?? null) ? $pfMeta[$p]['liens'] : [];
                      $rows = array_merge($ll, [['label'=>'','url'=>''], ['label'=>'','url'=>'']]);
                      foreach ($rows as $ln): ?>
                <div class="lien-row" style="display:flex;gap:8px;margin-bottom:6px">
                  <input type="text" name="lien_label[<?= ea($p) ?>][]" value="<?= ea($ln['label'] ?? '') ?>" class="pin" style="flex:1" placeholder="Libellé (ex. Documentation)">
                  <input type="url"  name="lien_url[<?= ea($p) ?>][]"   value="<?= ea($ln['url'] ?? '') ?>"   class="pin" style="flex:1.4" placeholder="https://…">
                </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="pm-emo" onclick="addLien(this)">＋ Ajouter un lien</button>
            </div>

            <div class="pm-field">
              <a class="back" href="projet.php?p=<?= ea($p) ?>" target="_blank">👁 Voir la page détaillée ↗</a>
            </div>
          </div>
        </details>
        <?php endforeach; ?>
      </div>
      <div class="pm-savebar">
        <button class="btn" type="submit">💾 Enregistrer les projets</button>
        <a class="back" href="index.php" target="_blank">Voir la carte ↗</a>
      </div>
    </form>
    <script>
      /* + Ajouter un lien : clone la dernière ligne (label + url) et la vide */
      function addLien(btn){
        var list = btn.previousElementSibling;               // .lien-list
        var rows = list.querySelectorAll('.lien-row');
        var clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('input').forEach(function(i){ i.value = ''; });
        list.appendChild(clone);
        clone.querySelector('input').focus();
      }
    </script>
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

  /* Aperçu instantané de l'image choisie (avant envoi) */
  document.querySelectorAll('.pm-file').forEach(function(inp){
    inp.addEventListener('change',function(){
      var f=inp.files&&inp.files[0], box=document.getElementById(inp.dataset.thumb);
      if(!f||!box)return;
      var u=URL.createObjectURL(f);
      box.innerHTML='<img src="'+u+'" alt="">';
      var clr=inp.parentNode.querySelector('input[name^="imgclear"]'); if(clr)clr.checked=false;
    });
  });

  /* Le nom saisi se reflète en direct dans le titre replié du projet */
  document.querySelectorAll('input[data-sum]').forEach(function(inp){
    inp.addEventListener('input',function(){
      var t=document.getElementById(inp.dataset.sum); if(!t)return;
      t.textContent = inp.value.trim() || inp.dataset.folder;
    });
  });

  /* Palette d'émojis : clic → applique l'icône au champ + à la miniature */
  function setThumbEmoji(thumbId, emo){
    var box=document.getElementById(thumbId); if(!box)return;
    if(box.querySelector('img')) return;          // une image est déjà choisie → on n'écrase pas
    box.innerHTML='<span class="pm-emoji">'+(emo||'🕹️')+'</span>';
  }
  function markSel(input){
    var wrap=input.closest('.pm-body'); if(!wrap)return;
    var v=input.value.trim();
    wrap.querySelectorAll('.pm-emo').forEach(function(b){ b.classList.toggle('sel', b.dataset.emo===v); });
  }
  document.querySelectorAll('.pm-emo').forEach(function(btn){
    btn.addEventListener('click',function(){
      var inp=document.getElementById(btn.dataset.target); if(!inp)return;
      inp.value=btn.dataset.emo;
      setThumbEmoji(btn.dataset.thumb, btn.dataset.emo);
      markSel(inp);
    });
  });
  /* Saisie manuelle dans le champ icône → maj miniature + surbrillance palette */
  document.querySelectorAll('.pm-field-icon input[data-thumb]').forEach(function(inp){
    inp.addEventListener('input',function(){ setThumbEmoji(inp.dataset.thumb, inp.value.trim()); markSel(inp); });
    markSel(inp);   // surbrillance initiale
  });

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
  /* Après enregistrement : on GARDE la confirmation « ✔ » visible (avant on
     rechargeait le parent aussitôt, du coup le message disparaissait et on
     croyait que rien n'avait été sauvé). Le portfolio derrière la modale est
     marqué « à rafraîchir » et se recharge seulement à la FERMETURE. */
  try{
    var msg=document.querySelector('.msg.ok'); if(msg){ msg.scrollIntoView({behavior:'smooth',block:'center'}); }
    if(window.parent && window.parent!==window){
      try{ window.parent.sessionStorage.setItem('pf_admin_dirty','1'); }catch(e){}
    }
  }catch(e){}
</script>
<?php endif; ?>
</body>
</html>
