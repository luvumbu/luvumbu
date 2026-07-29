<?php
// === Galerie web DÉDIÉE à DualCam ===
//   Même compte / même serveur que PhotoSync, mais n'affiche QUE les vidéos
//   envoyées par l'application DualCam (source = 'dualcam').
//   https://luvumbu.com/DualCam/web/dualcam.php

require __DIR__ . '/../lib/bootstrap.php';

// Connexion / déconnexion Google (même session web que PhotoSync).
$sess  = Auth::webSession('dualcam.php');
$uid   = $sess['uid'];
$uname = $sess['uname'];
$error = $sess['error'];

// ---- Actions (connecté) : suppression groupée OU bascule du partage public ----
if ($uid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'trash') {
        foreach (Request::ids() as $i) {
            Photos::trash($i, $uid); // scopé au compte : ne touche jamais aux fichiers d'un autre
        }
        header('Location: dualcam.php?p=' . max(1, (int) ($_POST['p'] ?? 1)));
        exit;
    }
    if ($act === 'share') {
        Auth::setPublicShare($uid, ($_POST['enabled'] ?? '') === '1');
        header('Location: dualcam.php');
        exit;
    }
}

// ---- Page de connexion ----
if (!$uid) {
    ?>
    <!doctype html><html lang="fr"><head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>DualCam — Connexion</title>
        <style>
            *{box-sizing:border-box;}
            body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#e6f2ef;
                 background:radial-gradient(1000px 500px at 20% -10%,#0f3d3a 0%,transparent 55%),radial-gradient(800px 500px at 110% 10%,#13294a 0%,transparent 50%),#08111a;}
            .card{width:100%;max-width:360px;background:rgba(16,32,40,.85);backdrop-filter:blur(8px);border:1px solid rgba(148,163,184,.15);padding:34px 28px;border-radius:22px;box-shadow:0 20px 60px rgba(0,0,0,.55);text-align:center;}
            .logo{width:72px;height:72px;margin:0 auto 16px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:38px;background:linear-gradient(135deg,#14b8a6,#6366f1);box-shadow:0 8px 24px rgba(20,184,166,.4);}
            h1{font-size:23px;margin:0 0 6px;font-weight:800;}
            p.sub{color:#94a3b8;font-size:14px;margin:0 0 24px;}
            .gbtn{display:flex;justify-content:center;min-height:44px;}
            .err{color:#fca5a5;font-size:13px;margin-top:14px;background:rgba(127,29,29,.3);border:1px solid #7f1d1d;padding:10px;border-radius:10px;}
            .hint{color:#64748b;font-size:12px;margin-top:18px;}
        </style></head>
    <body><div class="card">
        <div class="logo">🎥</div>
        <h1>DualCam</h1><p class="sub">Connecte-toi avec ton compte Google</p>
        <div class="gbtn"><?= Auth::googleButtonHtml() ?></div>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="hint">Mêmes identifiants que l'application DualCam.</div>
    </div></body></html>
    <?php
    exit;
}

// ---- État du partage public (global pour ce compte) ----
$shareOn  = Auth::isPublicShare($uid);
$shareUrl = Auth::shareUrl(Auth::shareToken($uid));

// ---- Données : uniquement les médias DualCam de ce compte ----
$perPage = 24;
$where = "user_id = :uid AND deleted_at IS NULL AND source = 'dualcam'";

$c = Db::pdo()->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " WHERE $where");
$c->execute([':uid' => $uid]);
$total = (int) $c->fetch(PDO::FETCH_ASSOC)['c'];

['pages' => $pages, 'page' => $page, 'offset' => $offset] =
    Photos::paginate($total, (int) ($_GET['p'] ?? 1), $perPage);

// COALESCE : tolère une base pas encore migrée (colonnes GPS absentes => NULL partout).
$hasGeo = true;
try { Db::pdo()->query("SELECT latitude, longitude FROM " . TBL_PHOTOS . " LIMIT 0"); }
catch (Throwable $e) { $hasGeo = false; }
$geoCols = $hasGeo ? "latitude, longitude" : "NULL AS latitude, NULL AS longitude";

$stmt = Db::pdo()->prepare(
    "SELECT id, original_name, taken_at, uploaded_at, stored_path, size_bytes, $geoCols
     FROM " . TBL_PHOTOS . " WHERE $where ORDER BY COALESCE(taken_at, uploaded_at) DESC, id DESC
     LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = Photos::filterExisting($stmt->fetchAll(PDO::FETCH_ASSOC), $uid);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>DualCam — Mes vidéos</title>
<style>
  :root{ --bg:#08111a; --panel:#0f2028; --panel2:#0c1a26; --line:#1e3a3f; --ink:#e6f2ef; --muted:#8daab0; --accent:#14b8a6; --violet:#6366f1; }
  *{box-sizing:border-box;}
  body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);min-height:100vh;
       background:radial-gradient(1000px 500px at 20% -10%,#0f3d3a 0%,transparent 55%),radial-gradient(800px 500px at 110% 10%,#13294a 0%,transparent 50%),var(--bg);}
  header{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:16px 28px;background:rgba(8,17,26,.7);backdrop-filter:blur(6px);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:6;flex-wrap:wrap;}
  header h1{font-size:18px;margin:0;font-weight:800;}
  .nav a{color:#bcd0ef;text-decoration:none;font-size:.9rem;background:#12242c;border:1px solid var(--line);padding:8px 13px;border-radius:10px;}
  .nav a:hover{border-color:var(--accent);}
  /* Barre d'actions collante : sélection + suppression */
  .toolbar{position:sticky;top:0;z-index:20;display:flex;gap:10px;align-items:center;padding:12px 18px;max-width:1100px;margin:8px auto 0;flex-wrap:wrap;background:rgba(12,26,38,.96);backdrop-filter:blur(8px);border:1px solid var(--line);border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.4);}
  .toolbar label{font-size:.9rem;color:#cbd8ef;display:flex;gap:7px;align-items:center;cursor:pointer;}
  .toolbar .spacer{flex:1;}
  .bulk-btn{border:0;padding:11px 18px;border-radius:11px;font-size:.9rem;font-weight:700;cursor:pointer;color:#fff;background:linear-gradient(135deg,#ef4444,#b91c1c);transition:filter .15s;}
  .bulk-btn:hover{filter:brightness(1.12);}
  /* Bandeau de partage public */
  .share{max-width:1100px;margin:14px auto 0;padding:14px 18px;background:linear-gradient(160deg,var(--panel),var(--panel2));border:1px solid var(--line);border-radius:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;}
  .share .st{display:flex;align-items:center;gap:9px;font-weight:700;font-size:.95rem;}
  .share .dot{width:11px;height:11px;border-radius:50%;}
  .share.on .dot{background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.18);}
  .share.off .dot{background:#f87171;box-shadow:0 0 0 4px rgba(248,113,113,.18);}
  .share .spacer{flex:1;}
  .share input.url{flex:1;min-width:200px;background:#0a1622;border:1px solid var(--line);color:#cbd8ef;border-radius:10px;padding:9px 12px;font-size:.85rem;}
  .share .s-btn{border:0;padding:10px 15px;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
  .s-copy{background:#334155;} .s-open{background:linear-gradient(135deg,var(--accent),var(--violet));} .s-on{background:linear-gradient(135deg,#059669,#34d399);} .s-off{background:linear-gradient(135deg,#b91c1c,#ef4444);}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;padding:18px 22px 22px;max-width:1100px;margin:0 auto;}
  .card{background:linear-gradient(160deg,var(--panel),var(--panel2));border:1px solid var(--line);border-radius:16px;overflow:hidden;transition:transform .18s,border-color .18s;position:relative;}
  .card:hover{transform:translateY(-4px);border-color:var(--accent);}
  .pick{position:absolute;top:10px;right:10px;width:26px;height:26px;z-index:3;cursor:pointer;accent-color:var(--accent);}
  .geo-dot{position:absolute;top:10px;left:10px;z-index:3;background:rgba(8,14,28,.75);border-radius:999px;padding:3px 8px;font-size:13px;line-height:1;}
  .thumb{position:relative;display:block;background:#0a1622;cursor:pointer;border:0;padding:0;width:100%;text-align:left;}
  .thumb img{width:100%;height:240px;object-fit:cover;display:block;}
  .play-badge{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:3;pointer-events:none;width:54px;height:54px;border-radius:50%;background:rgba(8,14,28,.6);border:2px solid rgba(255,255,255,.85);color:#fff;font-size:22px;display:flex;align-items:center;justify-content:center;padding-left:4px;}
  .date{position:absolute;left:10px;bottom:10px;background:rgba(8,14,28,.75);color:#fff;font-size:12px;font-weight:600;padding:6px 11px;border-radius:999px;}
  .meta{padding:11px 14px;}
  .type-tag{display:inline-block;font-size:11px;font-weight:700;color:#cbd8ef;background:rgba(30,58,63,.7);border:1px solid var(--line);padding:3px 9px;border-radius:999px;margin-bottom:8px;}
  .name{font-size:12px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:6px;}
  .size{font-size:12px;color:var(--muted);}
  .pager{display:flex;gap:10px;justify-content:center;align-items:center;padding:22px;flex-wrap:wrap;}
  .pager a,.pager span{padding:10px 18px;border-radius:11px;background:#12242c;border:1px solid var(--line);color:var(--ink);text-decoration:none;font-size:.9rem;}
  .pager .cur{background:linear-gradient(135deg,var(--accent),var(--violet));font-weight:700;border:0;}
  .empty{text-align:center;padding:70px 20px;color:var(--muted);font-size:16px;line-height:1.7;}
  /* Fenêtre (modale) au clic sur une vidéo */
  .modal{position:fixed;inset:0;z-index:50;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(4,10,16,.7);backdrop-filter:blur(3px);}
  .modal.open{display:flex;}
  .sheet{position:relative;width:100%;max-width:420px;background:linear-gradient(160deg,var(--panel),var(--panel2));border:1px solid var(--line);border-radius:18px;padding:24px 22px;box-shadow:0 24px 70px rgba(0,0,0,.6);}
  .sheet-title{font-size:15px;font-weight:700;color:var(--ink);margin:0 30px 18px 0;word-break:break-word;}
  .sheet-close{position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:50%;border:1px solid var(--line);background:#12242c;color:#cbd8ef;font-size:15px;cursor:pointer;}
  .sheet-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;margin:8px 0;padding:13px;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;text-decoration:none;border:1px solid var(--line);background:#12242c;color:#e6f2ef;}
  .sheet-btn:hover{border-color:var(--accent);}
  .sheet-btn.primary{border:0;background:linear-gradient(135deg,var(--accent),var(--violet));color:#fff;}
  .sheet-btn.ghost{background:transparent;color:var(--muted);}
  .quicklinks{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px;padding:26px 28px 8px;max-width:1150px;margin:0 auto;}
  .ql{display:flex;flex-direction:column;align-items:center;text-align:center;gap:4px;padding:30px 20px;border-radius:22px;text-decoration:none;color:var(--ink);
      background:linear-gradient(135deg,rgba(20,184,166,.16),rgba(99,102,241,.16));border:1px solid var(--line);
      transition:transform .14s ease,border-color .14s ease,box-shadow .14s ease;}
  .ql:hover{transform:translateY(-5px);border-color:var(--accent);box-shadow:0 16px 36px rgba(0,0,0,.5);}
  .ql .ql-ic{font-size:64px;line-height:1;filter:drop-shadow(0 6px 14px rgba(0,0,0,.45));}
  .ql b{font-size:1.35rem;margin-top:12px;font-weight:800;}
  .ql small{color:var(--muted);font-size:.92rem;margin-top:2px;}
  .ql-remote{background:linear-gradient(135deg,rgba(185,28,28,.20),rgba(99,102,241,.16));}
  .gps-box{background:rgba(8,14,28,.5);border:1px solid var(--line);border-radius:12px;padding:13px 15px;font-size:.9rem;color:#cbd8ef;margin-bottom:6px;line-height:1.7;}
  .addr-box{margin-top:12px;font-size:.9rem;color:#cbd8ef;line-height:1.5;min-height:1px;}
</style>
</head>
<body>
  <header>
    <h1>🎥 DualCam — <?= htmlspecialchars($uname) ?> · <?= $total ?> vidéo(s)</h1>
    <div class="nav">
      <a href="dualcam.php?logout=1">Déconnexion</a>
    </div>
  </header>

  <!-- Accès rapide : toutes les fonctions DualCam, toujours visibles -->
  <div class="quicklinks">
    <a class="ql ql-remote" href="remote.php"><span class="ql-ic">🎬</span><b>Télécommande</b><small>Démarrer / arrêter à distance</small></a>
    <a class="ql ql-vids"   href="dualcam.php"><span class="ql-ic">🎞️</span><b>Mes vidéos</b><small>Tous les enregistrements</small></a>
    <a class="ql ql-photo"  href="gallery.php"><span class="ql-ic">📸</span><b>Galerie PhotoSync</b><small>Photos & autres médias</small></a>
  </div>

  <!-- Bandeau : partage public global du compte -->
  <div class="share <?= $shareOn ? 'on' : 'off' ?>">
    <span class="st"><span class="dot"></span><?= $shareOn ? 'Partage public ACTIVÉ' : 'Partage public désactivé' ?></span>
    <?php if ($shareOn): ?>
      <input class="url" id="shareUrl" type="text" readonly value="<?= htmlspecialchars($shareUrl, ENT_QUOTES) ?>">
      <button type="button" class="s-btn s-copy" onclick="copyShare()">📋 Copier le lien</button>
      <a class="s-btn s-open" href="<?= htmlspecialchars($shareUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">↗ Ouvrir</a>
      <form method="post" style="margin:0"><input type="hidden" name="enabled" value="0">
        <button class="s-btn s-off" type="submit" name="action" value="share"
                onclick="return confirm('Désactiver le partage public ? Le lien ne fonctionnera plus.')">🔒 Désactiver</button></form>
    <?php else: ?>
      <span class="spacer"></span>
      <form method="post" style="margin:0"><input type="hidden" name="enabled" value="1">
        <button class="s-btn s-on" type="submit" name="action" value="share">🌐 Activer le partage public</button></form>
    <?php endif; ?>
  </div>

  <?php if ($total === 0): ?>
    <div class="empty">Aucune vidéo DualCam pour l'instant.<br>Filme puis envoie une vidéo depuis l'application DualCam 🎬</div>
  <?php else: ?>
      <form method="post">
        <input type="hidden" name="p" value="<?= $page ?>">
        <div class="toolbar">
          <label><input type="checkbox" onclick="toggleAll(this)"> Tout sélectionner</label>
          <span class="spacer"></span>
          <button class="bulk-btn" type="submit" name="action" value="trash"
                  onclick="return bulk('Mettre {n} vidéo(s) à la corbeille ? (récupérable 30 jours)')">🗑 Supprimer la sélection</button>
        </div>
        <div class="grid">
          <?php foreach ($rows as $r): $id = (int) $r['id'];
                $size = Photos::humanSize((int) ($r['size_bytes'] ?? 0));
                $hasPos = is_numeric($r['latitude']) && is_numeric($r['longitude']);
                $lat = $hasPos ? (float) $r['latitude'] : '';
                $lng = $hasPos ? (float) $r['longitude'] : ''; ?>
            <div class="card">
              <input class="pick" type="checkbox" name="ids[]" value="<?= $id ?>" title="Sélectionner">
              <?php if ($hasPos): ?><span class="geo-dot" title="Vidéo géolocalisée">📍</span><?php endif; ?>
              <span class="play-badge">▶</span>
              <button type="button" class="thumb"
                      data-view="view.php?id=<?= $id ?>"
                      data-name="<?= htmlspecialchars($r['original_name'], ENT_QUOTES) ?>"
                      data-lat="<?= $lat ?>" data-lng="<?= $lng ?>"
                      onclick="openCard(this)">
                <img loading="lazy" src="../api/media.php?id=<?= $id ?>&amp;thumb=1" alt="">
                <span class="date"><?= htmlspecialchars(Photos::frDate($r['taken_at'] ?: $r['uploaded_at'])) ?></span>
              </button>
              <div class="meta">
                <span class="type-tag">🎬 Vidéo</span>
                <div class="name"><?= htmlspecialchars($r['original_name']) ?></div>
                <?php if ($size !== ''): ?><div class="size"><?= $size ?></div><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </form>
    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php if ($page > 1): ?><a href="?p=<?= $page - 1 ?>">‹ Précédent</a><?php endif; ?>
        <span class="cur">Page <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="?p=<?= $page + 1 ?>">Suivant ›</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Fenêtre : Voir / Plus d'infos (GPS) -->
  <div id="modal" class="modal" onclick="if(event.target===this)closeModal()">
    <div class="sheet">
      <button class="sheet-close" type="button" onclick="closeModal()">✕</button>
      <div class="sheet-title" id="mTitle"></div>

      <div id="step1">
        <a id="mView" class="sheet-btn primary" href="#" target="_blank" rel="noopener">▶ Voir la vidéo</a>
        <button type="button" class="sheet-btn" onclick="showInfo()">ℹ️ Plus d'infos</button>
      </div>

      <div id="step2" style="display:none">
        <div class="gps-box" id="mGps"></div>
        <div id="mActions"></div>
        <div class="addr-box" id="mAddr"></div>
        <button type="button" class="sheet-btn ghost" onclick="backStep()">‹ Retour</button>
      </div>
    </div>
  </div>

<script>
  // --- Copie du lien de partage ---
  function copyShare(){
    var i = document.getElementById('shareUrl'); if (!i) return;
    i.select(); i.setSelectionRange(0, 99999);
    var done = function(){ var b = document.querySelector('.s-copy'); if (b){ var t = b.textContent; b.textContent = '✅ Copié !'; setTimeout(function(){ b.textContent = t; }, 1500); } };
    if (navigator.clipboard) navigator.clipboard.writeText(i.value).then(done, function(){ document.execCommand('copy'); done(); });
    else { document.execCommand('copy'); done(); }
  }

  // --- Sélection multiple ---
  function toggleAll(cb){ document.querySelectorAll('input[name="ids[]"]').forEach(x => x.checked = cb.checked); }
  function bulk(msg){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une vidéo.'); return false; }
    return confirm(msg.replace('{n}', n));
  }

  // --- Fenêtre Voir / Plus d'infos ---
  var cur = {};
  function openCard(el){
    cur = { view: el.dataset.view, name: el.dataset.name, lat: el.dataset.lat, lng: el.dataset.lng };
    document.getElementById('mTitle').textContent = cur.name;
    document.getElementById('mView').href = cur.view;
    document.getElementById('step1').style.display = '';
    document.getElementById('step2').style.display = 'none';
    document.getElementById('mAddr').textContent = '';
    document.getElementById('modal').classList.add('open');
  }
  function closeModal(){ document.getElementById('modal').classList.remove('open'); }
  function backStep(){
    document.getElementById('step1').style.display = '';
    document.getElementById('step2').style.display = 'none';
  }
  function showInfo(){
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = '';
    var gps = document.getElementById('mGps');
    var actions = document.getElementById('mActions');
    document.getElementById('mAddr').textContent = '';
    if (cur.lat && cur.lng){
      gps.innerHTML = '📍 Latitude : <b>' + cur.lat + '</b><br>📍 Longitude : <b>' + cur.lng + '</b>';
      var maps = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(cur.lat + ',' + cur.lng);
      actions.innerHTML =
        '<a class="sheet-btn primary" target="_blank" rel="noopener" href="' + maps + '">🗺️ Ouvrir la carte</a>' +
        '<button type="button" class="sheet-btn" onclick="showAddress()">🏠 Voir l\'adresse</button>';
    } else {
      gps.innerHTML = '📍 Position inconnue pour cette vidéo.<br><span style="color:#8daab0">(filmée sans GPS ou avant l\'activation)</span>';
      actions.innerHTML = '';
    }
  }
  // Adresse lisible depuis les coordonnées (OpenStreetMap / Nominatim, gratuit, sans clé).
  async function showAddress(){
    var addr = document.getElementById('mAddr');
    addr.textContent = '⏳ Recherche de l\'adresse…';
    try {
      var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=fr&lat='
              + encodeURIComponent(cur.lat) + '&lon=' + encodeURIComponent(cur.lng);
      var r = await fetch(url, { headers: { 'Accept': 'application/json' } });
      var j = await r.json();
      addr.innerHTML = j && j.display_name ? '🏠 ' + j.display_name : 'Adresse introuvable pour ce point.';
    } catch (e){
      addr.textContent = 'Adresse indisponible (problème réseau).';
    }
  }
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>
