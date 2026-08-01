<?php
// === Page publique d'un album partagé ===
//   web/share.php?a=<token>
// Affiche les photos de l'album désigné par le jeton. Aucun compte n'est nécessaire.
// Si l'album est protégé par un mot de passe, il est demandé une fois puis mémorisé
// dans la session. Les fichiers eux-mêmes sont servis par api/media.php?id=…&a=<token>.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

// Un lien de partage ne doit jamais finir dans un moteur de recherche.
header('X-Robots-Tag: noindex, nofollow, noarchive');

/** Page d'erreur sobre (le visiteur n'a pas à savoir ce qui existe ou non). */
function shareError(string $titre, string $msg): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex,nofollow">'
       . '<title>Album indisponible</title></head>'
       . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
       . 'font-family:system-ui,-apple-system,sans-serif;background:#0b1220;color:#e6edf7;text-align:center;padding:24px;">'
       . '<div><div style="font-size:46px;margin-bottom:10px;">📁</div>'
       . '<h2 style="margin:0 0 8px;font-size:20px;">' . htmlspecialchars($titre) . '</h2>'
       . '<p style="color:#8da2c0;margin:0;max-width:340px;">' . htmlspecialchars($msg) . '</p></div></body></html>';
    exit;
}

if (!Db::isReady()) shareError('Album indisponible', 'Le serveur est momentanément indisponible. Réessaie plus tard.');
Albums::ensureSchema();

$token = (string) ($_GET['a'] ?? '');
$album = Albums::byToken($token);
if (!$album) shareError('Album introuvable', "Ce lien de partage n'existe pas, ou il a été remplacé par son propriétaire.");
if (Albums::isExpired($album)) shareError('Lien expiré', "Ce lien de partage a expiré. Demande-en un nouveau à la personne qui te l'a envoyé.");

// ---- Mot de passe éventuel ----
$needPass = !empty($album['pass_hash']);
$unlocked = !$needPass || !empty($_SESSION['album_ok'][$token]);
$err      = '';

