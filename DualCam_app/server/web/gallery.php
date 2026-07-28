<?php
// === Galerie web par compte (login identifiant + mot de passe) ===
//   - chaque compte ne voit QUE ses photos
//   - corbeille (30 j), sélection multiple, nb par page réglable
//   https://luvumbu.com/web/gallery.php

require __DIR__ . '/../lib/bootstrap.php';

// Connexion / déconnexion (session web partagée avec upload_web.php).
$sess  = Auth::webSession('gallery.php');
$uid   = $sess['uid'];
$uname = $sess['uname'];
$error = $sess['error'];

// L'utilisateur connecté est-il administrateur ? (pour afficher le lien Admin)
$isAdmin = $uid ? Auth::isAdmin((int) $uid) : false;

// ---- Réglage nb par page ----
if ($uid && isset($_GET['perpage'])) {
    $pp = (int) $_GET['perpage'];
    if (!in_array($pp, Photos::PER_PAGE, true)) $pp = 5;
    setcookie('photosync_perpage', (string) $pp, time() + 31536000, '/');
    $_COOKIE['photosync_perpage'] = (string) $pp;
}
$perPage = isset($_COOKIE['photosync_perpage']) ? (int) $_COOKIE['photosync_perpage'] : 5;
if (!in_array($perPage, Photos::PER_PAGE, true)) $perPage = 5;

// ---- Actions (connecté) ----
if ($uid) {
    Photos::purgeOldTrash($uid);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        foreach (Request::ids() as $i) {
            switch ($_POST['action']) {
                case 'trash':   Photos::trash($i, $uid);         break;
                case 'restore': Photos::restore($i, $uid);       break;
                case 'purge':   Photos::deleteForever($i, $uid); break;
            }
        }
        $view = ($_POST['view'] ?? '') === 'corbeille' ? '&view=corbeille' : '';
        // On conserve les filtres/tri en cours après l'action.
        $kSrc  = in_array($_POST['src'] ?? '', ['phone', 'computer', 'web'], true) ? '&src=' . $_POST['src'] : '';
        $kType = in_array($_POST['type'] ?? '', ['photo', 'video', 'audio', 'document', 'other'], true) ? '&type=' . $_POST['type'] : '';
        $kSort = (in_array($_POST['sort'] ?? '', ['date_desc', 'date_asc', 'name_asc', 'name_desc', 'size_desc', 'size_asc', 'type'], true) && $_POST['sort'] !== 'date_desc') ? '&sort=' . $_POST['sort'] : '';
        header('Location: gallery.php?p=' . max(1, (int) ($_POST['p'] ?? 1)) . $view . $kSrc . $kType . $kSort);
        exit;
    }
}

