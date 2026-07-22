<?php
// === Maintenance : entrées BDD sans fichier physique ===
//   https://luvumbu.com/web/maintenance.php
// Parcourt la table des photos et repère les lignes dont le fichier n'existe
// PLUS sur le disque (uploads/ ou corbeille). Propose de les supprimer de la base.
//   Accès : réservé à l'admin (connexion via admin.php — mot de passe de la BDD).

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

// Accès réservé : il faut être passé par la connexion admin.
if (empty($_SESSION['admin_ok'])) {
    header('Location: admin.php');
    exit;
}

/** Taille lisible (Ko/Mo). */
function humanSize(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' Mo';
    if ($b >= 1024)    return round($b / 1024) . ' Ko';
    return $b . ' o';
}

/**
 * Fichiers présents dans uploads/ mais SANS ligne en base (espace perdu).
 * Renvoie une liste de ['rel','abs','size','trash'].
 */
function scanOrphanFiles(): array {
    $root = realpath(UPLOAD_DIR);
    if ($root === false) return [];

    // Chemins connus de la base (stored_path), normalisés.
    $known = [];
    foreach (Db::pdo()->query('SELECT stored_path FROM ' . TBL_PHOTOS)->fetchAll(PDO::FETCH_COLUMN) as $sp) {
        $known[str_replace('\\', '/', (string) $sp)] = true;
    }

    $orphans = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $abs = $file->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($root))), '/');
        if ($rel === '') continue;
        if (strpos($rel, '.thumbs/') === 0) continue;     // cache de vignettes : régénéré
        $bn = basename($rel);
        if ($bn === '' || $bn[0] === '.') continue;        // .htaccess et autres fichiers cachés
        $isTrash = strpos($rel, '.corbeille/') === 0;
        $logical = $isTrash ? substr($rel, strlen('.corbeille/')) : $rel;
        if (isset($known[$logical])) continue;             // référencé en base => OK
        $orphans[] = ['rel' => $rel, 'abs' => $abs, 'size' => (int) $file->getSize(), 'trash' => $isTrash];
    }
    usort($orphans, fn($a, $b) => strcmp($a['rel'], $b['rel']));
    return $orphans;
}

$done = '';
$filesDone = '';
$dbError = '';

// --- Action : supprimer les entrées orphelines sélectionnées ---
if (($_POST['action'] ?? '') === 'purge' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    try {
        $n = 0;
        foreach (Request::ids() as $pid) {
            $uid = Photos::ownerId($pid);
            if ($uid > 0) { Photos::deleteForever($pid, $uid); $n++; } // gère fichier manquant proprement
        }
        $_SESSION['maint_done'] = $n;
    } catch (Throwable $e) { $_SESSION['maint_error'] = $e->getMessage(); }
    header('Location: maintenance.php');
    exit;
}
// --- Action : supprimer les FICHIERS orphelins (sans ligne en base) ---
if (($_POST['action'] ?? '') === 'purge_files' && !empty($_POST['files']) && is_array($_POST['files'])) {
    try {
        $root = realpath(UPLOAD_DIR);
        $orphanSet = [];
        foreach (scanOrphanFiles() as $o) $orphanSet[$o['rel']] = $o['abs'];
        $n = 0;
        foreach ($_POST['files'] as $rel) {
            $rel = (string) $rel;
            if (!isset($orphanSet[$rel])) continue; // on ne supprime QUE des orphelins reconfirmés
            $abs = realpath($orphanSet[$rel]);
            if ($abs !== false && $root !== false && strpos($abs, $root) === 0 && is_file($abs)) {
                @unlink($abs);
                $n++;
            }
        }
        $_SESSION['maint_files_done'] = $n;
    } catch (Throwable $e) { $_SESSION['maint_error'] = $e->getMessage(); }
    header('Location: maintenance.php');
    exit;
}
if (isset($_SESSION['maint_done']))       { $done = (int) $_SESSION['maint_done'];        unset($_SESSION['maint_done']); }
if (isset($_SESSION['maint_files_done'])) { $filesDone = (int) $_SESSION['maint_files_done']; unset($_SESSION['maint_files_done']); }
if (isset($_SESSION['maint_error']))      { $dbError = $_SESSION['maint_error'];          unset($_SESSION['maint_error']); }

