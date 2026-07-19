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

// ---- Réglage nb par page (nombre libre, borné entre 1 et 500) ----
if ($uid && isset($_GET['perpage'])) {
    $pp = max(1, min(500, (int) $_GET['perpage']));
    setcookie('photosync_perpage', (string) $pp, time() + 31536000, '/');
    $_COOKIE['photosync_perpage'] = (string) $pp;
}
$perPage = isset($_COOKIE['photosync_perpage']) ? (int) $_COOKIE['photosync_perpage'] : 20;
$perPage = max(1, min(500, $perPage));

// ---- Actions (connecté) ----
if ($uid) {
    Photos::purgeOldTrash($uid);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Réglage du mot de passe de l'album masqué (par compte).
        if ($_POST['action'] === 'set_hidden_pass') {
            $np = (string) ($_POST['new_hidden'] ?? '');
            if (strlen($np) >= 4) {
                Db::pdo()->prepare('UPDATE ' . TBL_USERS . ' SET hidden_pass_hash = ? WHERE id = ?')
                         ->execute([password_hash($np, PASSWORD_DEFAULT), $uid]);
            }
            header('Location: gallery.php?view=cache'); exit;
        }
        if ($_POST['action'] === 'use_account_pass') {
            Db::pdo()->prepare('UPDATE ' . TBL_USERS . ' SET hidden_pass_hash = NULL WHERE id = ?')->execute([$uid]);
            header('Location: gallery.php?view=cache'); exit;
        }
        // --- Albums (dossiers virtuels partageables) ---
        if ($_POST['action'] === 'add_to_album') {
            $sel = array_map('intval', (array) ($_POST['ids'] ?? []));
            $newName = trim($_POST['album_new'] ?? '');
            $albumId = (int) ($_POST['album_id'] ?? 0);
            if ($newName !== '') $albumId = Albums::create($uid, $newName);
            if ($albumId > 0) Albums::addPhotos($albumId, $uid, $sel);
            header('Location: gallery.php?view=albums'); exit;
        }
        if ($_POST['action'] === 'album_setpass') {
            Albums::setPassword((int) ($_POST['album_id'] ?? 0), $uid, (string) ($_POST['album_pass'] ?? ''));
            header('Location: gallery.php?view=albums'); exit;
        }
        if ($_POST['action'] === 'album_clearpass') {
            Albums::setPassword((int) ($_POST['album_id'] ?? 0), $uid, null);
            header('Location: gallery.php?view=albums'); exit;
        }
        if ($_POST['action'] === 'album_delete') {
            Albums::delete((int) ($_POST['album_id'] ?? 0), $uid);
            header('Location: gallery.php?view=albums'); exit;
        }
        $ids = [];
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            foreach ($_POST['ids'] as $v) { $i = (int) $v; if ($i > 0) $ids[] = $i; }
        } elseif (isset($_POST['id'])) { $i = (int) $_POST['id']; if ($i > 0) $ids[] = $i; }
        foreach ($ids as $i) {
            switch ($_POST['action']) {
                case 'trash':   Photos::trash($i, $uid);           break;
                case 'restore': Photos::restore($i, $uid);         break;
                case 'purge':   Photos::deleteForever($i, $uid);   break;
                case 'hide':    Photos::setHidden($i, $uid, true);  break;
                case 'unhide':  Photos::setHidden($i, $uid, false); break;
            }
        }
        $v = $_POST['view'] ?? '';
        $view = ($v === 'corbeille' || $v === 'cache') ? '&view=' . $v : '';
        header('Location: gallery.php?p=' . max(1, (int) ($_POST['p'] ?? 1)) . $view);
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
            body { font-family:system-ui,sans-serif; background:#0b1220; color:#e2e8f0; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
            .card { background:#16213a; padding:28px; border-radius:16px; width:300px; box-shadow:0 10px 30px rgba(0,0,0,.5); }
            h1 { font-size:20px; margin:0 0 4px; } p.sub { color:#8aa0bd; font-size:13px; margin:0 0 20px; }
            input { width:100%; box-sizing:border-box; padding:12px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:15px; margin-top:10px; }
            button { width:100%; margin-top:14px; padding:12px; border:0; border-radius:10px; background:#1565C0; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
            .err { color:#f87171; font-size:13px; margin-top:10px; }
            .hint { color:#64748b; font-size:12px; margin-top:14px; text-align:center; }
        </style></head>
    <body><form class="card" method="post">
        <h1>🔒 PhotoSync</h1><p class="sub">Connexion à ton compte</p>
        <input type="text" name="username" placeholder="Identifiant" autofocus required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="hint">Pas encore de compte ? <a href="register.php" style="color:#93c5fd;text-decoration:none;">Créer un compte</a></div>
    </form></body></html>
    <?php
    exit;
}

// ---- Album MASQUÉ : déverrouillage par mot de passe ----
$inHidden = ($_GET['view'] ?? '') === 'cache';
$hiddenErr = '';
if ($inHidden) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hidden_pass'])) {
        // Référence : mot de passe spécifique de l'album si défini, sinon mot de passe du compte.
        $urow = Db::pdo()->prepare('SELECT pass_hash, hidden_pass_hash FROM ' . TBL_USERS . ' WHERE id = ?');
        $urow->execute([$uid]);
        $u = $urow->fetch(PDO::FETCH_ASSOC) ?: [];
        $ref = !empty($u['hidden_pass_hash']) ? $u['hidden_pass_hash'] : ($u['pass_hash'] ?? '');
        if ($ref !== '' && password_verify((string) $_POST['hidden_pass'], $ref)) {
            $_SESSION['hidden_ok'] = true;
            header('Location: gallery.php?view=cache');
            exit;
        }
        $hiddenErr = 'Mot de passe incorrect.';
    }
    if (isset($_GET['lock'])) { unset($_SESSION['hidden_ok']); header('Location: gallery.php'); exit; }

    if (empty($_SESSION['hidden_ok'])) {
        ?>
        <!doctype html><html lang="fr"><head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="../favicon.svg" type="image/svg+xml">
        <title>PhotoSync — Album masqué</title>
        <style>
            body { font-family:system-ui,sans-serif; background:#0b1220; color:#e2e8f0; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
            .card { background:#16213a; padding:28px; border-radius:16px; width:300px; box-shadow:0 10px 30px rgba(0,0,0,.5); }
            h1 { font-size:20px; margin:0 0 4px; } p.sub { color:#8aa0bd; font-size:13px; margin:0 0 20px; }
            input { width:100%; box-sizing:border-box; padding:12px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:15px; margin-top:10px; }
            button { width:100%; margin-top:14px; padding:12px; border:0; border-radius:10px; background:#7c3aed; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
            .err { color:#f87171; font-size:13px; margin-top:10px; }
            .hint { color:#64748b; font-size:12px; margin-top:14px; text-align:center; }
            .hint a { color:#93c5fd; text-decoration:none; }
        </style></head>
        <body><form class="card" method="post">
            <h1>🔒 Album masqué</h1><p class="sub">Entre ton mot de passe de connexion (ou le mot de passe spécifique si tu en as défini un).</p>
            <input type="password" name="hidden_pass" placeholder="Mot de passe" autofocus required>
            <button type="submit">Déverrouiller</button>
            <?php if (!empty($hiddenErr)): ?><div class="err"><?= htmlspecialchars($hiddenErr) ?></div><?php endif; ?>
            <div class="hint"><a href="gallery.php">← Retour à la galerie</a></div>
        </form></body></html>
        <?php
        exit;
    }
}

// ---- Données (scopées au compte) ----
$inTrash = ($_GET['view'] ?? '') === 'corbeille';
$inAlbums = ($_GET['view'] ?? '') === 'albums';
$albums = Albums::forUser($uid); // pour le menu « ajouter à un album » et la vue Albums
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$shareBase = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\') . '/share.php?a=';

// Filtre par date (calendrier) : du / au (format AAAA-MM-JJ).
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = '';

$dateWhere = '';
$dateParams = [];
if ($from !== '') { $dateWhere .= ' AND COALESCE(taken_at, uploaded_at) >= :from'; $dateParams[':from'] = $from . ' 00:00:00'; }
if ($to   !== '') { $dateWhere .= ' AND COALESCE(taken_at, uploaded_at) <= :to';   $dateParams[':to']   = $to . ' 23:59:59'; }

// Filtre d'affichage par origine (bouton « Afficher ») : 📱 phone / 💻 computer / 🌐 web. Vide = tout.
$srcFilter = in_array($_GET['src'] ?? '', ['phone', 'computer', 'web'], true) ? $_GET['src'] : '';
if ($srcFilter !== '') { $dateWhere .= ' AND source = :src'; $dateParams[':src'] = $srcFilter; }

// État selon la vue : masquée / corbeille / normale (les photos masquées n'apparaissent
// que dans la vue masquée ; elles sont exclues partout ailleurs).
$baseState = $inHidden
    ? 'hidden = 1 AND deleted_at IS NULL'
    : ($inTrash ? 'deleted_at IS NOT NULL AND hidden = 0' : 'deleted_at IS NULL AND hidden = 0');
$where = 'user_id = :uid AND ' . $baseState . $dateWhere;
$order = $inTrash ? 'deleted_at DESC' : 'COALESCE(taken_at, uploaded_at) DESC';

$viewParam = $inHidden ? 'cache' : ($inTrash ? 'corbeille' : '');

// Query string conservant la vue + le filtre date (pour pagination, perpage…).
$filterQs = ($viewParam !== '' ? '&view=' . $viewParam : '')
          . ($from !== '' ? '&from=' . urlencode($from) : '')
          . ($to !== '' ? '&to=' . urlencode($to) : '')
          . ($srcFilter !== '' ? '&src=' . $srcFilter : '');

$page = max(1, (int) ($_GET['p'] ?? 1));
$c = Db::pdo()->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " WHERE $where");
$c->execute(array_merge([':uid' => $uid], $dateParams));
$total = (int) $c->fetch(PDO::FETCH_ASSOC)['c'];
$tc = Db::pdo()->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " WHERE user_id = ? AND deleted_at IS NOT NULL"); $tc->execute([$uid]);
$trashCount = (int) $tc->fetch(PDO::FETCH_ASSOC)['c'];
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = Db::pdo()->prepare(
    "SELECT id, original_name, taken_at, uploaded_at, deleted_at, stored_path, source
     FROM " . TBL_PHOTOS . " WHERE $where ORDER BY $order LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
foreach ($dateParams as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
// On exclut (et on efface) les entrées dont le fichier a disparu du disque.
$rows = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function ($r) use ($uid) {
    if (Photos::fileExists($r)) return true;
    Photos::deleteForever((int) $r['id'], $uid);
    return false;
}));
$viewQs = $filterQs; // conserve vue + filtre date dans la pagination
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title>PhotoSync — <?= $inTrash ? 'Corbeille' : 'Galerie' ?></title>
<style>
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,sans-serif; margin:0; background:#0b1220; color:#e2e8f0; }
  header { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:16px 20px;
           background:linear-gradient(135deg,#1565C0,#0b3a78); position:sticky; top:0; z-index:6; box-shadow:0 4px 20px rgba(0,0,0,.4); flex-wrap:wrap; }
  header h1 { font-size:18px; margin:0; font-weight:700; }
  .nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .nav a, .settings select { color:#fff; text-decoration:none; font-size:13px; background:rgba(255,255,255,.15); padding:8px 12px; border-radius:20px; border:0; }
  .nav a.active { background:#fff; color:#1565C0; font-weight:700; }
  .settings select { cursor:pointer; } .settings option { color:#000; }
  /* Barre d'actions FIXE : reste visible en haut pendant le défilement. */
  .toolbar { position:sticky; top:0; z-index:30; display:flex; gap:10px; align-items:center; padding:12px 18px; max-width:1100px; margin:8px auto 0; flex-wrap:wrap; background:rgba(13,22,42,.96); -webkit-backdrop-filter:blur(8px); backdrop-filter:blur(8px); border:1px solid #22304f; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.4); }
  .toolbar label { font-size:14px; color:#cbd5e1; display:flex; gap:7px; align-items:center; cursor:pointer; }
  .toolbar .spacer { flex:1; }
  .bulk-btn { border:0; padding:9px 14px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; color:#fff; }
  .b-trash { background:#ef4444; } .b-restore { background:#16a34a; } .b-purge { background:#7c2d12; }
  .b-up { background:#334155; } .b-app { background:#1565C0; }
  #upLog { max-width:1100px; margin:8px auto 0; padding:0 22px; color:#8da2c0; font-size:.9rem; }
  .srcfilter { display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:1100px; margin:14px auto 0; padding:0 22px; }
  .srcfilter-lbl { color:#8aa0bd; font-size:.9rem; }
  .srcbtn { text-decoration:none; color:#cbd8ef; background:rgba(34,48,79,.6); border:1px solid #22304f; padding:7px 13px; border-radius:999px; font-size:.88rem; }
  .srcbtn:hover { border-color:#1565C0; }
  .srcbtn.on { background:#1565C0; color:#fff; border-color:#1565C0; font-weight:700; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:18px; padding:0 18px 18px; max-width:1100px; margin:0 auto; }
  .card { background:#16213a; border-radius:16px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,.35); transition:transform .18s; position:relative; }
  .card:hover { transform:translateY(-4px); }
  .pick { position:absolute; top:10px; right:10px; width:26px; height:26px; z-index:3; cursor:pointer; accent-color:#1565C0; }
  .thumb { position:relative; display:block; }
  .thumb img { width:100%; height:240px; object-fit:cover; display:block; background:#0f172a; transition:transform .3s; }
  .card:hover .thumb img { transform:scale(1.04); }
  .date { position:absolute; left:10px; bottom:10px; background:rgba(0,0,0,.6); color:#fff; font-size:12px; font-weight:600; padding:6px 11px; border-radius:20px; }
  /* Origine du fichier : cadre coloré + pastille (bleu = téléphone, orange = web). */
  .card.src-phone    { border:3px solid #1565C0; }
  .card.src-web      { border:3px solid #E8772E; }
  .card.src-computer { border:3px solid #16a34a; }
  .src-badge { position:absolute; top:10px; left:10px; z-index:3; background:rgba(0,0,0,.6); border-radius:20px; padding:3px 7px; font-size:14px; line-height:1; }
  .meta { padding:11px 13px; }
  .name { font-size:12px; color:#8aa0bd; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom:8px; }
  .warn { font-size:12px; color:#fbbf24; }
  .pager { display:flex; gap:10px; justify-content:center; align-items:center; padding:22px; flex-wrap:wrap; }
  .pager a, .pager span { padding:10px 18px; border-radius:12px; background:#16213a; color:#e2e8f0; text-decoration:none; font-size:14px; }
  .pager .cur { background:#1565C0; font-weight:700; }
  .empty { text-align:center; padding:70px 20px; color:#8aa0bd; font-size:16px; line-height:1.7; }
</style>
<script>
  function toggleAll(cb){ document.querySelectorAll('input[name="ids[]"]').forEach(x => x.checked = cb.checked); }
  function bulk(msg){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo.'); return false; }
    return confirm(msg.replace('{n}', n));
  }
  function haveSelection(){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo.'); return false; }
    return true;
  }
  function cp(id){ var e=document.getElementById(id); e.select(); try{navigator.clipboard.writeText(e.value);}catch(x){document.execCommand('copy');} alert('Lien copié !'); }
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
        if (log) log.textContent = `Envoi… ${i+1}/${files.length}  (✅ ${ok} · ⏭️ ${dup} · ❌ ${ko})`;
      }
      if (log) log.textContent = `Terminé : ✅ ${ok} envoyé(s) · ⏭️ ${dup} déjà présent(s) · ❌ ${ko} échec(s). Rechargement…`;
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
    <h1><?= $inHidden ? '🔒 Masquées' : ($inTrash ? '🗑️ Corbeille' : '📸 ' . htmlspecialchars($uname)) ?> — <?= $total ?> photo(s)</h1>
    <div class="nav">
      <a href="gallery.php" class="<?= (!$inTrash && !$inHidden && !$inAlbums) ? 'active' : '' ?>">Galerie</a>
      <a href="upload_web.php">➕ Ajouter</a>
      <?php if (!$inTrash && !$inHidden && $total > 0): ?>
        <a href="download.php?all=1" onclick="return confirm('Télécharger TOUTES tes photos en .zip ? Cela peut être long.')">⬇️ Tout télécharger</a>
      <?php endif; ?>
      <a href="gallery.php?view=corbeille" class="<?= $inTrash ? 'active' : '' ?>">Corbeille (<?= $trashCount ?>)</a>
      <a href="gallery.php?view=cache" class="<?= $inHidden ? 'active' : '' ?>">🔒 Masquées</a>
      <a href="gallery.php?view=albums" class="<?= $inAlbums ? 'active' : '' ?>">📁 Albums</a>
      <?php if ($inHidden): ?><a href="gallery.php?view=cache&lock=1">🔓 Verrouiller</a><?php endif; ?>
      <form class="settings" method="get" style="margin:0;display:inline-flex;gap:6px;align-items:center;" title="Nombre d'images affichées par page">
        <?php if ($viewParam !== ''): ?><input type="hidden" name="view" value="<?= $viewParam ?>"><?php endif; ?>
        <?php if ($from !== ''): ?><input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>"><?php endif; ?>
        <?php if ($to !== ''): ?><input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>"><?php endif; ?>
        <span style="color:#fff;font-size:13px;">🖼️ par page</span>
        <input type="number" name="perpage" min="1" max="500" value="<?= $perPage ?>"
               style="width:64px;padding:6px 8px;border-radius:8px;border:1px solid #334155;background:#0b1220;color:#fff;font-size:14px;">
        <button type="submit" style="background:#fff;color:#1565C0;border:0;border-radius:8px;padding:6px 12px;font-weight:700;cursor:pointer;">OK</button>
      </form>
      <a href="admin.php">🛠️ Admin</a>
      <a href="gallery.php?logout=1">Déconnexion</a>
    </div>
  </header>

  <?php if (!$inAlbums): ?>
  <?php
    // Bouton « Afficher » : filtre la galerie selon l'origine (téléphone / ordinateur / web).
    $qsBase  = 'gallery.php?perpage=' . $perPage
             . ($viewParam !== '' ? '&view=' . $viewParam : '')
             . ($from !== '' ? '&from=' . urlencode($from) : '')
             . ($to !== '' ? '&to=' . urlencode($to) : '');
    $srcOpts = ['' => 'Tout', 'phone' => '📱 Téléphone', 'computer' => '💻 Ordinateur', 'web' => '🌐 Web'];
  ?>
  <div class="srcfilter">
    <span class="srcfilter-lbl">Afficher :</span>
    <?php foreach ($srcOpts as $val => $label):
        $href = $qsBase . ($val !== '' ? '&src=' . $val : '');
        $on = ($srcFilter === $val) ? ' on' : ''; ?>
      <a class="srcbtn<?= $on ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($inAlbums): ?>
    <div style="max-width:1000px;margin:0 auto;padding:18px;">
      <h2 style="font-size:18px;margin:0 0 6px;">📁 Mes albums partageables</h2>
      <p style="color:#8aa0bd;font-size:14px;margin:0 0 12px;">Crée un album, ajoute des photos depuis la galerie (bouton « 📁 Ajouter à l'album »), puis partage le lien — public ou protégé par mot de passe.</p>

      <form method="post" style="margin:0 0 18px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="action" value="add_to_album">
        <input type="text" name="album_new" placeholder="Nom du nouvel album" required style="padding:9px 11px;border-radius:9px;border:1px solid #334155;background:#0b1220;color:#fff;">
        <button type="submit" style="background:#1565C0;color:#fff;border:0;border-radius:9px;padding:9px 14px;font-weight:600;cursor:pointer;">Créer un album</button>
      </form>

      <?php if (!$albums): ?>
        <div class="empty">Aucun album pour l'instant.</div>
      <?php else: foreach ($albums as $al): $u = $shareBase . $al['token']; ?>
        <div style="background:#16213a;border-radius:12px;padding:14px;margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
            <b><?= htmlspecialchars($al['name']) ?></b>
            <span style="color:#8aa0bd;font-size:13px;"><?= (int) $al['n'] ?> photo(s) · <?= !empty($al['pass_hash']) ? '🔒 protégé' : '🌐 public' ?></span>
          </div>
          <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
            <input id="u<?= (int) $al['id'] ?>" readonly value="<?= htmlspecialchars($u) ?>" style="flex:1;min-width:220px;padding:8px;border-radius:8px;border:1px solid #334155;background:#0b1220;color:#cbd5e1;font-size:13px;">
            <button type="button" onclick="cp('u<?= (int) $al['id'] ?>')" style="background:#0ea5e9;color:#fff;border:0;border-radius:8px;padding:8px 12px;cursor:pointer;">Copier le lien</button>
            <a href="<?= htmlspecialchars($u) ?>" target="_blank" style="background:#16a34a;color:#fff;border-radius:8px;padding:8px 12px;text-decoration:none;">Voir</a>
          </div>
          <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;align-items:center;">
            <form method="post" style="display:inline;margin:0;">
              <input type="hidden" name="action" value="album_setpass">
              <input type="hidden" name="album_id" value="<?= (int) $al['id'] ?>">
              <input type="password" name="album_pass" placeholder="mot de passe" style="padding:7px 9px;border-radius:8px;border:1px solid #334155;background:#0b1220;color:#fff;">
              <button type="submit" style="background:#7c3aed;color:#fff;border:0;border-radius:8px;padding:7px 12px;cursor:pointer;">Protéger</button>
            </form>
            <?php if (!empty($al['pass_hash'])): ?>
              <form method="post" style="display:inline;margin:0;">
                <input type="hidden" name="action" value="album_clearpass">
                <input type="hidden" name="album_id" value="<?= (int) $al['id'] ?>">
                <button type="submit" style="background:#334155;color:#fff;border:0;border-radius:8px;padding:7px 12px;cursor:pointer;">Rendre public</button>
              </form>
            <?php endif; ?>
            <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('Supprimer cet album ? (les photos ne sont pas supprimées)')">
              <input type="hidden" name="action" value="album_delete">
              <input type="hidden" name="album_id" value="<?= (int) $al['id'] ?>">
              <button type="submit" style="background:#7c2d12;color:#fff;border:0;border-radius:8px;padding:7px 12px;cursor:pointer;">Supprimer</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php else: ?>

  <form method="get" style="max-width:1100px;margin:12px auto 0;padding:0 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;color:#cbd5e1;font-size:14px;">
    <?php if ($viewParam !== ''): ?><input type="hidden" name="view" value="<?= $viewParam ?>"><?php endif; ?>
    <span>📅 Filtrer par date :</span>
    <label>du <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" style="background:#16213a;color:#fff;border:1px solid #334155;border-radius:8px;padding:7px 9px;"></label>
    <label>au <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" style="background:#16213a;color:#fff;border:1px solid #334155;border-radius:8px;padding:7px 9px;"></label>
    <button type="submit" style="background:#1565C0;color:#fff;border:0;border-radius:8px;padding:8px 14px;cursor:pointer;font-weight:600;">Filtrer</button>
    <?php if ($from !== '' || $to !== ''): ?>
      <a href="gallery.php<?= $viewParam !== '' ? '?view=' . $viewParam : '' ?>" style="color:#93c5fd;text-decoration:none;">✕ Réinitialiser</a>
    <?php endif; ?>
  </form>

  <?php if ($inHidden): ?>
    <div style="max-width:1100px;margin:8px auto 0;padding:0 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-size:13px;color:#9fb3cd;">
      <span>⚙️ Mot de passe de cet album :</span>
      <form method="post" style="display:inline;margin:0;">
        <input type="hidden" name="action" value="set_hidden_pass">
        <input type="hidden" name="view" value="cache">
        <input type="password" name="new_hidden" placeholder="mot de passe spécifique (4+)" required style="background:#16213a;color:#fff;border:1px solid #334155;border-radius:8px;padding:6px 9px;">
        <button type="submit" style="background:#7c3aed;color:#fff;border:0;border-radius:8px;padding:7px 12px;cursor:pointer;">Définir</button>
      </form>
      <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('Utiliser ton mot de passe de connexion pour cet album ?')">
        <input type="hidden" name="action" value="use_account_pass">
        <input type="hidden" name="view" value="cache">
        <button type="submit" style="background:#334155;color:#fff;border:0;border-radius:8px;padding:7px 12px;cursor:pointer;">Utiliser mon mot de passe de connexion</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($total === 0): ?>
    <div class="empty"><?php
      if ($from !== '' || $to !== '') echo 'Aucune photo sur cette période 📅';
      elseif ($inHidden) echo 'Aucune photo masquée. Sélectionne des photos dans la galerie puis « 🔒 Masquer ».';
      else echo $inTrash ? 'La corbeille est vide ♻️' : "Aucune photo pour l'instant.<br>Lance une synchro depuis l'app 📲";
    ?></div>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="p" value="<?= $page ?>">
      <?php if ($viewParam !== ''): ?><input type="hidden" name="view" value="<?= $viewParam ?>"><?php endif; ?>
      <div class="toolbar">
        <label><input type="checkbox" onclick="toggleAll(this)"> Tout sélectionner</label>
        <span class="spacer"></span>
        <?php if ($inTrash): ?>
          <button class="bulk-btn b-restore" type="submit" name="action" value="restore" onclick="return bulk('Restaurer {n} photo(s) ?')">♻️ Restaurer la sélection</button>
          <button class="bulk-btn b-purge" type="submit" name="action" value="purge" onclick="return bulk('Supprimer DÉFINITIVEMENT {n} photo(s) ?')">❌ Supprimer définitivement</button>
        <?php elseif ($inHidden): ?>
          <button class="bulk-btn" type="submit" formaction="download.php" formmethod="post" style="background:#0ea5e9" onclick="return haveSelection()">⬇️ Télécharger la sélection</button>
          <button class="bulk-btn" type="submit" name="action" value="unhide" style="background:#16a34a" onclick="return bulk('Ré-afficher {n} photo(s) dans la galerie ?')">👁 Démasquer la sélection</button>
          <button class="bulk-btn b-trash" type="submit" name="action" value="trash" onclick="return bulk('Mettre {n} photo(s) à la corbeille ?')">🗑 Corbeille</button>
        <?php else: ?>
          <button type="button" class="bulk-btn b-up" onclick="document.getElementById('upDesk').click()">📁 Ordinateur</button>
          <button type="button" class="bulk-btn b-app" onclick="document.getElementById('upApp').click()">📱 Application</button>
          <input id="upDesk" type="file" accept="*/*" multiple style="display:none">
          <input id="upApp" type="file" accept="image/*,video/*" multiple style="display:none">
          <?php if ($albums): ?>
            <select name="album_id" style="padding:8px 10px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#fff;">
              <option value="0">— choisir un album —</option>
              <?php foreach ($albums as $al): ?>
                <option value="<?= (int) $al['id'] ?>"><?= htmlspecialchars($al['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="bulk-btn" type="submit" name="action" value="add_to_album" style="background:#1565C0" onclick="return haveSelection()">📁 Ajouter à l'album</button>
          <?php endif; ?>
          <button class="bulk-btn" type="submit" formaction="download.php" formmethod="post" style="background:#0ea5e9" onclick="return haveSelection()">⬇️ Télécharger la sélection</button>
          <button class="bulk-btn" type="submit" name="action" value="hide" style="background:#7c3aed" onclick="return bulk('Masquer {n} photo(s) ? (elles iront dans l album protégé)')">🔒 Masquer la sélection</button>
          <button class="bulk-btn b-trash" type="submit" name="action" value="trash" onclick="return bulk('Mettre {n} photo(s) à la corbeille ?')">🗑 Mettre la sélection à la corbeille</button>
        <?php endif; ?>
      </div>
      <?php if (!$inTrash && !$inHidden): ?><div id="upLog"></div><?php endif; ?>
      <div class="grid">
        <?php foreach ($rows as $r): $id = (int) $r['id'];
              $src = $r['source'] ?? 'phone';
              $srcCls   = ['phone'=>'src-phone','computer'=>'src-computer','web'=>'src-web'][$src] ?? 'src-phone';
              $srcIcon  = ['phone'=>'📱','computer'=>'💻','web'=>'🌐'][$src] ?? '📱';
              $srcTitle = ['phone'=>'Envoyé depuis le téléphone','computer'=>'Envoyé depuis l\'ordinateur','web'=>'Ajouté depuis le web'][$src] ?? 'Téléphone'; ?>
          <div class="card <?= $srcCls ?>">
            <input class="pick" type="checkbox" name="ids[]" value="<?= $id ?>" title="Sélectionner">
            <span class="src-badge" title="<?= htmlspecialchars($srcTitle) ?>"><?= $srcIcon ?></span>
            <a class="thumb" href="../api/media.php?id=<?= $id ?>" target="_blank" title="<?= htmlspecialchars($r['original_name']) ?>">
              <img loading="lazy" src="../api/media.php?id=<?= $id ?>&amp;thumb=micro" alt="">
              <span class="date"><?= htmlspecialchars(Photos::frDate($r['taken_at'] ?: $r['uploaded_at'])) ?></span>
            </a>
            <div class="meta">
              <div class="name"><?= htmlspecialchars($r['original_name']) ?></div>
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
  <?php endif; /* total */ ?>
  <?php endif; /* inAlbums else */ ?>
</body>
</html>