// ---- Page de connexion ----
if (!$uid) {
    ?>
    <!doctype html><html lang="fr"><head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="../favicon.svg" type="image/svg+xml">
        <title>PhotoSync — Connexion</title>
        <style>
            * { box-sizing:border-box; }
            body { font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; color:#e6edf7;
                   background:
                     radial-gradient(1200px 600px at 10% -10%, #1b2c52 0%, transparent 55%),
                     radial-gradient(900px 500px at 110% 10%, #143042 0%, transparent 50%),
                     #0b1220; }
            .card { width:100%; max-width:360px; background:rgba(22,33,58,.85); -webkit-backdrop-filter:blur(8px); backdrop-filter:blur(8px);
                    border:1px solid rgba(148,163,184,.15); padding:34px 28px; border-radius:22px; box-shadow:0 20px 60px rgba(0,0,0,.55); text-align:center; }
            .logo { width:72px; height:72px; margin:0 auto 16px; border-radius:20px; display:flex; align-items:center; justify-content:center;
                    font-size:38px; background:linear-gradient(135deg,#4f8cff,#a78bfa); box-shadow:0 8px 24px rgba(79,140,255,.4); }
            h1 { font-size:23px; margin:0 0 6px; font-weight:800; letter-spacing:.5px; }
            p.sub { color:#94a3b8; font-size:14px; margin:0 0 24px; }
            .gbtn { display:flex; justify-content:center; min-height:44px; }
            .err { color:#fca5a5; font-size:13px; margin-top:14px; background:rgba(127,29,29,.3); border:1px solid #7f1d1d; padding:10px; border-radius:10px; }
            .hint { color:#64748b; font-size:12px; margin-top:18px; }
            .sep { display:flex; align-items:center; gap:12px; margin:22px 0 16px; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:1px; }
            .sep::before, .sep::after { content:""; flex:1; height:1px; background:rgba(148,163,184,.18); }
            .admin-btn { display:block; text-decoration:none; padding:13px; border-radius:12px; font-weight:700; font-size:15px;
                         color:#c4b5fd; border:1px solid rgba(124,58,237,.5); background:rgba(124,58,237,.12); transition:background .15s, color .15s; }
            .admin-btn:hover { background:rgba(124,58,237,.28); color:#fff; }
        </style></head>
    <body><div class="card">
        <div class="logo">📸</div>
        <h1>PhotoSync</h1><p class="sub">Connecte-toi avec ton compte Google</p>
        <div class="gbtn"><?= Auth::googleButtonHtml() ?></div>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="hint">Ton compte est créé automatiquement la première fois.</div>
        <div class="sep">ou</div>
        <a href="admin.php" class="admin-btn">🛠️ Espace administrateur</a>
    </div></body></html>
    <?php
    exit;
}

// ---- Données (scopées au compte) ----
$inTrash = ($_GET['view'] ?? '') === 'corbeille';
$where = 'user_id = :uid AND ' . ($inTrash ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL');
// Filtre d'affichage par origine (bouton « Afficher ») : 📱 phone / 💻 computer / 🌐 web. Vide = tout.
$srcFilter = in_array($_GET['src'] ?? '', ['phone', 'computer', 'web'], true) ? $_GET['src'] : '';
if ($srcFilter !== '') $where .= ' AND source = :src';
// Filtre par type de fichier (photo / vidéo / audio / document / autre).
$typeFilter = in_array($_GET['type'] ?? '', ['photo', 'video', 'audio', 'document', 'other'], true) ? $_GET['type'] : '';
// Condition inlinée (valeurs constantes côté code) → compatible avec les paramètres nommés.
if ($typeFilter !== '') $where .= ' AND ' . Photos::categoryCondition($typeFilter);
// Tri (ignoré dans la corbeille : on garde l'ordre de mise à la corbeille).
$allowedSort = ['date_desc', 'date_asc', 'name_asc', 'name_desc', 'size_desc', 'size_asc', 'type'];
$sortKey = in_array($_GET['sort'] ?? '', $allowedSort, true) ? $_GET['sort'] : 'date_desc';
$order = $inTrash ? 'deleted_at DESC' : Photos::sortClause($sortKey);

$c = Db::pdo()->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " WHERE $where");
$c->execute($srcFilter !== '' ? [':uid' => $uid, ':src' => $srcFilter] : [':uid' => $uid]);
$total = (int) $c->fetch(PDO::FETCH_ASSOC)['c'];
$tc = Db::pdo()->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " WHERE user_id = ? AND deleted_at IS NOT NULL"); $tc->execute([$uid]);
$trashCount = (int) $tc->fetch(PDO::FETCH_ASSOC)['c'];
['pages' => $pages, 'page' => $page, 'offset' => $offset] =
    Photos::paginate($total, (int) ($_GET['p'] ?? 1), $perPage);

$stmt = Db::pdo()->prepare(
    "SELECT id, original_name, taken_at, uploaded_at, deleted_at, stored_path, source, size_bytes
     FROM " . TBL_PHOTOS . " WHERE $where ORDER BY $order LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
if ($srcFilter !== '') $stmt->bindValue(':src', $srcFilter);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
// On exclut (et on efface) les entrées dont le fichier a disparu du disque.
$rows = Photos::filterExisting($stmt->fetchAll(PDO::FETCH_ASSOC), $uid);
// Paramètres de filtre/tri à propager dans les liens (pagination, etc.).
$filterQs = ($srcFilter !== '' ? '&src=' . $srcFilter : '')
          . ($typeFilter !== '' ? '&type=' . $typeFilter : '')
          . ($sortKey !== 'date_desc' ? '&sort=' . $sortKey : '');
$viewQs = ($inTrash ? '&view=corbeille' : '') . $filterQs;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title>PhotoSync — <?= $inTrash ? 'Corbeille' : 'Galerie' ?></title>
<style>
  :root{
    --bg:#0b1220; --panel:#111c33; --panel2:#0e1830; --line:#22304f;
    --ink:#e6edf7; --muted:#8da2c0; --accent:#4f8cff; --accent2:#22d3ee; --violet:#a78bfa;
    --green:#34d399; --amber:#fbbf24; --red:#f87171;
  }
  * { box-sizing:border-box; }
  body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:var(--ink); min-height:100vh;
         background:
           radial-gradient(1200px 600px at 10% -10%, #1b2c52 0%, transparent 55%),
           radial-gradient(900px 500px at 110% 10%, #143042 0%, transparent 50%),
           var(--bg); }
  header { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:16px 28px;
           background:rgba(8,14,28,.7); -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px);
           border-bottom:1px solid var(--line); position:sticky; top:0; z-index:6; flex-wrap:wrap; }
  header h1 { font-size:18px; margin:0; font-weight:800; letter-spacing:.02em; }
  .nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .nav a, .settings select { color:#bcd0ef; text-decoration:none; font-size:.9rem; background:#16213a; border:1px solid var(--line); padding:8px 13px; border-radius:10px; }
  .nav a:hover { border-color:var(--accent); }
  .nav a.active { background:linear-gradient(135deg,var(--accent),var(--violet)); color:#fff; font-weight:700; border:0; }
  .nav a.admin-link { background:rgba(124,58,237,.18); border-color:rgba(124,58,237,.5); color:#c4b5fd; font-weight:700; }
  .settings select { cursor:pointer; } .settings option { color:#000; }
  /* Barre d'actions FIXE : reste visible en haut pendant le défilement. */
  .toolbar { position:sticky; top:0; z-index:30; display:flex; gap:10px; align-items:center; padding:12px 18px; max-width:1100px; margin:8px auto 0; flex-wrap:wrap; background:rgba(13,22,42,.96); -webkit-backdrop-filter:blur(8px); backdrop-filter:blur(8px); border:1px solid var(--line); border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.4); }
  .toolbar label { font-size:.9rem; color:#cbd8ef; display:flex; gap:7px; align-items:center; cursor:pointer; }
  .toolbar .spacer { flex:1; }
  .bulk-btn { border:0; padding:11px 18px; border-radius:11px; font-size:.9rem; font-weight:700; cursor:pointer; color:#fff; transition:filter .15s; }
  .bulk-btn:hover { filter:brightness(1.12); }
  .b-trash { background:linear-gradient(135deg,#ef4444,#b91c1c); } .b-restore { background:linear-gradient(135deg,var(--green),#059669); } .b-purge { background:linear-gradient(135deg,#7c2d12,#431407); }
  .b-up { background:linear-gradient(135deg,#475569,#334155); } .b-app { background:linear-gradient(135deg,#2563eb,#1565C0); }
  #upLog { max-width:1100px; margin:8px auto 0; padding:0 22px; color:#8da2c0; font-size:.9rem; }
  .srcfilter { display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:1100px; margin:14px auto 0; padding:0 22px; }
  .srcfilter-lbl { color:#8da2c0; font-size:.9rem; }
  .srcbtn { text-decoration:none; color:#cbd8ef; background:rgba(34,48,79,.6); border:1px solid var(--line); padding:7px 13px; border-radius:999px; font-size:.88rem; }
  .srcbtn:hover { border-color:var(--accent); }
  .srcbtn.on { background:var(--accent); color:#fff; border-color:var(--accent); font-weight:700; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:18px; padding:18px 22px 22px; max-width:1100px; margin:0 auto; }
  .card { background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line); border-radius:16px; overflow:hidden; transition:transform .18s, border-color .18s; position:relative; }
  .card:hover { transform:translateY(-4px); border-color:var(--accent); }
  .pick { position:absolute; top:10px; right:10px; width:26px; height:26px; z-index:3; cursor:pointer; accent-color:var(--accent); }
  .thumb { position:relative; display:block; }
  .thumb img { width:100%; height:240px; object-fit:cover; display:block; background:#0a1124; transition:transform .3s; }
  .card:hover .thumb img { transform:scale(1.04); }
  /* Fichiers non-image (audio/doc/vidéo) : icône centrée, non rognée. */
  .thumb.fileicon { background:#0a1124; }
  .thumb.fileicon img { object-fit:contain; padding:54px 40px; }
  .type-tag { display:inline-block; font-size:11px; font-weight:700; color:#cbd8ef; background:rgba(34,48,79,.7);
              border:1px solid var(--line); padding:3px 9px; border-radius:999px; margin-bottom:8px; }
  .size { font-size:12px; color:var(--muted); }
  .date { position:absolute; left:10px; bottom:10px; background:rgba(8,14,28,.75); color:#fff; font-size:12px; font-weight:600; padding:6px 11px; border-radius:999px; }
  /* Origine du fichier : cadre coloré + pastille (bleu = téléphone, orange = web). */
  .card.src-phone, .card.src-web, .card.src-computer { border-width:3px; }
  .card.src-phone,    .card.src-phone:hover    { border-color:#1565C0; }
  .card.src-web,      .card.src-web:hover       { border-color:#E8772E; }
  .card.src-computer, .card.src-computer:hover  { border-color:#16a34a; }
  .src-badge { position:absolute; top:10px; left:10px; z-index:3; background:rgba(8,14,28,.75); border-radius:999px; padding:3px 7px; font-size:14px; line-height:1; }
  /* Pastille ▶ centrée sur les vidéos (indique la lecture en ligne). */
  .play-badge { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); z-index:3; pointer-events:none;
                width:54px; height:54px; border-radius:50%; background:rgba(8,14,28,.6); border:2px solid rgba(255,255,255,.85);
                color:#fff; font-size:22px; display:flex; align-items:center; justify-content:center; padding-left:4px; }
  .meta { padding:11px 14px; }
  .name { font-size:12px; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom:8px; }
  .warn { font-size:12px; color:var(--amber); }
  .pager { display:flex; gap:10px; justify-content:center; align-items:center; padding:22px; flex-wrap:wrap; }
  .pager a, .pager span { padding:10px 18px; border-radius:11px; background:#16213a; border:1px solid var(--line); color:var(--ink); text-decoration:none; font-size:.9rem; }
  .pager .cur { background:linear-gradient(135deg,var(--accent),var(--violet)); font-weight:700; border:0; }
  .empty { text-align:center; padding:70px 20px; color:var(--muted); font-size:16px; line-height:1.7; }
</style>
<script>
  function toggleAll(cb){ document.querySelectorAll('input[name="ids[]"]').forEach(x => x.checked = cb.checked); }
  function bulk(msg){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo.'); return false; }
    return confirm(msg.replace('{n}', n));
  }
  // Envoi direct depuis la galerie (boutons Ordinateur / Application), sans changer de page.
  function wireUpload(input, source){
    if (!input) return;
    input.addEventListener('change', async () => {
      const files = Array.from(input.files); if (!files.length) return;
      const log = document.getElementById('upLog');
      let ok = 0, dup = 0, ko = 0;
      for (let i = 0; i < files.length; i++){
        const fd = new FormData();
        fd.append('photo', files[i], files[i].name);
        fd.append('taken_at', String(files[i].lastModified || 0));
        fd.append('source', source);
        try {
          const r = await fetch('../api/upload.php', { method:'POST', body:fd, credentials:'same-origin' });
          const j = await r.json();
          if (j.ok && j.duplicate) dup++; else if (j.ok) ok++; else ko++;
        } catch (e) { ko++; }
        log.textContent = `Envoi… ${i+1}/${files.length}  (✅ ${ok} · ⏭️ ${dup} · ❌ ${ko})`;
      }
      log.textContent = `Terminé : ✅ ${ok} envoyé(s) · ⏭️ ${dup} déjà présent(s) · ❌ ${ko} échec(s). Rechargement…`;
      input.value = '';
      setTimeout(() => location.reload(), 1200);
    });
  }
  wireUpload(document.getElementById('upDesk'), 'computer');
  wireUpload(document.getElementById('upApp'), 'web');
</script>
</head>
<body>
  <header>
    <h1><?= $inTrash ? '🗑️ Corbeille' : '📸 ' . htmlspecialchars($uname) ?> — <?= $total ?> photo(s)</h1>
    <div class="nav">
      <a href="gallery.php" class="<?= $inTrash ? '' : 'active' ?>">Galerie</a>
      <a href="dualcam.php">🎞️ DualCam</a>
      <a href="remote.php">🎬 Télécommande</a>
      <a href="upload_web.php">➕ Ajouter</a>
      <a href="gallery.php?view=corbeille" class="<?= $inTrash ? 'active' : '' ?>">Corbeille (<?= $trashCount ?>)</a>
      <form class="settings" method="get" style="margin:0;display:inline;">
        <?php if ($inTrash): ?><input type="hidden" name="view" value="corbeille"><?php endif; ?>
        <?php if ($srcFilter !== ''): ?><input type="hidden" name="src" value="<?= htmlspecialchars($srcFilter) ?>"><?php endif; ?>
        <?php if ($typeFilter !== ''): ?><input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>"><?php endif; ?>
        <?php if ($sortKey !== 'date_desc'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sortKey) ?>"><?php endif; ?>
        <select name="perpage" onchange="this.form.submit()" title="Photos par page">
          <?php foreach (Photos::PER_PAGE as $opt): ?>
            <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?> / page</option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php if ($isAdmin): ?><a href="admin.php" class="admin-link">🛠️ Admin</a><?php endif; ?>
      <a href="gallery.php?logout=1">Déconnexion</a>
    </div>
  </header>

  <?php
    // Bouton « Afficher » : filtre la galerie selon l'origine (téléphone / ordinateur / web).
    $qsBase  = 'gallery.php?perpage=' . $perPage . ($inTrash ? '&view=corbeille' : '');
    // Suffixes pour conserver les autres filtres/tri dans chaque lien.
    $keepType = $typeFilter !== '' ? '&type=' . $typeFilter : '';
    $keepSrc  = $srcFilter !== '' ? '&src=' . $srcFilter : '';
    $keepSort = $sortKey !== 'date_desc' ? '&sort=' . $sortKey : '';
    $srcOpts = ['' => 'Tout', 'phone' => '📱 Téléphone', 'computer' => '💻 Ordinateur', 'web' => '🌐 Web'];
    $typeOpts = ['' => 'Tous', 'photo' => '🖼️ Photos', 'video' => '🎬 Vidéos', 'audio' => '🎵 Musique', 'document' => '📄 Documents', 'other' => '🗂️ Autres'];
    $sortOpts = ['date_desc' => 'Date (récent)', 'date_asc' => 'Date (ancien)', 'name_asc' => 'Nom (A→Z)', 'name_desc' => 'Nom (Z→A)', 'size_desc' => 'Taille (gros)', 'size_asc' => 'Taille (petit)', 'type' => 'Type de fichier'];
  ?>
  <div class="srcfilter">
    <span class="srcfilter-lbl">Afficher :</span>
    <?php foreach ($srcOpts as $val => $label):
        $href = $qsBase . ($val !== '' ? '&src=' . $val : '') . $keepType . $keepSort;
        $on = ($srcFilter === $val) ? ' on' : ''; ?>
      <a class="srcbtn<?= $on ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="srcfilter">
    <span class="srcfilter-lbl">Type :</span>
    <?php foreach ($typeOpts as $val => $label):
        $href = $qsBase . ($val !== '' ? '&type=' . $val : '') . $keepSrc . $keepSort;
        $on = ($typeFilter === $val) ? ' on' : ''; ?>
      <a class="srcbtn<?= $on ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <?php if (!$inTrash): ?>
      <span style="flex:1"></span>
      <form method="get" style="margin:0;display:flex;gap:7px;align-items:center;">
        <input type="hidden" name="perpage" value="<?= $perPage ?>">
        <?php if ($srcFilter !== ''): ?><input type="hidden" name="src" value="<?= htmlspecialchars($srcFilter) ?>"><?php endif; ?>
        <?php if ($typeFilter !== ''): ?><input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>"><?php endif; ?>
        <span class="srcfilter-lbl">Trier :</span>
        <select name="sort" onchange="this.form.submit()" title="Trier par"
                style="color:#bcd0ef;background:#16213a;border:1px solid var(--line);padding:7px 11px;border-radius:10px;cursor:pointer;">
          <?php foreach ($sortOpts as $val => $label): ?>
            <option value="<?= $val ?>" <?= $val === $sortKey ? 'selected' : '' ?> style="color:#000;"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($total === 0): ?>
    <div class="empty"><?php
        if ($inTrash) echo 'La corbeille est vide ♻️';
        elseif ($typeFilter !== '' || $srcFilter !== '') echo 'Aucun fichier pour ce filtre.<br><a href="gallery.php" style="color:#8ab4ff;">Réinitialiser les filtres</a>';
        else echo "Aucune photo pour l'instant.<br>Lance une synchro depuis l'app 📲";
    ?></div>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="p" value="<?= $page ?>">
      <?php if ($inTrash): ?><input type="hidden" name="view" value="corbeille"><?php endif; ?>
      <?php if ($srcFilter !== ''): ?><input type="hidden" name="src" value="<?= htmlspecialchars($srcFilter) ?>"><?php endif; ?>
      <?php if ($typeFilter !== ''): ?><input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>"><?php endif; ?>
      <?php if ($sortKey !== 'date_desc'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sortKey) ?>"><?php endif; ?>
      <div class="toolbar">
        <label><input type="checkbox" onclick="toggleAll(this)"> Tout sélectionner</label>
        <span class="spacer"></span>
        <?php if ($inTrash): ?>
          <button class="bulk-btn b-restore" type="submit" name="action" value="restore" onclick="return bulk('Restaurer {n} photo(s) ?')">♻️ Restaurer la sélection</button>
          <button class="bulk-btn b-purge" type="submit" name="action" value="purge" onclick="return bulk('Supprimer DÉFINITIVEMENT {n} photo(s) ?')">❌ Supprimer définitivement</button>
        <?php else: ?>
          <button type="button" class="bulk-btn b-up" onclick="document.getElementById('upDesk').click()">📁 Ordinateur</button>
          <button type="button" class="bulk-btn b-app" onclick="document.getElementById('upApp').click()">📱 Application</button>
          <button class="bulk-btn b-trash" type="submit" name="action" value="trash" onclick="return bulk('Mettre {n} photo(s) à la corbeille ?')">🗑 Mettre la sélection à la corbeille</button>
          <input id="upDesk" type="file" accept="*/*" multiple style="display:none">
          <input id="upApp" type="file" accept="image/*,video/*" multiple style="display:none">
        <?php endif; ?>
      </div>
      <?php if (!$inTrash): ?><div id="upLog"></div><?php endif; ?>
      <div class="grid">
        <?php foreach ($rows as $r): $id = (int) $r['id'];
              $src = $r['source'] ?? 'phone';
              $srcCls   = ['phone'=>'src-phone','computer'=>'src-computer','web'=>'src-web'][$src] ?? 'src-phone';
              $srcIcon  = ['phone'=>'📱','computer'=>'💻','web'=>'🌐'][$src] ?? '📱';
              $srcTitle = ['phone'=>'Envoyé depuis le téléphone','computer'=>'Envoyé depuis l\'ordinateur','web'=>'Ajouté depuis le web'][$src] ?? 'Téléphone';
              // Catégorie + taille pour soigner l'affichage des fichiers non-image.
              $cat   = Photos::categoryOf($r['original_name'], (string) $r['stored_path']);
              $isImg = ($cat === 'photo');
              $size  = Photos::humanSize((int) ($r['size_bytes'] ?? 0));
              // Image / vidéo / audio : lecture en ligne (view.php). Documents : téléchargement.
              $playable = in_array($cat, ['photo', 'video', 'audio'], true);
              $href     = $playable ? 'view.php?id=' . $id : '../api/media.php?id=' . $id;
              $openAttr = $playable ? 'target="_blank"' : 'download="' . htmlspecialchars($r['original_name']) . '"'; ?>
          <div class="card <?= $srcCls ?>">
            <input class="pick" type="checkbox" name="ids[]" value="<?= $id ?>" title="Sélectionner">
            <span class="src-badge" title="<?= htmlspecialchars($srcTitle) ?>"><?= $srcIcon ?></span>
            <?php if ($cat === 'video'): ?><span class="play-badge">▶</span><?php endif; ?>
            <a class="thumb<?= $isImg ? '' : ' fileicon' ?>" href="<?= $href ?>" <?= $openAttr ?> title="<?= htmlspecialchars($r['original_name']) ?>">
              <img loading="lazy" src="../api/media.php?id=<?= $id ?>&amp;thumb=1" alt="">
              <span class="date"><?= htmlspecialchars(Photos::frDate($r['taken_at'] ?: $r['uploaded_at'])) ?></span>
            </a>
            <div class="meta">
              <span class="type-tag"><?= Photos::categoryLabel($cat) ?></span>
              <div class="name"><?= htmlspecialchars($r['original_name']) ?></div>
              <?php if ($size !== ''): ?><div class="size"><?= $size ?></div><?php endif; ?>
              <?php if ($inTrash): $left = max(0, Photos::TRASH_DAYS - (int) floor((time() - strtotime($r['deleted_at'])) / 86400)); ?>
                <div class="warn">⏳ Suppression définitive dans <?= $left ?> jour(s)</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </form>
    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php if ($page > 1): ?><a href="?p=<?= $page - 1 ?><?= $viewQs ?>">‹ Précédent</a><?php endif; ?>
        <span class="cur">Page <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="?p=<?= $page + 1 ?><?= $viewQs ?>">Suivant ›</a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
