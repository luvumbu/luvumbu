<?php
/* ═══════════════════════════════════════════════════════════════════════
   PAGE DÉTAILLÉE D'UN PROJET.
   URL : projet.php?p=<dossier>  (ex. projet.php?p=cv_luvumbu)
   Contenu piloté depuis l'admin (config/projets_meta.json) :
     nom, icon/img, desc (résumé), details (texte long), url_app, liens[].
   ═══════════════════════════════════════════════════════════════════════ */
$root = __DIR__;
$CFG  = require $root . '/config/portfolio.php';

/* Dossiers-projets réels (même exclusion que la carte) */
$exclude = array_values(array_unique(array_merge(
    $CFG['carte']['exclude'] ?? [],
    ['luvumbu','config','css','js','inc','images','vendor','node_modules','_gestion']
)));
$folders = [];
foreach (scandir($root) ?: [] as $e) {
    if ($e === '' || $e[0] === '.' || in_array($e, $exclude, true)) continue;
    if (is_dir($root . '/' . $e)) $folders[] = $e;
}

$p = (string)($_GET['p'] ?? '');
$valid = in_array($p, $folders, true);

/* Métadonnées : défaut (portfolio.php) surchargé par projets_meta.json */
$cfgMeta = $CFG['carte']['meta'] ?? [];
$ovMeta  = [];
$mf = $root . '/config/projets_meta.json';
if (is_file($mf)) { $t = json_decode(file_get_contents($mf), true); if (is_array($t)) $ovMeta = $t; }
$m = $valid ? array_merge($cfgMeta[$p] ?? [], array_filter($ovMeta[$p] ?? [], fn($v) => $v !== '' && $v !== null && $v !== [])) : [];

/* Point d'entrée de l'appli (projets.json → sinon auto → sinon racine) */
$entryUrl = '';
if ($valid) {
    $pj = [];
    $pjf = $root . '/config/projets.json';
    if (is_file($pjf)) { $t = json_decode(file_get_contents($pjf), true); if (is_array($t)) $pj = $t; }
    $target = array_key_exists($p, $pj) ? trim(str_replace('\\','/',(string)$pj[$p]),'/') : '';
    if ($target === '') {
        foreach (['index.php','index.html','index.htm'] as $f) { if (is_file("$root/$p/$f")) { $target=''; break; } }
        if ($target === '') {
            $subs = glob("$root/$p/*", GLOB_ONLYDIR) ?: [];
            foreach ($subs as $s) foreach (['index.php','index.html','index.htm'] as $f)
                if (is_file("$s/$f")) { $target = basename($s); break 2; }
        }
    }
    $enc = $target === '' ? '' : implode('/', array_map('rawurlencode', explode('/', $target))) . '/';
    $entryUrl = rawurlencode($p) . '/' . $enc;
}
$urlApp = (string)($m['url_app'] ?? '') ?: $entryUrl;
$icon   = (string)($m['icon'] ?? '🕹️');
$img    = (string)($m['img'] ?? '');
$nom    = (string)($m['nom'] ?? $p);
$desc   = (string)($m['desc'] ?? '');
$details= (string)($m['details'] ?? '');
$liens  = is_array($m['liens'] ?? null) ? $m['liens'] : [];

