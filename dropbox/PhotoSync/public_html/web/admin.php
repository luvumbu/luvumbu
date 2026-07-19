<?php
// === Panneau d'administration — liste des inscrits ===
//   https://luvumbu.com/web/admin.php
// Protégé par le MOT DE PASSE DE LA BASE DE DONNÉES (DB_PASS, défini lors de
// l'installation). Affiche les comptes enregistrés, avec le nombre de photos
// de chacun. Pas de mot de passe supplémentaire à retenir.

require __DIR__ . '/../lib/bootstrap.php';

Auth::startSession();

// --- Déconnexion ---
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_ok']);
    header('Location: admin.php');
    exit;
}

// --- Connexion admin (mot de passe = celui de la base de données) ---
$error = '';
if (($_POST['action'] ?? '') === 'login') {
    $given = (string) ($_POST['password'] ?? '');
    if (DB_PASS === '') {
        // Sécurité : pas de mot de passe BDD => on n'ouvre pas l'admin à n'importe qui.
        $error = "Accès admin indisponible : aucun mot de passe n'est défini pour la base de données.";
    } elseif (hash_equals(DB_PASS, $given)) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Mot de passe incorrect.';
    }
}

$isAdmin = !empty($_SESSION['admin_ok']);

// ---- Page de connexion ----
if (!$isAdmin):
?>
<!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title>PhotoSync — Admin</title>
<style>
  body { font-family:system-ui,sans-serif; background:#0b1220; color:#e2e8f0; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
  .card { background:#16213a; padding:28px; border-radius:16px; width:300px; box-shadow:0 10px 30px rgba(0,0,0,.5); }
  h1 { font-size:20px; margin:0 0 4px; } p.sub { color:#8aa0bd; font-size:13px; margin:0 0 20px; }
  input { width:100%; box-sizing:border-box; padding:12px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:15px; margin-top:10px; }
  button { width:100%; margin-top:14px; padding:12px; border:0; border-radius:10px; background:#7c3aed; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
  .err { color:#f87171; font-size:13px; margin-top:10px; }
</style></head>
<body><form class="card" method="post">
  <h1>🛠️ Administration</h1><p class="sub">Mot de passe de la base de données</p>
  <input type="hidden" name="action" value="login">
  <input type="password" name="password" placeholder="Mot de passe de la BDD" autofocus required>
  <button type="submit">Entrer</button>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
</form></body></html>
<?php
    exit;
endif;

// --- Action admin : mettre des photos à la corbeille (n'importe quel compte) ---
if (($_POST['action'] ?? '') === 'trash' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    try {
        Auth::ensureSchema();
        $db = Db::pdo();
        $owner = $db->prepare('SELECT user_id FROM ' . TBL_PHOTOS . ' WHERE id = ?');
        foreach ($_POST['ids'] as $v) {
            $pid = (int) $v;
            if ($pid <= 0) continue;
            $owner->execute([$pid]);
            $ownerUid = (int) ($owner->fetch(PDO::FETCH_ASSOC)['user_id'] ?? 0);
            if ($ownerUid > 0) Photos::trash($pid, $ownerUid); // réutilise la logique scopée au compte
        }
    } catch (Throwable $e) { /* affiché au rechargement si besoin */ }
    // PRG : on recharge en conservant le filtre/profil/page.
    $qs = 'view=photos';
    if (!empty($_POST['user'])) $qs .= '&user=' . (int) $_POST['user'];
    if (!empty($_POST['p']))    $qs .= '&p=' . (int) $_POST['p'];
    header('Location: admin.php?' . $qs);
    exit;
}

// --- Action admin : supprimer un compte (avec toutes ses photos) ---
if (($_POST['action'] ?? '') === 'delete_user' && !empty($_POST['uid'])) {
    $du = (int) $_POST['uid'];
    if ($du > 0) {
        try {
            $db = Db::pdo();
            $ph = $db->prepare('SELECT id FROM ' . TBL_PHOTOS . ' WHERE user_id = ?');
            $ph->execute([$du]);
            foreach ($ph->fetchAll(PDO::FETCH_COLUMN) as $pid) Photos::deleteForever((int) $pid, $du);
            $db->prepare('DELETE FROM ' . TBL_USERS . ' WHERE id = ?')->execute([$du]);
            $_SESSION['admin_msg'] = 'Compte supprimé (avec ses photos).';
        } catch (Throwable $e) { $_SESSION['admin_msg'] = 'Erreur : ' . $e->getMessage(); }
    }
    header('Location: admin.php'); // retour à la liste des inscrits
    exit;
}

// --- Action admin : changer le mot de passe d'un compte ---
if (($_POST['action'] ?? '') === 'set_pass' && !empty($_POST['uid'])) {
    $du = (int) $_POST['uid'];
    $np = (string) ($_POST['newpass'] ?? '');
    if ($du > 0 && strlen($np) >= 4) {
        try {
            Db::pdo()->prepare('UPDATE ' . TBL_USERS . ' SET pass_hash = ? WHERE id = ?')
                     ->execute([password_hash($np, PASSWORD_DEFAULT), $du]);
            $_SESSION['admin_msg'] = 'Mot de passe modifié.';
        } catch (Throwable $e) { $_SESSION['admin_msg'] = 'Erreur : ' . $e->getMessage(); }
    } else {
        $_SESSION['admin_msg'] = 'Mot de passe trop court (4 caractères minimum).';
    }
    header('Location: admin.php?view=photos&user=' . $du);
    exit;
}

// Filtre éventuel sur un profil précis (clic depuis la liste des inscrits).
$filterUser = isset($_GET['user']) ? max(0, (int) $_GET['user']) : 0;
// Vue courante : « users » (inscrits) ou « photos ». Un filtre profil force la vue photos.
$view = (($_GET['view'] ?? '') === 'photos' || $filterUser > 0) ? 'photos' : 'users';

// Message d'action admin (suppression / mot de passe).
$adminMsg = '';
if (isset($_SESSION['admin_msg'])) { $adminMsg = $_SESSION['admin_msg']; unset($_SESSION['admin_msg']); }

// ---- Données ----
$users = [];
$photos = [];
$totalPhotos = 0;
$totalUsers = 0;
$filterName = '';
$page = 1; $pages = 1;
$perPage = 24;
$dbError = '';
try {
    Auth::ensureSchema();
    $db = Db::pdo();

    // Compteurs globaux (affichés dans les deux vues).
    $totalUsers  = (int) $db->query('SELECT COUNT(*) c FROM ' . TBL_USERS)->fetch(PDO::FETCH_ASSOC)['c'];
    $totalPhotos = (int) $db->query('SELECT COUNT(*) c FROM ' . TBL_PHOTOS . ' WHERE deleted_at IS NULL')->fetch(PDO::FETCH_ASSOC)['c'];

    if ($view === 'users') {
        $sql = "SELECT u.id, u.username, u.created_at,
                  (SELECT COUNT(*) FROM " . TBL_PHOTOS . " p WHERE p.user_id = u.id AND p.deleted_at IS NULL) AS active,
                  (SELECT COUNT(*) FROM " . TBL_PHOTOS . " p WHERE p.user_id = u.id AND p.deleted_at IS NOT NULL) AS trashed
                FROM " . TBL_USERS . " u
                ORDER BY u.created_at DESC, u.id DESC";
        $users = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Photos (toutes, ou filtrées sur un profil), paginées.
        $whereUser = $filterUser > 0 ? ' AND p.user_id = :user' : '';

        if ($filterUser > 0) {
            $un = $db->prepare('SELECT username FROM ' . TBL_USERS . ' WHERE id = ?');
            $un->execute([$filterUser]);
            $filterName = (string) ($un->fetch(PDO::FETCH_ASSOC)['username'] ?? '');
        }

        $cst = $db->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " p WHERE p.deleted_at IS NULL AND p.hidden = 0" . $whereUser);
        if ($filterUser > 0) $cst->bindValue(':user', $filterUser, PDO::PARAM_INT);
        $cst->execute();
        $photoCount = (int) $cst->fetch(PDO::FETCH_ASSOC)['c'];

        $pages = max(1, (int) ceil($photoCount / $perPage));
        $page  = min(max(1, (int) ($_GET['p'] ?? 1)), $pages);
        $off   = ($page - 1) * $perPage;

        $st = $db->prepare(
            "SELECT p.id, p.original_name, p.taken_at, p.uploaded_at, p.stored_path, p.deleted_at, p.user_id, u.username
             FROM " . TBL_PHOTOS . " p
             LEFT JOIN " . TBL_USERS . " u ON u.id = p.user_id
             WHERE p.deleted_at IS NULL AND p.hidden = 0" . $whereUser . "
             ORDER BY COALESCE(p.taken_at, p.uploaded_at) DESC, p.id DESC
             LIMIT :lim OFFSET :off"
        );
        if ($filterUser > 0) $st->bindValue(':user', $filterUser, PDO::PARAM_INT);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', $off, PDO::PARAM_INT);
        $st->execute();
        // On exclut (et on efface) les entrées dont le fichier a disparu du disque.
        $photos = array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), function ($r) {
            if (Photos::fileExists($r)) return true;
            Photos::deleteForever((int) $r['id'], (int) $r['user_id']);
            return false;
        }));
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
$pageQs = 'view=photos' . ($filterUser > 0 ? '&user=' . $filterUser : '');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title>PhotoSync — Inscrits</title>
<style>
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,sans-serif; margin:0; background:#0b1220; color:#e2e8f0; }
  header { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:16px 20px;
           background:linear-gradient(135deg,#7c3aed,#4c1d95); position:sticky; top:0; box-shadow:0 4px 20px rgba(0,0,0,.4); flex-wrap:wrap; }
  header h1 { font-size:18px; margin:0; font-weight:700; }
  header a { color:#fff; text-decoration:none; font-size:13px; background:rgba(255,255,255,.15); padding:8px 12px; border-radius:20px; }
  .wrap { max-width:900px; margin:0 auto; padding:18px; }
  .stats { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:16px; }
  .stat { background:#16213a; border-radius:12px; padding:14px 18px; flex:1; min-width:150px; }
  .stat .n { font-size:26px; font-weight:800; } .stat .l { color:#8aa0bd; font-size:13px; }
  table { width:100%; border-collapse:collapse; background:#16213a; border-radius:12px; overflow:hidden; }
  th, td { padding:12px 14px; text-align:left; font-size:14px; border-bottom:1px solid #243049; }
  th { background:#1e293b; color:#cbd5e1; font-size:13px; text-transform:uppercase; letter-spacing:.5px; }
  tr:last-child td { border-bottom:0; }
  td.num { text-align:center; font-variant-numeric:tabular-nums; }
  .badge { background:#0b1220; border:1px solid #334155; border-radius:20px; padding:3px 10px; font-size:12px; }
  .empty { text-align:center; padding:60px 20px; color:#8aa0bd; }
  .err { background:#3b0d0d; border:1px solid #7f1d1d; color:#fca5a5; padding:14px; border-radius:10px; white-space:pre-wrap; font-size:13px; }
  .tabs { display:flex; gap:8px; margin-bottom:16px; }
  .tabs a { text-decoration:none; font-size:14px; color:#cbd5e1; background:#16213a; padding:9px 16px; border-radius:20px; }
  .tabs a.active { background:#7c3aed; color:#fff; font-weight:700; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:14px; }
  .pcard { background:#16213a; border-radius:12px; overflow:hidden; position:relative; }
  .pcard img { width:100%; height:150px; object-fit:cover; display:block; background:#0f172a; }
  .pcard .who { position:absolute; left:8px; top:8px; background:rgba(124,58,237,.85); color:#fff; font-size:11px; font-weight:700; padding:3px 9px; border-radius:14px; }
  .pcard .cap { padding:8px 10px; font-size:11px; color:#8aa0bd; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .pager { display:flex; gap:10px; justify-content:center; align-items:center; padding:22px; flex-wrap:wrap; }
  .pager a, .pager span { padding:9px 16px; border-radius:10px; background:#16213a; color:#e2e8f0; text-decoration:none; font-size:14px; }
  .pager .cur { background:#7c3aed; font-weight:700; }
  tr.click { cursor:pointer; } tr.click:hover td { background:#1b2742; }
  tr.click td a { color:#93c5fd; text-decoration:none; font-weight:600; }
  .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }
  .toolbar label { font-size:14px; color:#cbd5e1; display:flex; gap:7px; align-items:center; cursor:pointer; }
  .toolbar .spacer { flex:1; }
  .subhead { font-size:15px; color:#cbd5e1; margin-bottom:12px; }
  .subhead a { color:#93c5fd; text-decoration:none; }
  .btn-trash { border:0; padding:10px 16px; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; color:#fff; background:#ef4444; }
  .pcard .pick { position:absolute; top:8px; right:8px; width:24px; height:24px; z-index:2; cursor:pointer; accent-color:#7c3aed; }
  .amsg { background:#0f3d22; border:1px solid #166534; color:#86efac; padding:11px 14px; border-radius:10px; margin-bottom:14px; font-size:14px; }
  .manage { background:#16213a; border-radius:12px; padding:14px; margin-bottom:16px; display:flex; gap:18px; flex-wrap:wrap; align-items:flex-end; }
  .manage .grp { display:flex; flex-direction:column; gap:6px; }
  .manage label { font-size:12px; color:#8aa0bd; }
  .manage input[type=password] { padding:9px 11px; border-radius:9px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:14px; }
  .manage .bpass { border:0; padding:9px 14px; border-radius:9px; font-weight:700; cursor:pointer; color:#fff; background:#1565C0; }
  .manage .bdel { border:0; padding:9px 14px; border-radius:9px; font-weight:700; cursor:pointer; color:#fff; background:#7c2d12; }
</style>
<script>
  function goUser(id){ location.href = 'admin.php?view=photos&user=' + id; }
  function toggleAllPhotos(cb){ document.querySelectorAll('input[name="ids[]"]').forEach(x => x.checked = cb.checked); }
  function confirmTrash(){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo.'); return false; }
    return confirm('Mettre ' + n + ' photo(s) à la corbeille ? (récupérables 30 jours)');
  }
  function haveSelectionPhotos(){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo à télécharger.'); return false; }
    return true;
  }
</script>
</head>
<body>
  <header>
    <h1>🛠️ PhotoSync — Admin</h1>
    <div><a href="gallery.php">Ma galerie</a> &nbsp; <a href="admin.php?logout=1">Déconnexion</a></div>
  </header>
  <div class="wrap">
    <?php if ($dbError): ?>
      <div class="err">Impossible de lire la base de données.

Détail : <?= htmlspecialchars($dbError) ?>

➡️ Vérifie la configuration via <a href="../install.php" style="color:#93c5fd;">install.php</a>.</div>
    <?php else: ?>
      <div class="stats">
        <div class="stat"><div class="n"><?= $totalUsers ?></div><div class="l">compte(s) inscrit(s)</div></div>
        <div class="stat"><div class="n"><?= $totalPhotos ?></div><div class="l">photo(s) au total</div></div>
      </div>

      <div class="tabs">
        <a href="admin.php" class="<?= $view === 'users' ? 'active' : '' ?>">👥 Inscrits</a>
        <a href="admin.php?view=photos" class="<?= $view === 'photos' ? 'active' : '' ?>">🖼️ Toutes les photos</a>
        <a href="maintenance.php">🔧 Maintenance</a>
      </div>

      <?php if ($view === 'users'): ?>
        <?php if (!$users): ?>
          <div class="empty">Aucun inscrit pour l'instant.</div>
        <?php else: ?>
          <p class="subhead">Clique sur un inscrit pour voir (et gérer) ses photos.</p>
          <table>
            <thead>
              <tr><th>#</th><th>Identifiant</th><th>Inscrit le</th><th class="num">Photos</th><th class="num">Corbeille</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): $uid = (int) $u['id']; ?>
                <tr class="click" onclick="goUser(<?= $uid ?>)">
                  <td><?= $uid ?></td>
                  <td><a href="admin.php?view=photos&user=<?= $uid ?>"><?= htmlspecialchars($u['username']) ?></a></td>
                  <td><?= htmlspecialchars(Photos::frDate($u['created_at'])) ?></td>
                  <td class="num"><span class="badge"><?= (int) $u['active'] ?></span></td>
                  <td class="num"><span class="badge"><?= (int) $u['trashed'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

      <?php else: /* vue photos */ ?>
        <?php if ($filterUser > 0): ?>
          <p class="subhead">📁 Photos de <b><?= htmlspecialchars($filterName ?: ('compte #' . $filterUser)) ?></b>
             &nbsp;·&nbsp; <a href="admin.php">← tous les inscrits</a></p>
          <?php if ($adminMsg): ?><div class="amsg"><?= htmlspecialchars($adminMsg) ?></div><?php endif; ?>
          <div class="manage">
            <form method="post" class="grp" onsubmit="return confirm('Changer le mot de passe de ce compte ?')">
              <input type="hidden" name="action" value="set_pass">
              <input type="hidden" name="uid" value="<?= $filterUser ?>">
              <label>Nouveau mot de passe (4 car. min.)</label>
              <div style="display:flex;gap:8px;">
                <input type="password" name="newpass" required>
                <button class="bpass" type="submit">Changer</button>
              </div>
            </form>
            <form method="post" class="grp" onsubmit="return confirm('SUPPRIMER ce compte ET toutes ses photos ? Action irréversible.')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="uid" value="<?= $filterUser ?>">
              <label>Compte</label>
              <button class="bdel" type="submit">🗑 Supprimer ce compte</button>
            </form>
          </div>
        <?php endif; ?>

        <?php if (!$photos): ?>
          <div class="empty">Aucune photo<?= $filterUser > 0 ? ' pour ce compte' : ' sur le serveur' ?> pour l'instant.</div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="trash">
            <input type="hidden" name="user" value="<?= $filterUser ?>">
            <input type="hidden" name="p" value="<?= $page ?>">
            <div class="toolbar">
              <label><input type="checkbox" onclick="toggleAllPhotos(this)"> Tout sélectionner</label>
              <span class="spacer"></span>
              <?php if ($filterUser > 0): ?>
                <button class="btn-trash" type="submit" formaction="download.php" formmethod="post" style="background:#0ea5e9" onclick="return haveSelectionPhotos()">⬇️ Télécharger la sélection</button>
                <a href="download.php?all=1&user=<?= $filterUser ?>" style="text-decoration:none;background:#0ea5e9;color:#fff;padding:10px 16px;border-radius:10px;font-weight:700;font-size:14px;" onclick="return confirm('Télécharger TOUTES les photos de ce compte en .zip ?')">⬇️ Tout télécharger</a>
              <?php endif; ?>
              <button class="btn-trash" type="submit" onclick="return confirmTrash()">🗑 Mettre la sélection à la corbeille</button>
            </div>
            <div class="grid">
              <?php foreach ($photos as $p): $id = (int) $p['id']; ?>
                <div class="pcard">
                  <input class="pick" type="checkbox" name="ids[]" value="<?= $id ?>" title="Sélectionner">
                  <?php if ($filterUser === 0): ?><span class="who"><?= htmlspecialchars($p['username'] ?? '—') ?></span><?php endif; ?>
                  <a href="../api/media.php?id=<?= $id ?>" target="_blank" title="<?= htmlspecialchars($p['original_name']) ?>">
                    <img loading="lazy" src="../api/media.php?id=<?= $id ?>&amp;thumb=micro" alt="">
                  </a>
                  <div class="cap"><?= htmlspecialchars(Photos::frDate($p['taken_at'] ?: $p['uploaded_at'])) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </form>
          <?php if ($pages > 1): ?>
            <div class="pager">
              <?php if ($page > 1): ?><a href="?<?= $pageQs ?>&p=<?= $page - 1 ?>">‹ Précédent</a><?php endif; ?>
              <span class="cur">Page <?= $page ?> / <?= $pages ?></span>
              <?php if ($page < $pages): ?><a href="?<?= $pageQs ?>&p=<?= $page + 1 ?>">Suivant ›</a><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
