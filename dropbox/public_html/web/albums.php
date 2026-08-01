<?php
// === Albums partageables — création, contenu et réglages du lien ===
//   albums.php           -> liste des albums du compte
//   albums.php?id=12     -> contenu d'un album + panneau de partage
// Le lien de partage lui-même est servi par share.php (aucun compte requis).

require __DIR__ . '/../lib/bootstrap.php';

$sess  = Auth::webSession('albums.php');
$uid   = $sess['uid'];
$uname = $sess['uname'];

// Page réservée aux comptes connectés : sinon retour à la galerie (qui gère la connexion).
if (!$uid) { header('Location: gallery.php'); exit; }

Albums::ensureSchema();
$isAdmin = Auth::isAdmin((int) $uid);
$notice  = '';

// ---- Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $aid    = (int) ($_POST['album_id'] ?? 0);

    switch ($action) {
        case 'create':
            $newId = Albums::create((int) $uid, (string) ($_POST['name'] ?? ''));
            header('Location: albums.php?id=' . $newId . '&ok=cree');
            exit;

        case 'rename':
            Albums::rename($aid, (int) $uid, (string) ($_POST['name'] ?? ''));
            header('Location: albums.php?id=' . $aid . '&ok=renomme');
            exit;

        case 'setpass':
            Albums::setPassword($aid, (int) $uid, (string) ($_POST['pass'] ?? ''));
            header('Location: albums.php?id=' . $aid . '&ok=motdepasse');
            exit;

        case 'setexp':
            Albums::setExpiry($aid, (int) $uid, trim((string) ($_POST['expires'] ?? '')));
            header('Location: albums.php?id=' . $aid . '&ok=expiration');
            exit;

        case 'reset':
            Albums::resetToken($aid, (int) $uid);
            header('Location: albums.php?id=' . $aid . '&ok=nouveaulien');
            exit;

        case 'remove':
            Albums::removePhotos($aid, (int) $uid, Request::ids());
            header('Location: albums.php?id=' . $aid . '&ok=retire');
            exit;

        case 'delete':
            Albums::delete($aid, (int) $uid);
            header('Location: albums.php?ok=supprime');
            exit;
    }
}

$okMsgs = [
    'cree'        => 'Album créé — il ne reste plus qu\'à y ajouter des photos.',
    'renomme'     => 'Album renommé.',
    'motdepasse'  => 'Mot de passe du lien mis à jour.',
    'expiration'  => 'Date d\'expiration mise à jour.',
    'nouveaulien' => 'Nouveau lien généré : l\'ancien ne fonctionne plus.',
    'retire'      => 'Photos retirées de l\'album (elles restent dans ta galerie).',
    'supprime'    => 'Album supprimé (les photos sont conservées).',
    'ajout'       => 'Photos ajoutées à l\'album.',
];
$notice = $okMsgs[$_GET['ok'] ?? ''] ?? '';