function pe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Mini-rendu texte → HTML sûr : ## titre · - liste · **gras** · ligne vide = paragraphe */
function render_details(string $txt): string {
    $txt = str_replace(["\r\n","\r"], "\n", $txt);
    $lines = explode("\n", $txt);
    $html = ''; $inList = false;
    $inline = function($s){
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('#\[([^\]]+)\]\((https?://[^\s)]+)\)#', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s);
        return $s;
    };
    foreach ($lines as $ln) {
        $t = trim($ln);
        if ($t === '') { if ($inList){$html.='</ul>';$inList=false;} continue; }
        if (strpos($t, '## ') === 0) {
            if ($inList){$html.='</ul>';$inList=false;}
            $html .= '<h2>'.$inline(substr($t,3)).'</h2>';
        } elseif ($t[0] === '-' && isset($t[1]) && $t[1] === ' ') {
            if (!$inList){$html.='<ul>';$inList=true;}
            $html .= '<li>'.$inline(substr($t,2)).'</li>';
        } else {
            if ($inList){$html.='</ul>';$inList=false;}
            $html .= '<p>'.$inline($t).'</p>';
        }
    }
    if ($inList) $html .= '</ul>';
    return $html;
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $valid ? pe($nom).' — Projet' : 'Projet introuvable' ?></title>
<meta name="description" content="<?= pe(mb_substr($desc, 0, 160)) ?>">
<style>
  :root{--bg:#0e1526;--panel:#151d31;--panel2:#1b2540;--line:#2a3450;--text:#eaf0ff;
        --muted:#9fb0d0;--accent:#5b8cff;--accent2:#3a6bff;--ok:#37c98b}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.6}
  a{color:var(--accent)}
  .wrap{max-width:820px;margin:0 auto;padding:24px 18px 70px}
  .back{display:inline-block;color:var(--muted);text-decoration:none;font-size:.9rem;margin-bottom:20px}
  .back:hover{color:var(--text)}

  .hero{display:flex;gap:18px;align-items:center;background:linear-gradient(135deg,var(--panel2),var(--panel));
        border:1px solid var(--line);border-radius:18px;padding:22px;margin-bottom:24px}
  .hero .ic{width:82px;height:82px;flex:none;border-radius:16px;border:1px solid var(--line);
            display:flex;align-items:center;justify-content:center;font-size:2.6rem;overflow:hidden;background:#0c1220}
  .hero .ic img{width:100%;height:100%;object-fit:cover}
  .hero h1{margin:0 0 4px;font-size:1.7rem}
  .hero .folder{color:var(--muted);font-size:.82rem}
  .hero .lead{color:var(--muted);margin:8px 0 0;font-size:1rem}

  .content h2{font-size:1.15rem;margin:26px 0 8px;padding-bottom:6px;border-bottom:1px solid var(--line)}
  .content p{margin:10px 0}
  .content ul{margin:10px 0;padding-left:22px}
  .content li{margin:5px 0}
  .content strong{color:#fff}

  .links{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:20px;margin-top:34px}
  .links h2{margin:0 0 14px;font-size:1.1rem}
  .btn-app{display:inline-flex;align-items:center;gap:8px;background:var(--accent2);color:#fff;
           text-decoration:none;font-weight:600;padding:13px 20px;border-radius:11px;font-size:1rem}
  .btn-app:hover{background:var(--accent)}
  .more{list-style:none;padding:0;margin:16px 0 0;display:flex;flex-direction:column;gap:8px}
  .more a{display:flex;align-items:center;gap:10px;background:var(--panel2);border:1px solid var(--line);
          border-radius:10px;padding:11px 14px;text-decoration:none;color:var(--text)}
  .more a:hover{border-color:var(--accent)}
  .more .ar{margin-left:auto;color:var(--muted)}
  .empty{color:var(--muted);font-size:.9rem}
  .err{text-align:center;padding:60px 20px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="index.php">⮜ Retour au portfolio</a>

<?php if (!$valid): ?>
  <div class="err">
    <h1>Projet introuvable</h1>
    <p>Le projet demandé n'existe pas. <a href="index.php">Retour à l'accueil</a>.</p>
  </div>
<?php else: ?>
  <div class="hero">
    <div class="ic"><?php if ($img !== ''): ?><img src="<?= pe($img) ?>" alt=""><?php else: ?><?= pe($icon) ?><?php endif; ?></div>
    <div>
      <h1><?= pe($nom) ?></h1>
      <div class="folder">📁 <?= pe($p) ?></div>
      <?php if ($desc !== ''): ?><p class="lead"><?= pe($desc) ?></p><?php endif; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($details !== ''): ?>
      <?= render_details($details) ?>
    <?php elseif ($desc !== ''): ?>
      <h2>Présentation</h2><p><?= pe($desc) ?></p>
      <p class="empty">Description détaillée à compléter dans l'espace admin.</p>
    <?php else: ?>
      <p class="empty">Aucune description pour l'instant. À compléter dans l'espace admin.</p>
    <?php endif; ?>
  </div>

  <div class="links">
    <h2>🔗 Liens</h2>
    <?php if ($urlApp !== ''): ?>
      <a class="btn-app" href="<?= pe($urlApp) ?>"<?= preg_match('~^https?://~i',$urlApp)?' target="_blank" rel="noopener"':'' ?>>🚀 Ouvrir l'application</a>
    <?php endif; ?>
    <?php if ($liens): ?>
      <ul class="more">
        <?php foreach ($liens as $ln): $u=(string)($ln['url']??''); if($u==='')continue; ?>
        <li><a href="<?= pe($u) ?>" target="_blank" rel="noopener">🔎 <?= pe($ln['label'] ?? $u) ?><span class="ar">↗</span></a></li>
        <?php endforeach; ?>
      </ul>
    <?php elseif ($urlApp === ''): ?>
      <p class="empty">Aucun lien pour l'instant.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
