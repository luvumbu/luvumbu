<?php
// === Panneau d'administration — liste des inscrits ===
//   https://luvumbu.com/web/admin.php
// Protégé par le MOT DE PASSE DE LA BASE DE DONNÉES (DB_PASS, défini lors de
// l'installation). Affiche les comptes enregistrés, avec le nombre de photos
// de chacun. Pas de mot de passe supplémentaire à retenir.

require __DIR__ . '/../lib/bootstrap.php';

Auth::startSession();

// Base pas encore configurée → assistant d'installation.
if (!Db::isReady()) { header('Location: ../install.php'); exit; }
Auth::ensureSchema();

// --- Déconnexion de la clé maître (la session Google se ferme depuis la galerie) ---
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_ok']);
    header('Location: admin.php');
    exit;
}

// --- Connexion admin par CLÉ MAÎTRE (mot de passe de la base de données) ---
// Sert de secours : l'accès normal se fait avec un compte Google promu admin.
$error = '';
if (($_POST['action'] ?? '') === 'login') {
    $given = (string) ($_POST['password'] ?? '');
    if (DB_PASS === '') {
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

// Utilisateur Google connecté (via la galerie) ?
$gid = Auth::currentUserId();
// Accès admin = clé maître BDD OU compte Google promu administrateur.
$isAdmin = !empty($_SESSION['admin_ok']) || ($gid !== null && Auth::isAdmin($gid));

// ---- Page de connexion ----
if (!$isAdmin):
?>
<!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?= Pwa::head('..') ?>
<title>PhotoSync — Admin</title>
<style>
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,sans-serif; margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;
         background:radial-gradient(1100px 560px at 50% -10%, #4c1d95 0%, #0b1220 55%); color:#e2e8f0; }
  .card { width:100%; max-width:360px; background:rgba(22,33,58,.85); -webkit-backdrop-filter:blur(8px); backdrop-filter:blur(8px);
          border:1px solid rgba(148,163,184,.15); padding:34px 28px; border-radius:22px; box-shadow:0 20px 60px rgba(0,0,0,.55); text-align:center; }
  .logo { width:72px; height:72px; margin:0 auto 16px; border-radius:20px; display:flex; align-items:center; justify-content:center;
          font-size:36px; background:linear-gradient(135deg,#7c3aed,#4c1d95); box-shadow:0 8px 24px rgba(124,58,237,.4); }
  h1 { font-size:22px; margin:0 0 6px; font-weight:800; }
  p.sub { color:#94a3b8; font-size:13px; margin:0 0 20px; }
  input { width:100%; padding:13px; border-radius:11px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:15px; }
  button { width:100%; margin-top:14px; padding:13px; border:0; border-radius:11px; background:#7c3aed; color:#fff; font-size:15px; font-weight:700; cursor:pointer; }
  .err { color:#fca5a5; font-size:13px; margin-top:12px; background:rgba(127,29,29,.3); border:1px solid #7f1d1d; padding:10px; border-radius:10px; }
</style></head>
<body>
<?php if ($gid !== null): /* connecté Google mais pas admin */ ?>
  <div class="card">
    <div class="logo">🛠️</div>
    <h1>Administration</h1>
    <p class="sub">Accès réservé aux administrateurs</p>
    <p style="color:#fca5a5;font-size:14px;margin:0;">Ton compte n'a pas les droits d'administrateur. Demande à un administrateur de te les accorder.</p>
    <p style="margin-top:18px;"><a href="gallery.php" style="color:#93c5fd;text-decoration:none;">← Retour à ma galerie</a></p>
  </div>
<?php else: ?>
  <form class="card" method="post">
    <div class="logo">🛠️</div>
    <h1>Administration</h1><p class="sub">Connecte-toi avec Google, ou utilise la clé maître</p>
    <input type="hidden" name="action" value="login">
    <input type="password" name="password" placeholder="Clé maître (mot de passe de la base)" autofocus required>
    <button type="submit">Entrer</button>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <p style="margin-top:16px;text-align:center;"><a href="gallery.php" style="color:#93c5fd;text-decoration:none;">Se connecter avec Google</a></p>
  </form>
<?php endif; ?>
</body></html>
<?php
    exit;
endif;

// Reconstruit la query string de retour en conservant filtre/profil/page (PRG).
$backToPhotos = function (): string {
    $qs = 'view=photos';
    if (!empty($_POST['user']))       $qs .= '&user=' . (int) $_POST['user'];
    if (!empty($_POST['unassigned'])) $qs .= '&unassigned=1';
    if (!empty($_POST['p']))          $qs .= '&p=' . (int) $_POST['p'];
    return $qs;
};

// --- Action admin : mettre des photos à la corbeille (n'importe quel compte) ---
if (($_POST['action'] ?? '') === 'trash' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    try {
        Auth::ensureSchema();
        foreach (Request::ids() as $pid) {
            $ownerUid = Photos::ownerId($pid);
            if ($ownerUid > 0) Photos::trash($pid, $ownerUid); // réutilise la logique scopée au compte
        }
    } catch (Throwable $e) { /* affiché au rechargement si besoin */ }
    header('Location: admin.php?' . $backToPhotos());
    exit;
}

// --- Action admin : ATTRIBUER des photos à un compte (y compris des orphelines) ---
if (($_POST['action'] ?? '') === 'assign' && !empty($_POST['ids']) && is_array($_POST['ids']) && !empty($_POST['target'])) {
    $target = (int) $_POST['target'];
    try {
        Auth::ensureSchema();
        $db = Db::pdo();
        // Le compte de destination doit exister.
        $chk = $db->prepare('SELECT id FROM ' . TBL_USERS . ' WHERE id = ?');
        $chk->execute([$target]);
        if (!$chk->fetch()) {
            $_SESSION['admin_msg'] = "Compte de destination introuvable.";
        } else {
            $assigned = 0; $skipped = 0;
            $upd = $db->prepare('UPDATE ' . TBL_PHOTOS . ' SET user_id = ? WHERE id = ?');
            foreach (Request::ids() as $pid) {
                try {
                    $upd->execute([$target, $pid]);
                    $assigned++;
                } catch (Throwable $e) {
                    // Clé unique (user_id, sha256) : ce compte a déjà cette photo → on ignore.
                    $skipped++;
                }
            }
            $_SESSION['admin_msg'] = "$assigned photo(s) attribuée(s)"
                . ($skipped ? ", $skipped ignorée(s) (déjà présente(s) sur ce compte)" : '') . '.';
        }
    } catch (Throwable $e) { $_SESSION['admin_msg'] = 'Erreur : ' . $e->getMessage(); }
    header('Location: admin.php?' . $backToPhotos());
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

// --- Action admin : donner / retirer les droits d'administrateur ---
if (($_POST['action'] ?? '') === 'set_admin' && !empty($_POST['uid'])) {
    $du  = (int) $_POST['uid'];
    $val = (($_POST['admin'] ?? '') === '1') ? 1 : 0;
    if ($du > 0) {
        try {
            // Sécurité : on ne retire pas le DERNIER administrateur (éviter le verrouillage total).
            if ($val === 0) {
                $nbAdmins = (int) Db::pdo()->query('SELECT COUNT(*) c FROM ' . TBL_USERS . ' WHERE is_admin = 1')
                                          ->fetch(PDO::FETCH_ASSOC)['c'];
                if ($nbAdmins <= 1) {
                    $_SESSION['admin_msg'] = "Impossible : il doit rester au moins un administrateur.";
                    header('Location: admin.php');
                    exit;
                }
            }
            Db::pdo()->prepare('UPDATE ' . TBL_USERS . ' SET is_admin = ? WHERE id = ?')->execute([$val, $du]);
            $_SESSION['admin_msg'] = $val ? 'Droits administrateur accordés.' : 'Droits administrateur retirés.';
        } catch (Throwable $e) { $_SESSION['admin_msg'] = 'Erreur : ' . $e->getMessage(); }
    }
    header('Location: admin.php');
    exit;
}

// Filtre éventuel sur un profil précis (clic depuis la liste des inscrits).
$filterUser = isset($_GET['user']) ? max(0, (int) $_GET['user']) : 0;
// Filtre « photos sans compte » (orphelines : user_id IS NULL).
$unassigned = !empty($_GET['unassigned']) && $filterUser === 0;
// Vue courante : « users » (inscrits) ou « photos ». Un filtre force la vue photos.
$view = (($_GET['view'] ?? '') === 'photos' || $filterUser > 0 || $unassigned) ? 'photos' : 'users';

// Message d'action admin (suppression / mot de passe).
$adminMsg = '';
if (isset($_SESSION['admin_msg'])) { $adminMsg = $_SESSION['admin_msg']; unset($_SESSION['admin_msg']); }

// ---- Données ----
$users = [];
$allUsers = [];          // liste (id, username) pour le menu d'attribution
$photos = [];
$totalPhotos = 0;
$totalUsers = 0;
$totalUnassigned = 0;    // nombre de photos sans compte (orphelines)
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
    $totalUnassigned = (int) $db->query('SELECT COUNT(*) c FROM ' . TBL_PHOTOS . ' WHERE deleted_at IS NULL AND user_id IS NULL')->fetch(PDO::FETCH_ASSOC)['c'];
    // Comptes disponibles comme destination d'attribution.
    $allUsers = $db->query('SELECT id, username FROM ' . TBL_USERS . ' ORDER BY username ASC')->fetchAll(PDO::FETCH_ASSOC);

    if ($view === 'users') {
        $sql = "SELECT u.id, u.username, u.email, u.created_at, u.is_admin,
                  (SELECT COUNT(*) FROM " . TBL_PHOTOS . " p WHERE p.user_id = u.id AND p.deleted_at IS NULL) AS active,
                  (SELECT COUNT(*) FROM " . TBL_PHOTOS . " p WHERE p.user_id = u.id AND p.deleted_at IS NOT NULL) AS trashed
                FROM " . TBL_USERS . " u
                ORDER BY u.created_at DESC, u.id DESC";
        $users = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Photos (toutes, filtrées sur un profil, ou sans compte), paginées.
        $whereUser = '';
        if ($filterUser > 0)      $whereUser = ' AND p.user_id = :user';
        elseif ($unassigned)      $whereUser = ' AND p.user_id IS NULL';

        if ($filterUser > 0) {
            $un = $db->prepare('SELECT username FROM ' . TBL_USERS . ' WHERE id = ?');
            $un->execute([$filterUser]);
            $filterName = (string) ($un->fetch(PDO::FETCH_ASSOC)['username'] ?? '');
        }

        $cst = $db->prepare("SELECT COUNT(*) c FROM " . TBL_PHOTOS . " p WHERE p.deleted_at IS NULL" . $whereUser);
        if ($filterUser > 0) $cst->bindValue(':user', $filterUser, PDO::PARAM_INT);
        $cst->execute();
        $photoCount = (int) $cst->fetch(PDO::FETCH_ASSOC)['c'];

        ['pages' => $pages, 'page' => $page, 'offset' => $off] =
            Photos::paginate($photoCount, (int) ($_GET['p'] ?? 1), $perPage);

        $st = $db->prepare(
            "SELECT p.id, p.original_name, p.taken_at, p.uploaded_at, p.stored_path, p.deleted_at, p.user_id, u.username
             FROM " . TBL_PHOTOS . " p
             LEFT JOIN " . TBL_USERS . " u ON u.id = p.user_id
             WHERE p.deleted_at IS NULL" . $whereUser . "
             ORDER BY COALESCE(p.taken_at, p.uploaded_at) DESC, p.id DESC
             LIMIT :lim OFFSET :off"
        );
        if ($filterUser > 0) $st->bindValue(':user', $filterUser, PDO::PARAM_INT);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', $off, PDO::PARAM_INT);
        $st->execute();
        // On exclut (et on efface) les entrées dont le fichier a disparu du disque.
        $photos = Photos::filterExisting($st->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
$pageQs = 'view=photos' . ($filterUser > 0 ? '&user=' . $filterUser : ($unassigned ? '&unassigned=1' : ''));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?= Pwa::head('..') ?>
<title>PhotoSync — Inscrits</title>
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
  a { color:var(--accent); text-decoration:none; }
  header { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:16px 28px;
           background:rgba(8,14,28,.7); -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px);
           border-bottom:1px solid var(--line); position:sticky; top:0; z-index:6; flex-wrap:wrap; }
  header h1 { font-size:18px; margin:0; font-weight:800; letter-spacing:.02em; }
  header a { color:#bcd0ef; text-decoration:none; font-size:.9rem; background:#16213a; border:1px solid var(--line); padding:8px 13px; border-radius:10px; }
  header a:hover { border-color:var(--accent); }
  .wrap { max-width:980px; margin:0 auto; padding:30px 22px 60px; }
  .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:24px; }
  .stat { background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line); border-radius:16px; padding:16px 18px; position:relative; overflow:hidden; }
  .stat .n { font-size:1.9rem; font-weight:800; } .stat .l { color:var(--muted); font-size:.8rem; }
  .stat::after { content:""; position:absolute; right:-30px; top:-30px; width:90px; height:90px; background:radial-gradient(circle,rgba(79,140,255,.2),transparent 70%); }
  table { width:100%; border-collapse:collapse; background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line); border-radius:16px; overflow:hidden; }
  th, td { padding:12px 14px; text-align:left; font-size:.87rem; border-bottom:1px solid var(--line); vertical-align:top; }
  th { color:var(--muted); font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
  tr:last-child td { border-bottom:0; }
  td.num { text-align:center; font-variant-numeric:tabular-nums; }
  .badge { display:inline-block; background:#1e2c4b; border:1px solid var(--line); border-radius:999px; padding:3px 10px; font-size:.74rem; color:#aebfde; }
  .empty { text-align:center; padding:30px; border:1px dashed var(--line); border-radius:14px; color:var(--muted); }
  .err { background:rgba(248,113,113,.14); border:1px solid rgba(248,113,113,.35); color:#fecaca; padding:14px; border-radius:12px; white-space:pre-wrap; font-size:13px; }
  .tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
  .tabs a { text-decoration:none; font-size:.9rem; color:#bcd0ef; background:#16213a; border:1px solid var(--line); padding:9px 16px; border-radius:10px; }
  .tabs a.active { background:linear-gradient(135deg,var(--accent),var(--violet)); color:#fff; font-weight:700; border:0; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:14px; }
  .pcard { background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line); border-radius:14px; overflow:hidden; position:relative; }
  .pcard img { width:100%; height:150px; object-fit:cover; display:block; background:#0a1124; }
  .pcard .who { position:absolute; left:8px; top:8px; background:rgba(124,58,237,.85); color:#fff; font-size:11px; font-weight:700; padding:3px 9px; border-radius:14px; }
  .pcard .cap { padding:8px 10px; font-size:11px; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .pcard .pick { position:absolute; top:8px; right:8px; width:24px; height:24px; z-index:2; cursor:pointer; accent-color:var(--accent); }
  .pager { display:flex; gap:10px; justify-content:center; align-items:center; padding:22px; flex-wrap:wrap; }
  .pager a, .pager span { padding:9px 16px; border-radius:11px; background:#16213a; border:1px solid var(--line); color:var(--ink); text-decoration:none; font-size:.9rem; }
  .pager .cur { background:linear-gradient(135deg,var(--accent),var(--violet)); font-weight:700; border:0; }
  tr.click { cursor:pointer; } tr.click:hover td { background:rgba(79,140,255,.07); }
  tr.click td a { color:#dbe6f7; text-decoration:none; font-weight:600; }
  .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }
  .toolbar label { font-size:.9rem; color:#cbd8ef; display:flex; gap:7px; align-items:center; cursor:pointer; }
  .toolbar .spacer { flex:1; }
  .subhead { font-size:15px; color:#cbd8ef; margin-bottom:12px; }
  .subhead a { color:var(--accent); text-decoration:none; }
  .btn-trash { border:0; padding:11px 18px; border-radius:11px; font-size:.9rem; font-weight:700; cursor:pointer; color:#fff; background:linear-gradient(135deg,#ef4444,#b91c1c); }
  .btn-assign { border:0; padding:11px 18px; border-radius:11px; font-size:.9rem; font-weight:700; cursor:pointer; color:#fff; background:linear-gradient(135deg,var(--accent),var(--violet)); }
  .usel { padding:10px 12px; border-radius:11px; background:#0b1220; color:var(--ink); border:1px solid var(--line); font-size:.9rem; max-width:220px; }
  .who.none { background:rgba(251,191,36,.85); color:#3a2a00; }
  .amsg { background:rgba(52,211,153,.15); border:1px solid rgba(52,211,153,.35); color:#bbf7d0; padding:11px 14px; border-radius:12px; margin-bottom:16px; font-size:14px; }
  .manage { background:linear-gradient(160deg,var(--panel),var(--panel2)); border:1px solid var(--line); border-radius:16px; padding:16px; margin-bottom:16px; display:flex; gap:18px; flex-wrap:wrap; align-items:flex-end; }
  .manage .grp { display:flex; flex-direction:column; gap:6px; }
  .manage label { font-size:12px; color:var(--muted); }
  .manage .bdel { border:0; padding:11px 16px; border-radius:11px; font-weight:700; cursor:pointer; color:#fff; background:linear-gradient(135deg,#7c2d12,#431407); }
  .badge-admin { background:rgba(124,58,237,.2); border:1px solid rgba(124,58,237,.55); color:#c4b5fd; }
  .bmini { margin-left:8px; padding:6px 12px; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; color:#bcd0ef; background:#16213a; border:1px solid var(--line); }
  .bmini.bmk { background:linear-gradient(135deg,var(--accent),var(--violet)); color:#fff; border:0; }
</style>
<script>
  function goUser(id){ location.href = 'admin.php?view=photos&user=' + id; }
  function toggleAllPhotos(cb){ document.querySelectorAll('input[name="ids[]"]').forEach(x => x.checked = cb.checked); }
  function confirmTrash(){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo.'); return false; }
    return confirm('Mettre ' + n + ' photo(s) à la corbeille ? (récupérables 30 jours)');
  }
  function confirmAssign(){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une photo.'); return false; }
    var sel = document.querySelector('select[name="target"]');
    if (!sel || !sel.value){ alert('Choisis d\'abord un compte de destination.'); return false; }
    var name = sel.options[sel.selectedIndex].text;
    return confirm('Attribuer ' + n + ' photo(s) au compte « ' + name + ' » ?');
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
        <a href="admin.php?view=photos" class="<?= ($view === 'photos' && $filterUser === 0 && !$unassigned) ? 'active' : '' ?>">🖼️ Toutes les photos</a>
        <a href="admin.php?view=photos&unassigned=1" class="<?= $unassigned ? 'active' : '' ?>">📂 Sans compte<?= $totalUnassigned > 0 ? ' (' . $totalUnassigned . ')' : '' ?></a>
        <a href="maintenance.php">🔧 Maintenance</a>
      </div>

      <?php if ($adminMsg): ?><div class="amsg"><?= htmlspecialchars($adminMsg) ?></div><?php endif; ?>

      <?php if ($view === 'users'): ?>
        <?php if (!$users): ?>
          <div class="empty">Aucun inscrit pour l'instant.</div>
        <?php else: ?>
          <p class="subhead">Clique sur un inscrit pour voir (et gérer) ses photos.</p>
          <table>
            <thead>
              <tr><th>#</th><th>Identifiant</th><th>Inscrit le</th><th class="num">Photos</th><th class="num">Corbeille</th><th>Rôle</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): $uid = (int) $u['id']; $uIsAdmin = (int) $u['is_admin'] === 1; $isMe = ($gid !== null && $uid === (int) $gid); ?>
                <tr class="click" onclick="goUser(<?= $uid ?>)">
                  <td><?= $uid ?></td>
                  <td>
                    <a href="admin.php?view=photos&user=<?= $uid ?>"><?= htmlspecialchars($u['username']) ?></a><?= $isMe ? ' <span class="badge">toi</span>' : '' ?>
                    <?php if (!empty($u['email'])): ?><br><small style="color:#64748b"><?= htmlspecialchars($u['email']) ?></small><?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(Photos::frDate($u['created_at'])) ?></td>
                  <td class="num"><span class="badge"><?= (int) $u['active'] ?></span></td>
                  <td class="num"><span class="badge"><?= (int) $u['trashed'] ?></span></td>
                  <td onclick="event.stopPropagation()">
                    <?php if ($uIsAdmin): ?>
                      <span class="badge badge-admin">⭐ Admin</span>
                      <form method="post" style="display:inline" onsubmit="return confirm('Retirer les droits admin à <?= htmlspecialchars($u['username'], ENT_QUOTES) ?> ?')">
                        <input type="hidden" name="action" value="set_admin">
                        <input type="hidden" name="uid" value="<?= $uid ?>">
                        <input type="hidden" name="admin" value="0">
                        <button type="submit" class="bmini">Retirer</button>
                      </form>
                    <?php else: ?>
                      <form method="post" style="display:inline" onsubmit="return confirm('Rendre <?= htmlspecialchars($u['username'], ENT_QUOTES) ?> administrateur ?')">
                        <input type="hidden" name="action" value="set_admin">
                        <input type="hidden" name="uid" value="<?= $uid ?>">
                        <input type="hidden" name="admin" value="1">
                        <button type="submit" class="bmini bmk">⭐ Rendre admin</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

      <?php else: /* vue photos */ ?>
        <?php if ($filterUser > 0): ?>
          <p class="subhead">📁 Photos de <b><?= htmlspecialchars($filterName ?: ('compte #' . $filterUser)) ?></b>
             &nbsp;·&nbsp; <a href="admin.php">← tous les inscrits</a></p>
          <div class="manage">
            <form method="post" class="grp" onsubmit="return confirm('SUPPRIMER ce compte ET toutes ses photos ? Action irréversible.')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="uid" value="<?= $filterUser ?>">
              <label>Compte</label>
              <button class="bdel" type="submit">🗑 Supprimer ce compte</button>
            </form>
          </div>
        <?php elseif ($unassigned): ?>
          <p class="subhead">📂 Photos <b>sans compte</b> (orphelines) &nbsp;·&nbsp;
             <a href="admin.php?view=photos">← toutes les photos</a></p>
        <?php endif; ?>

        <?php if (!$photos): ?>
          <div class="empty"><?= $unassigned ? 'Aucune photo sans compte 🎉' : ('Aucune photo' . ($filterUser > 0 ? ' pour ce compte' : ' sur le serveur') . " pour l'instant.") ?></div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="user" value="<?= $filterUser ?>">
            <input type="hidden" name="unassigned" value="<?= $unassigned ? 1 : 0 ?>">
            <input type="hidden" name="p" value="<?= $page ?>">
            <div class="toolbar">
              <label><input type="checkbox" onclick="toggleAllPhotos(this)"> Tout sélectionner</label>
              <span class="spacer"></span>
              <?php if ($allUsers): ?>
                <select name="target" class="usel" title="Compte de destination">
                  <option value="">— attribuer à… —</option>
                  <?php foreach ($allUsers as $au): ?>
                    <option value="<?= (int) $au['id'] ?>"><?= htmlspecialchars($au['username']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn-assign" type="submit" name="action" value="assign" onclick="return confirmAssign()">👤 Attribuer</button>
              <?php endif; ?>
              <button class="btn-trash" type="submit" name="action" value="trash" onclick="return confirmTrash()">🗑 Corbeille</button>
            </div>
            <div class="grid">
              <?php foreach ($photos as $p): $id = (int) $p['id']; $owner = $p['username'] ?? null; ?>
                <div class="pcard">
                  <input class="pick" type="checkbox" name="ids[]" value="<?= $id ?>" title="Sélectionner">
                  <?php if ($filterUser === 0): ?><span class="who <?= $owner === null ? 'none' : '' ?>"><?= $owner === null ? '— sans compte' : htmlspecialchars($owner) ?></span><?php endif; ?>
                  <a href="../api/media.php?id=<?= $id ?>" target="_blank" title="<?= htmlspecialchars($p['original_name']) ?>">
                    <img loading="lazy" src="../api/media.php?id=<?= $id ?>&amp;thumb=1" alt="">
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