if ($needPass && !$unlocked) {
    // Garde-fou anti-tâtonnement, volontairement doux : le lien lui-même est déjà secret.
    $tries  = (int) ($_SESSION['album_try'][$token] ?? 0);
    $locked = ($_SESSION['album_lock'][$token] ?? 0) > time();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_pass'])) {
        if ($locked) {
            $err = 'Trop d\'essais. Patiente quelques minutes avant de réessayer.';
        } elseif (password_verify((string) $_POST['share_pass'], (string) $album['pass_hash'])) {
            $_SESSION['album_ok'][$token] = true;
            unset($_SESSION['album_try'][$token], $_SESSION['album_lock'][$token]);
            header('Location: share.php?a=' . urlencode($token));
            exit;
        } else {
            sleep(1); // ralentit nettement une tentative automatisée
            $tries++;
            $_SESSION['album_try'][$token] = $tries;
            if ($tries >= 8) {
                $_SESSION['album_lock'][$token] = time() + 600;
                $_SESSION['album_try'][$token]  = 0;
                $err = 'Trop d\'essais. Réessaie dans 10 minutes.';
            } else {
                $err = 'Mot de passe incorrect.';
            }
        }
    }
    ?>
    <!doctype html><html lang="fr"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?= Pwa::head('..', false) ?>
    <title>Album protégé</title>
    <style>
      body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;
             font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:#e6edf7;
             background:radial-gradient(1200px 600px at 10% -10%, #1b2c52 0%, transparent 55%),
                        radial-gradient(900px 500px at 110% 10%, #143042 0%, transparent 50%), #0b1220; }
      .card { width:100%; max-width:340px; background:rgba(22,33,58,.85); border:1px solid rgba(148,163,184,.15);
              padding:32px 26px; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.55); text-align:center; }
      h1 { font-size:20px; margin:0 0 6px; } p.sub { color:#8da2c0; font-size:13.5px; margin:0 0 20px; }
      input { width:100%; box-sizing:border-box; padding:12px; border-radius:11px; border:1px solid #22304f;
              background:#0b1220; color:#fff; font-size:15px; }
      button { width:100%; margin-top:14px; padding:12px; border:0; border-radius:11px; font-size:15px; font-weight:700;
               cursor:pointer; color:#fff; background:linear-gradient(135deg,#4f8cff,#1d4ed8); }
      .err { color:#fca5a5; font-size:13px; margin-top:14px; background:rgba(127,29,29,.3);
             border:1px solid #7f1d1d; padding:10px; border-radius:10px; }
    </style></head>
    <body><form class="card" method="post">
      <div style="font-size:40px;margin-bottom:8px;">🔒</div>
      <h1><?= htmlspecialchars($album['name']) ?></h1>
      <p class="sub">Cet album est protégé. Entre le mot de passe qu'on t'a communiqué.</p>
      <input type="password" name="share_pass" placeholder="Mot de passe" autofocus required>
      <button type="submit">Voir l'album</button>
      <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    </form></body></html>
    <?php
    exit;
}

// ---- Affichage de l'album ----
$photos = Photos::filterExisting(Albums::photos((int) $album['id']), (int) $album['user_id']);
$tok    = rawurlencode($token);
$zipOk  = class_exists('ZipArchive');

// Données transmises au visualiseur plein écran (nom, type, dimensions inconnues côté serveur).
$items = [];
foreach ($photos as $p) {
    $items[] = [
        'id'   => (int) $p['id'],
        'name' => (string) $p['original_name'],
        'cat'  => Photos::categoryOf($p['original_name'], (string) $p['stored_path']),
        'date' => Photos::frDate($p['taken_at'] ?: $p['uploaded_at']),
        'size' => Photos::humanSize((int) ($p['size_bytes'] ?? 0)),
    ];
}
$totalBytes = 0;
foreach ($photos as $p) $totalBytes += (int) ($p['size_bytes'] ?? 0);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<?= Pwa::head('..', false) ?>
<title><?= htmlspecialchars($album['name']) ?> — Album partagé</title>
<style>
  :root{ --bg:#0b1220; --panel:#111c33; --panel2:#0e1830; --line:#22304f;
         --ink:#e6edf7; --muted:#8da2c0; --accent:#4f8cff; --violet:#a78bfa; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:var(--ink); min-height:100vh;
         background:radial-gradient(1200px 600px at 10% -10%, #1b2c52 0%, transparent 55%),
                    radial-gradient(900px 500px at 110% 10%, #143042 0%, transparent 50%), var(--bg); }
  header { padding:20px 24px; border-bottom:1px solid var(--line); background:rgba(8,14,28,.7);
           -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px);
           display:flex; gap:14px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
  header h1 { font-size:19px; margin:0 0 3px; font-weight:800; }
  header .sub { color:var(--muted); font-size:13.5px; }
  .btn { border:0; padding:11px 17px; border-radius:11px; font-size:.9rem; font-weight:700; cursor:pointer;
         text-decoration:none; color:#fff; background:linear-gradient(135deg,var(--accent),#1d4ed8); display:inline-block; }
  .btn:hover { filter:brightness(1.14); }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:12px;
          padding:18px 22px 40px; max-width:1200px; margin:0 auto; }
  .cell { position:relative; background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line);
          border-radius:14px; overflow:hidden; cursor:zoom-in; transition:transform .18s, border-color .18s; }
  .cell:hover { transform:translateY(-3px); border-color:var(--accent); }
  .cell img { width:100%; height:160px; object-fit:cover; display:block; background:#0a1124; }
  .cell.fileicon img { object-fit:contain; padding:34px 26px; }
  .cell .cap { padding:8px 11px; font-size:11.5px; color:var(--muted);
               overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .play { position:absolute; top:58px; left:50%; transform:translateX(-50%); width:46px; height:46px; border-radius:50%;
          background:rgba(8,14,28,.62); border:2px solid rgba(255,255,255,.85); color:#fff; font-size:19px;
          display:flex; align-items:center; justify-content:center; padding-left:4px; pointer-events:none; }
  .empty { text-align:center; padding:70px 20px; color:var(--muted); line-height:1.7; }
  footer { text-align:center; color:#64748b; font-size:12px; padding:22px; }
  /* --- Visionneuse plein écran --- */
  #lb { position:fixed; inset:0; z-index:50; background:rgba(4,8,18,.96); display:none;
        flex-direction:column; align-items:center; justify-content:center; }
  #lb.on { display:flex; }
  #lb .stage { flex:1; width:100%; display:flex; align-items:center; justify-content:center; padding:56px 16px 8px; min-height:0; }
  #lb img, #lb video, #lb audio { max-width:100%; max-height:100%; border-radius:10px; }
  #lb .bar { width:100%; display:flex; gap:10px; align-items:center; justify-content:space-between;
             padding:12px 18px 20px; flex-wrap:wrap; }
  #lb .info { color:var(--muted); font-size:13px; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  #lb .nav { position:absolute; top:50%; transform:translateY(-50%); background:rgba(17,28,51,.85);
             border:1px solid var(--line); color:#fff; width:46px; height:46px; border-radius:50%;
             font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
  #lb .prev { left:14px; } #lb .next { right:14px; }
  #lb .close { position:absolute; top:14px; right:16px; background:none; border:0; color:#cbd8ef; font-size:30px; cursor:pointer; line-height:1; }
  #lb .doc { text-align:center; color:var(--muted); }
  @media (max-width:520px){ #lb .nav { width:40px; height:40px; font-size:19px; } }
</style>
</head>
<body>
  <header>
    <div>
      <h1>📁 <?= htmlspecialchars($album['name']) ?></h1>
      <div class="sub">
        <?= count($photos) ?> fichier(s)<?= $totalBytes > 0 ? ' · ' . htmlspecialchars(Photos::humanSize($totalBytes)) : '' ?>
        <?php if (!empty($album['expires_at'])): ?> · disponible jusqu'au <?= htmlspecialchars(date('d/m/Y', strtotime($album['expires_at']))) ?><?php endif; ?>
      </div>
    </div>
    <?php if ($photos && $zipOk): ?>
      <a class="btn" href="share_zip.php?a=<?= $tok ?>">⬇️ Tout télécharger (ZIP)</a>
    <?php endif; ?>
  </header>

  <?php if (!$photos): ?>
    <div class="empty">Cet album est vide pour l'instant.<br>Reviens plus tard 🙂</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($items as $i => $it): $isImg = ($it['cat'] === 'photo'); ?>
        <div class="cell<?= $isImg ? '' : ' fileicon' ?>" onclick="openLb(<?= $i ?>)" title="<?= htmlspecialchars($it['name']) ?>">
          <img loading="lazy" src="../api/media.php?id=<?= $it['id'] ?>&amp;a=<?= $tok ?>&amp;thumb=1" alt="">
          <?php if ($it['cat'] === 'video'): ?><span class="play">▶</span><?php endif; ?>
          <div class="cap"><?= htmlspecialchars($it['name']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <footer>Partagé via PhotoSync</footer>

  <div id="lb" role="dialog" aria-modal="true">
    <button class="close" onclick="closeLb()" aria-label="Fermer">×</button>
    <button class="nav prev" onclick="step(-1)" aria-label="Précédent">‹</button>
    <button class="nav next" onclick="step(1)" aria-label="Suivant">›</button>
    <div class="stage" id="lbStage"></div>
    <div class="bar">
      <div class="info" id="lbInfo"></div>
      <a class="btn" id="lbDl" href="#" download>⬇️ Télécharger</a>
    </div>
  </div>

<script>
  var ITEMS = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var TOK = <?= json_encode($token) ?>;
  var cur = 0;

  function mediaUrl(id, thumb){
    return '../api/media.php?id=' + id + '&a=' + encodeURIComponent(TOK) + (thumb ? '&thumb=1' : '');
  }

  function render(){
    var it = ITEMS[cur], url = mediaUrl(it.id, false), stage = document.getElementById('lbStage');
    stage.innerHTML = '';
    var el;
    if (it.cat === 'photo')      { el = document.createElement('img'); el.src = url; el.alt = it.name; }
    else if (it.cat === 'video') { el = document.createElement('video'); el.src = url; el.controls = true; el.autoplay = true; el.playsInline = true; }
    else if (it.cat === 'audio') { el = document.createElement('audio'); el.src = url; el.controls = true; el.autoplay = true; }
    else {
      el = document.createElement('div'); el.className = 'doc';
      el.innerHTML = '<div style="font-size:54px">📄</div><p>Ce fichier ne s\'affiche pas dans le navigateur.<br>Utilise le bouton Télécharger.</p>';
    }
    stage.appendChild(el);
    document.getElementById('lbInfo').textContent =
      it.name + (it.date ? ' · ' + it.date : '') + (it.size ? ' · ' + it.size : '');
    var dl = document.getElementById('lbDl');
    dl.href = url; dl.setAttribute('download', it.name);
  }

  function openLb(i){ cur = i; document.getElementById('lb').classList.add('on'); render(); }
  function closeLb(){
    document.getElementById('lb').classList.remove('on');
    document.getElementById('lbStage').innerHTML = ''; // coupe une vidéo en cours
  }
  function step(d){ if (!ITEMS.length) return; cur = (cur + d + ITEMS.length) % ITEMS.length; render(); }

  document.addEventListener('keydown', function(e){
    if (!document.getElementById('lb').classList.contains('on')) return;
    if (e.key === 'Escape') closeLb();
    else if (e.key === 'ArrowLeft') step(-1);
    else if (e.key === 'ArrowRight') step(1);
  });
  // Clic sur le fond (hors média et hors boutons) : ferme la visionneuse.
  document.getElementById('lb').addEventListener('click', function(e){ if (e.target === this) closeLb(); });
</script>
</body>
</html>