// --- Scan : repérer les lignes sans fichier physique ---
$orphans = [];
$totalRows = 0;
if ($dbError === '') {
    try {
        Auth::ensureSchema();
        $db = Db::pdo();
        $sql = "SELECT p.id, p.original_name, p.stored_path, p.deleted_at, p.user_id, u.username
                FROM " . TBL_PHOTOS . " p
                LEFT JOIN " . TBL_USERS . " u ON u.id = p.user_id
                ORDER BY p.id ASC";
        foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totalRows++;
            if (!is_file(Photos::physicalPath($row))) $orphans[] = $row;
        }
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

// --- Scan inverse : fichiers sur le disque sans ligne en base ---
$orphanFiles = [];
$orphanFilesSize = 0;
if ($dbError === '') {
    try {
        $orphanFiles = scanOrphanFiles();
        foreach ($orphanFiles as $f) $orphanFilesSize += $f['size'];
    } catch (Throwable $e) { $dbError = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title>PhotoSync — Maintenance</title>
<style>
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,sans-serif; margin:0; background:#0b1220; color:#e2e8f0; }
  header { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:16px 20px;
           background:linear-gradient(135deg,#7c3aed,#4c1d95); position:sticky; top:0; flex-wrap:wrap; }
  header h1 { font-size:18px; margin:0; font-weight:700; }
  header a { color:#fff; text-decoration:none; font-size:13px; background:rgba(255,255,255,.15); padding:8px 12px; border-radius:20px; }
  .wrap { max-width:880px; margin:0 auto; padding:18px; }
  .ok { background:#0f3d22; border:1px solid #166534; color:#86efac; padding:14px; border-radius:10px; margin-bottom:16px; }
  .err { background:#3b0d0d; border:1px solid #7f1d1d; color:#fca5a5; padding:14px; border-radius:10px; white-space:pre-wrap; margin-bottom:16px; }
  .info { background:#16213a; padding:14px; border-radius:10px; color:#9fb3cd; font-size:14px; margin-bottom:16px; }
  table { width:100%; border-collapse:collapse; background:#16213a; border-radius:12px; overflow:hidden; }
  th, td { padding:11px 13px; text-align:left; font-size:13px; border-bottom:1px solid #243049; }
  th { background:#1e293b; color:#cbd5e1; text-transform:uppercase; letter-spacing:.4px; font-size:12px; }
  tr:last-child td { border-bottom:0; }
  .tag { font-size:11px; padding:2px 8px; border-radius:12px; }
  .tag.act { background:#1e3a5f; color:#93c5fd; } .tag.tr { background:#4a2410; color:#fbbf24; }
  .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
  .toolbar label { font-size:14px; color:#cbd5e1; display:flex; gap:7px; align-items:center; cursor:pointer; }
  .toolbar .spacer { flex:1; }
  .btn { border:0; padding:10px 16px; border-radius:10px; font-weight:700; cursor:pointer; color:#fff; background:#ef4444; }
  code { background:#0b1220; padding:1px 5px; border-radius:4px; font-size:12px; color:#cbd5e1; }
</style>
<script>
  function toggleAll(cb){ document.querySelectorAll('input[name="ids[]"]').forEach(x => x.checked = cb.checked); }
  function confirmPurge(){
    var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins une entrée.'); return false; }
    return confirm('Supprimer DÉFINITIVEMENT ' + n + ' entrée(s) de la base ? (le fichier est déjà absent du disque)');
  }
  function toggleAllFiles(cb){ document.querySelectorAll('input[name="files[]"]').forEach(x => x.checked = cb.checked); }
  function confirmPurgeFiles(){
    var n = document.querySelectorAll('input[name="files[]"]:checked').length;
    if (n === 0){ alert('Sélectionne au moins un fichier.'); return false; }
    return confirm('Supprimer DÉFINITIVEMENT ' + n + ' fichier(s) du disque ? (aucune entrée en base ne les référence)');
  }
</script>
</head>
<body>
  <header>
    <h1>🔧 PhotoSync — Maintenance</h1>
    <div><a href="admin.php">← Admin</a> &nbsp; <a href="admin.php?logout=1">Déconnexion</a></div>
  </header>
  <div class="wrap">
    <?php if ($done !== ''): ?>
      <div class="ok">✅ <?= (int) $done ?> entrée(s) orpheline(s) supprimée(s) de la base.</div>
    <?php endif; ?>
    <?php if ($dbError): ?>
      <div class="err">Erreur : <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <div class="info">
      Ce contrôle parcourt la base (<b><?= $totalRows ?></b> photo(s) au total) et repère les
      lignes dont le <b>fichier image n'existe plus</b> sur le disque (supprimé manuellement,
      transfert incomplet…). Supprimer ces entrées nettoie la base sans toucher à aucun vrai fichier.
    </div>

    <?php if (!$dbError && !$orphans): ?>
      <div class="ok">✅ Tout est cohérent : chaque entrée de la base a bien son fichier sur le disque.</div>
    <?php elseif (!$dbError): ?>
      <form method="post" onsubmit="return confirmPurge()">
        <input type="hidden" name="action" value="purge">
        <div class="toolbar">
          <label><input type="checkbox" onclick="toggleAll(this)" checked> Tout sélectionner</label>
          <span class="spacer"></span>
          <button class="btn" type="submit">🗑 Supprimer les entrées orphelines</button>
        </div>
        <table>
          <thead>
            <tr><th></th><th>#</th><th>Propriétaire</th><th>Nom</th><th>État</th><th>Chemin attendu</th></tr>
          </thead>
          <tbody>
            <?php foreach ($orphans as $o): $id = (int) $o['id']; ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= $id ?>" checked></td>
                <td><?= $id ?></td>
                <td><?= htmlspecialchars($o['username'] ?? '—') ?></td>
                <td><?= htmlspecialchars($o['original_name']) ?></td>
                <td><span class="tag <?= empty($o['deleted_at']) ? 'act' : 'tr' ?>"><?= empty($o['deleted_at']) ? 'active' : 'corbeille' ?></span></td>
                <td><code><?= htmlspecialchars($o['stored_path']) ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    <?php endif; ?>

    <h2 style="font-size:17px;margin:28px 0 10px;">🗂️ Fichiers sans entrée en base</h2>
    <?php if ($filesDone !== ''): ?>
      <div class="ok">✅ <?= (int) $filesDone ?> fichier(s) orphelin(s) supprimé(s) du disque.</div>
    <?php endif; ?>
    <div class="info">
      Fichiers présents dans <code>uploads/</code> mais qu'<b>aucune entrée en base ne référence</b>
      (restes d'envois interrompus, suppressions partielles…). Les supprimer libère de l'espace
      sans impacter les photos affichées.
    </div>

    <?php if (!$dbError && !$orphanFiles): ?>
      <div class="ok">✅ Aucun fichier orphelin : tout ce qui est sur le disque est référencé en base.</div>
    <?php elseif (!$dbError): ?>
      <form method="post" onsubmit="return confirmPurgeFiles()">
        <input type="hidden" name="action" value="purge_files">
        <div class="toolbar">
          <label><input type="checkbox" onclick="toggleAllFiles(this)" checked> Tout sélectionner</label>
          <span class="spacer"></span>
          <span style="color:#9fb3cd;font-size:13px;"><?= count($orphanFiles) ?> fichier(s) · <?= humanSize($orphanFilesSize) ?></span>
          <button class="btn" type="submit">🗑 Supprimer les fichiers orphelins</button>
        </div>
        <table>
          <thead>
            <tr><th></th><th>Fichier</th><th>Emplacement</th><th>Taille</th></tr>
          </thead>
          <tbody>
            <?php foreach ($orphanFiles as $f): ?>
              <tr>
                <td><input type="checkbox" name="files[]" value="<?= htmlspecialchars($f['rel'], ENT_QUOTES) ?>" checked></td>
                <td><code><?= htmlspecialchars($f['rel']) ?></code></td>
                <td><span class="tag <?= $f['trash'] ? 'tr' : 'act' ?>"><?= $f['trash'] ? 'corbeille' : 'uploads' ?></span></td>
                <td><?= humanSize($f['size']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
