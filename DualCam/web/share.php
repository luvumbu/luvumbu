<?php
// === Page PUBLIQUE de partage DualCam ===
//   share.php?t=<jeton>  — visible SANS connexion, en lecture seule.
//   N'affiche que les vidéos DualCam d'un compte dont le partage public est ACTIF.
//   Le propriétaire peut couper le partage depuis l'app ou depuis web/dualcam.php.

require __DIR__ . '/../lib/bootstrap.php';

if (!Db::isReady()) { http_response_code(503); exit('Service indisponible'); }
Auth::ensureSchema();

$t     = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['t'] ?? ''));
$owner = $t !== '' ? Auth::userIdFromShareToken($t) : null;

// Partage introuvable ou désactivé → page neutre, aucune fuite d'information.
if ($owner === null) {
    http_response_code(404);
    ?>
    <!doctype html><html lang="fr"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partage indisponible</title>
    <style>
      body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
           font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#e6f2ef;
           background:radial-gradient(1000px 500px at 20% -10%,#0f3d3a 0%,transparent 55%),#08111a;}
      .card{max-width:360px;text-align:center;background:rgba(16,32,40,.85);border:1px solid rgba(148,163,184,.15);
            padding:34px 28px;border-radius:22px;}
      h1{font-size:22px;margin:0 0 8px;} p{color:#8daab0;margin:0;}
    </style></head><body><div class="card">
      <div style="font-size:44px">🔒</div>
      <h1>Partage indisponible</h1>
      <p>Ce lien de partage n'existe pas ou a été désactivé par son propriétaire.</p>
    </div></body></html>
    <?php
    exit;
}

// Nom d'affichage du propriétaire (pour l'en-tête).
$un = Db::pdo()->prepare('SELECT username FROM ' . TBL_USERS . ' WHERE id = ?');
$un->execute([$owner]);
$ownerName = (string) ($un->fetch(PDO::FETCH_ASSOC)['username'] ?? 'DualCam');

// ---- Données : vidéos DualCam publiques de ce compte ----
$perPage = 24;
$where = "user_id = :uid AND deleted_at IS NULL AND source = 'dualcam'";

$c = Db::pdo()->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " WHERE $where");
$c->execute([':uid' => $owner]);
$total = (int) $c->fetch(PDO::FETCH_ASSOC)['c'];

['pages' => $pages, 'page' => $page, 'offset' => $offset] =
    Photos::paginate($total, (int) ($_GET['p'] ?? 1), $perPage);

$hasGeo = true;
try { Db::pdo()->query("SELECT latitude, longitude FROM " . TBL_PHOTOS . " LIMIT 0"); }
catch (Throwable $e) { $hasGeo = false; }
$geoCols = $hasGeo ? "latitude, longitude" : "NULL AS latitude, NULL AS longitude";

$stmt = Db::pdo()->prepare(
    "SELECT id, original_name, taken_at, uploaded_at, stored_path, size_bytes, $geoCols
     FROM " . TBL_PHOTOS . " WHERE $where ORDER BY COALESCE(taken_at, uploaded_at) DESC, id DESC
     LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':uid', $owner, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = Photos::filterExisting($stmt->fetchAll(PDO::FETCH_ASSOC), $owner);

$tok = htmlspecialchars($t, ENT_QUOTES); // jeton nettoyé, sûr à insérer dans les URLs
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>DualCam — Partage de <?= htmlspecialchars($ownerName) ?></title>
<style>
  :root{ --bg:#08111a; --panel:#0f2028; --panel2:#0c1a26; --line:#1e3a3f; --ink:#e6f2ef; --muted:#8daab0; --accent:#14b8a6; --violet:#6366f1; }
  *{box-sizing:border-box;}
  body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);min-height:100vh;
       background:radial-gradient(1000px 500px at 20% -10%,#0f3d3a 0%,transparent 55%),radial-gradient(800px 500px at 110% 10%,#13294a 0%,transparent 50%),var(--bg);}
  header{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:16px 28px;background:rgba(8,17,26,.7);backdrop-filter:blur(6px);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:6;flex-wrap:wrap;}
  header h1{font-size:18px;margin:0;font-weight:800;}
  .badge-pub{font-size:.8rem;color:#0b1220;background:var(--accent);border-radius:999px;padding:4px 11px;font-weight:800;}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;padding:18px 22px 22px;max-width:1100px;margin:0 auto;}
  .card{background:linear-gradient(160deg,var(--panel),var(--panel2));border:1px solid var(--line);border-radius:16px;overflow:hidden;transition:transform .18s,border-color .18s;position:relative;}
  .card:hover{transform:translateY(-4px);border-color:var(--accent);}
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
  .modal{position:fixed;inset:0;z-index:50;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(4,10,16,.7);backdrop-filter:blur(3px);}
  .modal.open{display:flex;}
  .sheet{position:relative;width:100%;max-width:420px;background:linear-gradient(160deg,var(--panel),var(--panel2));border:1px solid var(--line);border-radius:18px;padding:24px 22px;box-shadow:0 24px 70px rgba(0,0,0,.6);}
  .sheet-title{font-size:15px;font-weight:700;color:var(--ink);margin:0 30px 18px 0;word-break:break-word;}
  .sheet-close{position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:50%;border:1px solid var(--line);background:#12242c;color:#cbd8ef;font-size:15px;cursor:pointer;}
  .sheet-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;margin:8px 0;padding:13px;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;text-decoration:none;border:1px solid var(--line);background:#12242c;color:#e6f2ef;}
  .sheet-btn:hover{border-color:var(--accent);}
  .sheet-btn.primary{border:0;background:linear-gradient(135deg,var(--accent),var(--violet));color:#fff;}
  .sheet-btn.ghost{background:transparent;color:var(--muted);}
  .gps-box{background:rgba(8,14,28,.5);border:1px solid var(--line);border-radius:12px;padding:13px 15px;font-size:.9rem;color:#cbd8ef;margin-bottom:6px;line-height:1.7;}
  .addr-box{margin-top:12px;font-size:.9rem;color:#cbd8ef;line-height:1.5;min-height:1px;}
</style>
</head>
<body>
  <header>
    <h1>🎥 DualCam — <?= htmlspecialchars($ownerName) ?> · <?= $total ?> vidéo(s)</h1>
    <span class="badge-pub">Partage public</span>
  </header>

  <?php if ($total === 0): ?>
    <div class="empty">Aucune vidéo partagée pour l'instant.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $r): $id = (int) $r['id'];
            $size = Photos::humanSize((int) ($r['size_bytes'] ?? 0));
            $hasPos = is_numeric($r['latitude']) && is_numeric($r['longitude']);
            $lat = $hasPos ? (float) $r['latitude'] : '';
            $lng = $hasPos ? (float) $r['longitude'] : ''; ?>
        <div class="card">
          <?php if ($hasPos): ?><span class="geo-dot" title="Vidéo géolocalisée">📍</span><?php endif; ?>
          <span class="play-badge">▶</span>
          <button type="button" class="thumb"
                  data-view="../api/media.php?id=<?= $id ?>&amp;share=<?= $tok ?>"
                  data-name="<?= htmlspecialchars($r['original_name'], ENT_QUOTES) ?>"
                  data-lat="<?= $lat ?>" data-lng="<?= $lng ?>"
                  onclick="openCard(this)">
            <img loading="lazy" src="../api/media.php?id=<?= $id ?>&amp;share=<?= $tok ?>&amp;thumb=1" alt="">
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
    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php if ($page > 1): ?><a href="?t=<?= $tok ?>&amp;p=<?= $page - 1 ?>">‹ Précédent</a><?php endif; ?>
        <span class="cur">Page <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="?t=<?= $tok ?>&amp;p=<?= $page + 1 ?>">Suivant ›</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

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
      gps.innerHTML = '📍 Position inconnue pour cette vidéo.';
      actions.innerHTML = '';
    }
  }
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