// ---- Données ----
$openId = (int) ($_GET['id'] ?? 0);
$album  = $openId > 0 ? Albums::owned($openId, (int) $uid) : null;
$albums = $album ? [] : Albums::forUser((int) $uid);
$photos = $album ? Photos::filterExisting(Albums::photos((int) $album['id']), (int) $uid) : [];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?= Pwa::head('..') ?>
<title>PhotoSync — <?= $album ? htmlspecialchars($album['name']) : 'Albums' ?></title>
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
  header h1 { font-size:18px; margin:0; font-weight:800; }
  .nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .nav a { color:#bcd0ef; text-decoration:none; font-size:.9rem; background:#16213a; border:1px solid var(--line); padding:8px 13px; border-radius:10px; }
  .nav a:hover { border-color:var(--accent); }
  .nav a.active { background:linear-gradient(135deg,var(--accent),var(--violet)); color:#fff; font-weight:700; border:0; }
  .wrap { max-width:1100px; margin:0 auto; padding:18px 22px 40px; }
  .notice { background:rgba(52,211,153,.12); border:1px solid rgba(52,211,153,.45); color:#a7f3d0;
            padding:12px 16px; border-radius:12px; margin-bottom:18px; font-size:.92rem; }
  .panel { background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line);
           border-radius:16px; padding:18px 20px; margin-bottom:20px; }
  .panel h2 { margin:0 0 4px; font-size:16px; }
  .panel p.sub { margin:0 0 14px; color:var(--muted); font-size:.88rem; }
  label.fld { display:block; font-size:.82rem; color:#cbd8ef; margin-bottom:5px; }
  input[type=text], input[type=password], input[type=date] {
      background:#0b1220; border:1px solid var(--line); color:var(--ink);
      padding:11px 13px; border-radius:11px; font-size:.95rem; width:100%; }
  input:focus { outline:none; border-color:var(--accent); }
  .btn { border:0; padding:11px 18px; border-radius:11px; font-size:.9rem; font-weight:700; cursor:pointer;
         color:#fff; background:linear-gradient(135deg,var(--accent),#1d4ed8); transition:filter .15s; white-space:nowrap; }
  .btn:hover { filter:brightness(1.14); }
  .btn.ghost { background:#16213a; border:1px solid var(--line); color:#cbd8ef; }
  .btn.danger { background:linear-gradient(135deg,#ef4444,#b91c1c); }
  .btn.warn { background:linear-gradient(135deg,#f59e0b,#b45309); }
  .row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
  .row > .grow { flex:1; min-width:190px; }
  .albums { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px,1fr)); gap:18px; }
  .acard { background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line);
           border-radius:16px; overflow:hidden; transition:transform .18s, border-color .18s; }
  .acard:hover { transform:translateY(-4px); border-color:var(--accent); }
  .acard a.cover { display:block; height:150px; background:#0a1124; position:relative; }
  .acard a.cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .acard .nocover { display:flex; align-items:center; justify-content:center; height:100%; font-size:40px; color:#33456e; }
  .acard .body { padding:12px 14px; }
  .acard .title { font-weight:700; font-size:.98rem; margin-bottom:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .acard .cnt { color:var(--muted); font-size:.83rem; }
  .tags { display:flex; gap:6px; flex-wrap:wrap; margin-top:9px; }
  .tag { font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; border:1px solid var(--line); background:rgba(34,48,79,.7); color:#cbd8ef; }
  .tag.lock { border-color:rgba(251,191,36,.5); color:#fde68a; background:rgba(251,191,36,.12); }
  .tag.exp  { border-color:rgba(248,113,113,.5); color:#fecaca; background:rgba(248,113,113,.12); }
  .tag.live { border-color:rgba(52,211,153,.5); color:#a7f3d0; background:rgba(52,211,153,.12); }
  .linkbox { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .linkbox input { font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.83rem; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:14px; margin-top:14px; }
  .card { background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line);
          border-radius:14px; overflow:hidden; position:relative; }
  .card img { width:100%; height:150px; object-fit:cover; display:block; background:#0a1124; }
  .card .meta { padding:9px 12px; font-size:12px; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .pick { position:absolute; top:9px; right:9px; width:24px; height:24px; z-index:3; cursor:pointer; accent-color:var(--accent); }
  .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:12px 16px; border:1px solid var(--line);
             border-radius:12px; background:rgba(13,22,42,.9); margin-top:16px; }
  .toolbar label { font-size:.9rem; color:#cbd8ef; display:flex; gap:7px; align-items:center; cursor:pointer; }
  .toolbar .spacer { flex:1; }
  .empty { text-align:center; padding:60px 20px; color:var(--muted); line-height:1.7; }
  .hint { color:var(--muted); font-size:.83rem; margin-top:8px; line-height:1.6; }
</style>
<script>
  function toggleAll(cb){ document.querySelectorAll('input[name="ids[]"]').forEach(x => x.checked = cb.checked); }
  function bulk(msg){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo.'); return false; }
    return confirm(msg.replace('{n}', n));
  }
  // Copie le lien de partage dans le presse-papier (avec repli si l'API n'est pas dispo).
  function copyLink(btn, inputId){
    var f = document.getElementById(inputId);
    var done = function(){ var t = btn.textContent; btn.textContent = '✅ Copié'; setTimeout(function(){ btn.textContent = t; }, 1600); };
    if (navigator.clipboard && window.isSecureContext){
      navigator.clipboard.writeText(f.value).then(done, function(){ f.select(); document.execCommand('copy'); done(); });
    } else {
      f.select(); f.setSelectionRange(0, 99999); document.execCommand('copy'); done();
    }
  }
</script>
</head>
<body>
  <header>
    <h1>📁 Albums — <?= htmlspecialchars($uname) ?></h1>
    <div class="nav">
      <a href="gallery.php">Galerie</a>
      <a href="upload_web.php">➕ Ajouter</a>
      <a href="albums.php" class="active">Albums</a>
      <?php if ($isAdmin): ?><a href="admin.php">🛠️ Admin</a><?php endif; ?>
      <a href="gallery.php?logout=1">Déconnexion</a>
    </div>
  </header>

  <div class="wrap">
    <?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

<?php if (!$album): /* ================= LISTE DES ALBUMS ================= */ ?>

    <div class="panel">
      <h2>Nouvel album</h2>
      <p class="sub">Un album regroupe des photos que tu peux partager d'un seul lien, avec qui tu veux — la personne n'a pas besoin de compte.</p>
      <form method="post" class="row">
        <input type="hidden" name="action" value="create">
        <div class="grow">
          <label class="fld" for="newname">Nom de l'album</label>
          <input id="newname" type="text" name="name" maxlength="120" placeholder="Vacances 2026, Anniversaire de Marion…" required>
        </div>
        <button class="btn" type="submit">Créer l'album</button>
      </form>
    </div>

    <?php if (!$albums): ?>
      <div class="empty">Aucun album pour l'instant.<br>Crée-en un ci-dessus, puis ajoute des photos depuis la galerie.</div>
    <?php else: ?>
      <div class="albums">
        <?php foreach ($albums as $a):
              $expired = Albums::isExpired($a);
              $cover   = (int) ($a['cover'] ?? 0); ?>
          <div class="acard">
            <a class="cover" href="albums.php?id=<?= (int) $a['id'] ?>">
              <?php if ($cover): ?>
                <img loading="lazy" src="../api/media.php?id=<?= $cover ?>&amp;thumb=1" alt="">
              <?php else: ?>
                <div class="nocover">📁</div>
              <?php endif; ?>
            </a>
            <div class="body">
              <div class="title"><?= htmlspecialchars($a['name']) ?></div>
              <div class="cnt"><?= (int) $a['n'] ?> photo(s)</div>
              <div class="tags">
                <?php if ($expired): ?><span class="tag exp">⏰ Lien expiré</span>
                <?php else: ?><span class="tag live">🔗 Lien actif</span><?php endif; ?>
                <?php if (!empty($a['pass_hash'])): ?><span class="tag lock">🔒 Mot de passe</span><?php endif; ?>
                <?php if (!empty($a['expires_at']) && !$expired): ?>
                  <span class="tag">jusqu'au <?= htmlspecialchars(date('d/m/Y', strtotime($a['expires_at']))) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

<?php else: /* ================= UN ALBUM ================= */
      $expired = Albums::isExpired($album);
      $url     = Albums::shareUrl((string) $album['token']); ?>

    <div class="panel">
      <div class="row" style="align-items:center;">
        <div class="grow">
          <h2 style="margin:0;">📁 <?= htmlspecialchars($album['name']) ?></h2>
          <p class="sub" style="margin:4px 0 0;"><?= count($photos) ?> photo(s) · créé le <?= htmlspecialchars(date('d/m/Y', strtotime($album['created_at']))) ?></p>
        </div>
        <a class="btn ghost" href="albums.php">← Tous les albums</a>
        <a class="btn" href="gallery.php?album=<?= (int) $album['id'] ?>">➕ Ajouter des photos</a>
      </div>
    </div>

    <div class="panel">
      <h2>🔗 Lien de partage</h2>
      <p class="sub">Envoie ce lien à la personne (SMS, WhatsApp, e-mail). Elle verra toutes les photos de l'album et pourra les télécharger, sans créer de compte.</p>
      <div class="linkbox">
        <input id="shareUrl" class="grow" type="text" readonly value="<?= htmlspecialchars($url) ?>" onclick="this.select()" style="flex:1;min-width:220px;">
        <button class="btn" type="button" onclick="copyLink(this,'shareUrl')">📋 Copier</button>
        <a class="btn ghost" href="share.php?a=<?= htmlspecialchars($album['token']) ?>" target="_blank" rel="noopener">👁️ Aperçu</a>
      </div>
      <?php if ($expired): ?>
        <div class="hint" style="color:#fecaca;">⏰ Ce lien a expiré le <?= htmlspecialchars(date('d/m/Y', strtotime($album['expires_at']))) ?> : il n'affiche plus rien. Change ou efface la date ci-dessous pour le réactiver.</div>
      <?php endif; ?>
      <div class="hint">Toute personne qui possède ce lien peut voir l'album — traite-le comme un mot de passe. En cas de doute, génère un nouveau lien : l'ancien cesse aussitôt de fonctionner.</div>

      <div class="row" style="margin-top:16px;">
        <form method="post" class="row grow" style="gap:10px;">
          <input type="hidden" name="action" value="setpass">
          <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
          <div class="grow">
            <label class="fld">Mot de passe du lien <?= !empty($album['pass_hash']) ? '(actuellement : actif)' : '(aucun pour l\'instant)' ?></label>
            <input type="password" name="pass" placeholder="<?= !empty($album['pass_hash']) ? 'Laisser vide pour retirer le mot de passe' : 'Facultatif' ?>" autocomplete="new-password">
          </div>
          <button class="btn ghost" type="submit">Enregistrer</button>
        </form>
        <form method="post" class="row grow" style="gap:10px;">
          <input type="hidden" name="action" value="setexp">
          <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
          <div class="grow">
            <label class="fld">Le lien expire le (facultatif)</label>
            <input type="date" name="expires" value="<?= !empty($album['expires_at']) ? htmlspecialchars(date('Y-m-d', strtotime($album['expires_at']))) : '' ?>">
          </div>
          <button class="btn ghost" type="submit">Enregistrer</button>
        </form>
      </div>
    </div>

    <div class="panel">
      <h2>⚙️ Réglages de l'album</h2>
      <div class="row" style="margin-top:12px;">
        <form method="post" class="row grow" style="gap:10px;">
          <input type="hidden" name="action" value="rename">
          <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
          <div class="grow">
            <label class="fld">Nom de l'album</label>
            <input type="text" name="name" maxlength="120" value="<?= htmlspecialchars($album['name']) ?>" required>
          </div>
          <button class="btn ghost" type="submit">Renommer</button>
        </form>
        <form method="post" onsubmit="return confirm('Générer un NOUVEAU lien ? L\'ancien lien cessera immédiatement de fonctionner pour tous ceux à qui tu l\'as envoyé.');">
          <input type="hidden" name="action" value="reset">
          <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
          <button class="btn warn" type="submit">🔄 Nouveau lien</button>
        </form>
        <form method="post" onsubmit="return confirm('Supprimer cet album ? Les photos, elles, restent dans ta galerie.');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
          <button class="btn danger" type="submit">🗑 Supprimer l'album</button>
        </form>
      </div>
    </div>

    <?php if (!$photos): ?>
      <div class="empty">Cet album est vide.<br><a href="gallery.php?album=<?= (int) $album['id'] ?>" style="color:#8ab4ff;">Choisir des photos dans la galerie →</a></div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
        <div class="toolbar">
          <label><input type="checkbox" onclick="toggleAll(this)"> Tout sélectionner</label>
          <span class="spacer"></span>
          <button class="btn danger" type="submit" onclick="return bulk('Retirer {n} photo(s) de cet album ? (elles restent dans ta galerie)')">➖ Retirer de l'album</button>
        </div>
        <div class="grid">
          <?php foreach ($photos as $p): $pid = (int) $p['id'];
                $cat = Photos::categoryOf($p['original_name'], (string) $p['stored_path']); ?>
            <div class="card">
              <input class="pick" type="checkbox" name="ids[]" value="<?= $pid ?>" title="Sélectionner">
              <a href="view.php?id=<?= $pid ?>" target="_blank" title="<?= htmlspecialchars($p['original_name']) ?>">
                <img loading="lazy" src="../api/media.php?id=<?= $pid ?>&amp;thumb=1" alt="">
              </a>
              <div class="meta"><?= Photos::categoryLabel($cat) ?> · <?= htmlspecialchars($p['original_name']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </form>
    <?php endif; ?>

<?php endif; ?>
  </div>
</body>
</html>
