<?php
/**
 * admin.php — Espace d'administration interne de ztransfert
 *
 * Permet de gérer TOUS les transferts : lister, télécharger et supprimer
 * les fichiers, aussi bien ceux enregistrés en base que les fichiers
 * « orphelins » présents sur le disque mais absents de la base.
 *
 * ⚠ À FAIRE AVANT MISE EN LIGNE : changer ADMIN_PASSWORD ci-dessous.
 */

session_start();
error_reporting(E_ERROR | E_PARSE);
mysqli_report(MYSQLI_REPORT_OFF); // PHP 8.1+ : on gère les erreurs à la main

// ───────────────────────── CONFIG ─────────────────────────
// L'admin se connecte avec ses IDENTIFIANTS MySQL (comme l'admin luvumbu) :
//   - en local : root + (mot de passe vide)
//   - en prod  : u489596434_marion + v3p9r3e@59A
define('DB_HOST', 'localhost');
define('DB_USER', 'u489596434_marion'); // identifiants du projet (secours)
define('DB_PASS', 'v3p9r3e@59A');
define('DB_NAME', 'u489596434_marion'); // (user == nom de base, convention du projet)
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads');

// ───────────────────────── AUTH ─────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION['zt_admin'] = false;
    unset($_SESSION['zt_admin']);
    header('Location: admin.php');
    exit;
}

$login_error = '';
if (isset($_POST['admin_user']) || isset($_POST['admin_password'])) {
    $tryUser = trim((string)($_POST['admin_user'] ?? ''));
    $tryPw   = trim((string)($_POST['admin_password'] ?? '')); // tolère espaces (copier-coller)
    $ok = false;

    // 1) Connexion MySQL RÉELLE avec l'utilisateur + mot de passe tapés
    //    (même principe que l'admin luvumbu). Si MySQL accepte → admin.
    if (function_exists('mysqli_connect') && $tryUser !== '') {
        $c = @mysqli_connect(DB_HOST, $tryUser, $tryPw, DB_NAME);
        if ($c) { $ok = true; @mysqli_close($c); }
    }
    // 2) Secours : comparaison directe aux identifiants du projet
    if (!$ok && $tryUser === DB_USER && hash_equals(DB_PASS, $tryPw)) {
        $ok = true;
    }

    if ($ok) {
        $_SESSION['zt_admin'] = true;
    } else {
        $login_error = 'Identifiant ou mot de passe incorrect.';
    }
}
$is_logged = !empty($_SESSION['zt_admin']);

// Jeton anti-CSRF pour les actions destructrices
if (empty($_SESSION['zt_csrf'])) {
    $_SESSION['zt_csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['zt_csrf'];

// ───────────────────────── HELPERS ─────────────────────────
function human_size($bytes) {
    if ($bytes <= 0) return '0 o';
    $u = ['o', 'Ko', 'Mo', 'Go', 'To'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($u) - 1);
    return round($bytes / pow(1024, $i), 2) . ' ' . $u[$i];
}

/**
 * Renvoie le chemin absolu SÛR d'un fichier de uploads/, ou false si le
 * nom tente de sortir du dossier (protection contre les ../ et chemins absolus).
 */
function safe_upload_path($filename) {
    $base = basename($filename); // supprime tout composant de dossier
    if ($base === '' || $base === '.' || $base === '..') return false;
    $full = UPLOAD_DIR . DIRECTORY_SEPARATOR . $base;
    $real = realpath($full);
    $realDir = realpath(UPLOAD_DIR);
    if ($real === false || $realDir === false) return false;
    // Le fichier doit bien être DANS le dossier uploads
    if (strpos($real, $realDir . DIRECTORY_SEPARATOR) !== 0) return false;
    return $real;
}

function db_connect() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) return null;
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ───────────────────────── ACTIONS (POST) ─────────────────────────
$flash = '';
$is_delete_post = $is_logged && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['action']) || isset($_POST['del_one']) || isset($_POST['del_one_orphan']));

if ($is_delete_post) {
    // Vérification CSRF
    if (!isset($_POST['csrf']) || !hash_equals($CSRF, (string)$_POST['csrf'])) {
        $flash = '⛔ Jeton de sécurité invalide, action annulée.';
    } else {
        $act = $_POST['action'] ?? '';

        // Transferts (base) à supprimer : sélection groupée OU suppression simple
        $ids = [];
        if (isset($_POST['del_one'])) {
            $ids[] = (int)$_POST['del_one'];
        } elseif ($act === 'bulk_delete_transfers') {
            foreach ((array)($_POST['ids'] ?? []) as $v) $ids[] = (int)$v;
        }
        $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

        // Fichiers orphelins (disque) à supprimer : groupée OU simple
        $files = [];
        if (isset($_POST['del_one_orphan'])) {
            $files[] = (string)$_POST['del_one_orphan'];
        } elseif ($act === 'bulk_delete_orphans') {
            foreach ((array)($_POST['files'] ?? []) as $v) $files[] = (string)$v;
        }

        $nbT = 0; $nbF = 0;

        // Suppression des transferts (fichier sur disque + ligne en base)
        if ($ids) {
            $conn = db_connect();
            if ($conn) {
                $sel = $conn->prepare('SELECT file_path FROM we_transfert WHERE id_transfert = ? LIMIT 1');
                $del = $conn->prepare('DELETE FROM we_transfert WHERE id_transfert = ?');
                foreach ($ids as $id) {
                    $sel->bind_param('i', $id);
                    $sel->execute();
                    $res = $sel->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    if ($row) {
                        $path = safe_upload_path($row['file_path']);
                        if ($path && is_file($path)) @unlink($path);
                        $del->bind_param('i', $id);
                        $del->execute();
                        $nbT++;
                    }
                }
                $sel->close();
                $del->close();
                $conn->close();
            }
        }

        // Suppression des fichiers orphelins
        foreach ($files as $f) {
            $path = safe_upload_path($f);
            if ($path && is_file($path)) { @unlink($path); $nbF++; }
        }

        if ($nbT || $nbF) {
            $parts = [];
            if ($nbT) $parts[] = $nbT . ' transfert(s)';
            if ($nbF) $parts[] = $nbF . ' fichier(s) orphelin(s)';
            $flash = '✅ Supprimé : ' . implode(' + ', $parts) . '.';
        } else {
            $flash = '⚠ Rien à supprimer (aucune sélection ?).';
        }
    }
}

// ───────────────────────── DONNÉES ─────────────────────────
$transfers = [];
$db_basenames = [];
$db_error = '';
if ($is_logged) {
    $conn = db_connect();
    if (!$conn) {
        $db_error = 'Connexion à la base impossible (vérifie les identifiants DB).';
    } else {
        $result = $conn->query('SELECT id_transfert, file_path, total, name, date_inscription_user FROM we_transfert ORDER BY id_transfert DESC');
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $bn = basename($r['file_path']);
                $db_basenames[$bn] = true;
                $abs = UPLOAD_DIR . DIRECTORY_SEPARATOR . $bn;
                $r['_basename'] = $bn;
                $r['_exists']   = is_file($abs);
                $r['_size']     = $r['_exists'] ? filesize($abs) : 0;
                $transfers[] = $r;
            }
        }
        $conn->close();
    }
}

// Fichiers orphelins : présents sur le disque mais pas référencés en base
$orphans = [];
$total_disk_size = 0;
if ($is_logged && is_dir(UPLOAD_DIR)) {
    foreach (scandir(UPLOAD_DIR) as $f) {
        if ($f === '.' || $f === '..') continue;
        $abs = UPLOAD_DIR . DIRECTORY_SEPARATOR . $f;
        if (!is_file($abs)) continue;
        $sz = filesize($abs);
        $total_disk_size += $sz;
        if (empty($db_basenames[$f])) {
            $orphans[] = ['name' => $f, 'size' => $sz];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — ztransfert</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🛠️</text></svg>">
<style>
  :root{
    --bg:#030b17; --card:#0a1a2f; --text:#cbdfff; --muted:#7d97bd;
    --accent:#2f80ed; --border:#173251; --danger:#e74c3c; --ok:#2ecc71;
  }
  *{box-sizing:border-box;}
  body{margin:0;font-family:'Segoe UI',Arial,sans-serif;background:var(--bg);color:var(--text);padding:24px;}
  a{color:var(--accent);}
  h1{margin:0 0 4px;font-size:1.5rem;}
  .top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
  .btn{display:inline-block;padding:8px 16px;border-radius:8px;border:1px solid var(--border);
       background:rgba(47,128,237,.12);color:var(--text);text-decoration:none;cursor:pointer;font-size:.9rem;}
  .btn:hover{background:rgba(47,128,237,.25);}
  .btn-danger{border-color:#5a1e1e;background:rgba(231,76,60,.12);color:#ffb4ab;}
  .btn-danger:hover{background:rgba(231,76,60,.28);}
  .stats{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;}
  .stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px 22px;min-width:150px;}
  .stat .n{font-size:1.8rem;font-weight:800;}
  .stat .l{color:var(--muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;}
  .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:24px;overflow-x:auto;}
  h2{font-size:1.1rem;margin:0 0 14px;}
  table{width:100%;border-collapse:collapse;min-width:640px;}
  th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);font-size:.9rem;vertical-align:middle;}
  th{color:var(--muted);text-transform:uppercase;font-size:.75rem;letter-spacing:.05em;}
  tr:hover td{background:rgba(47,128,237,.05);}
  .tag{font-size:.72rem;padding:2px 8px;border-radius:20px;background:rgba(231,76,60,.18);color:#ffb4ab;}
  .muted{color:var(--muted);}
  .flash{background:rgba(46,204,113,.12);border:1px solid #1e5a3a;padding:12px 16px;border-radius:10px;margin-bottom:20px;}
  .empty{color:var(--muted);padding:8px 0;}
  form.inline{display:inline;}
  .chkcol{width:34px;text-align:center;}
  input[type=checkbox]{width:17px;height:17px;accent-color:var(--accent);cursor:pointer;vertical-align:middle;}
  .bulkbar{display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);}
  .selall-lbl{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:.9rem;cursor:pointer;}
  /* Login */
  .login{max-width:360px;margin:12vh auto 0;background:var(--card);border:1px solid var(--border);
         border-radius:16px;padding:32px;text-align:center;}
  .login input{width:100%;padding:12px;border-radius:8px;border:1px solid var(--border);
               background:#06111f;color:var(--text);font-size:1rem;margin:16px 0;}
  .login .btn{width:100%;background:var(--accent);color:#fff;border:none;padding:12px;font-weight:700;}
  .err{color:#ffb4ab;font-size:.9rem;margin-top:8px;}
</style>
</head>
<body>

<?php if (!$is_logged): ?>

  <form class="login" method="post" autocomplete="off">
    <h1>🛠️ Espace admin</h1>
    <p class="muted">Connexion avec vos identifiants MySQL<br>(local : <code>root</code> + mot de passe vide)</p>
    <input type="text" name="admin_user" placeholder="Utilisateur MySQL" autocomplete="off" autofocus>
    <input type="password" name="admin_password" placeholder="Mot de passe MySQL" autocomplete="off">
    <button class="btn" type="submit">Se connecter</button>
    <?php if ($login_error): ?><div class="err"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
  </form>

<?php else: ?>

  <div class="top">
    <div>
      <h1>🛠️ Espace admin</h1>
      <span class="muted">Gestion interne de tous les fichiers</span>
    </div>
    <div>
      <a class="btn" href="index.php#upload">➕ Envoyer un fichier</a>
      <a class="btn" href="?logout=1">Déconnexion</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="flash"><?= $flash /* messages déjà échappés */ ?></div><?php endif; ?>
  <?php if ($db_error): ?><div class="flash" style="background:rgba(231,76,60,.12);border-color:#5a1e1e;color:#ffb4ab;">⚠ <?= htmlspecialchars($db_error) ?></div><?php endif; ?>

  <div class="stats">
    <div class="stat"><div class="n"><?= count($transfers) ?></div><div class="l">Transferts en base</div></div>
    <div class="stat"><div class="n"><?= count($orphans) ?></div><div class="l">Fichiers orphelins</div></div>
    <div class="stat"><div class="n"><?= human_size($total_disk_size) ?></div><div class="l">Poids total disque</div></div>
  </div>

  <!-- TRANSFERTS EN BASE -->
  <div class="card">
    <h2>Transferts enregistrés (<?= count($transfers) ?>)</h2>
    <?php if (!$transfers): ?>
      <div class="empty">Aucun transfert en base.</div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $CSRF ?>">
        <table>
          <thead><tr>
            <th class="chkcol"><input type="checkbox" class="selall" data-group="t" title="Tout sélectionner"></th>
            <th>#</th><th>Nom (lien)</th><th>Fichier</th><th>Taille</th><th>Date</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($transfers as $t): ?>
            <tr>
              <td class="chkcol"><input type="checkbox" class="rowchk-t" name="ids[]" value="<?= (int)$t['id_transfert'] ?>"></td>
              <td><?= (int)$t['id_transfert'] ?></td>
              <td>
                <a href="all_doc.php?name=<?= rawurlencode($t['name']) ?>" target="_blank"><?= htmlspecialchars($t['name']) ?></a>
                <?php if (!$t['_exists']): ?> <span class="tag">fichier manquant</span><?php endif; ?>
              </td>
              <td class="muted"><?= htmlspecialchars($t['_basename']) ?></td>
              <td><?= $t['_exists'] ? human_size($t['_size']) : '—' ?></td>
              <td class="muted"><?= htmlspecialchars($t['date_inscription_user']) ?></td>
              <td>
                <?php if ($t['_exists']): ?>
                  <a class="btn" href="<?= htmlspecialchars($t['file_path']) ?>" download>Télécharger</a>
                <?php endif; ?>
                <button class="btn btn-danger" type="submit" name="del_one" value="<?= (int)$t['id_transfert'] ?>"
                        onclick="return confirm('Supprimer définitivement ce transfert et son fichier ?');">Supprimer</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="bulkbar">
          <label class="selall-lbl"><input type="checkbox" class="selall" data-group="t"> Tout sélectionner</label>
          <button class="btn btn-danger" type="submit" name="action" value="bulk_delete_transfers"
                  onclick="return confirmBulk('t','transfert(s) (fichier + base)');">🗑️ Supprimer la sélection</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- FICHIERS ORPHELINS -->
  <div class="card">
    <h2>Fichiers orphelins sur le disque (<?= count($orphans) ?>)</h2>
    <p class="muted" style="margin-top:-6px;font-size:.85rem;">Fichiers présents dans <code>uploads/</code> mais non référencés en base.</p>
    <?php if (!$orphans): ?>
      <div class="empty">Aucun fichier orphelin. 🎉</div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $CSRF ?>">
        <table>
          <thead><tr>
            <th class="chkcol"><input type="checkbox" class="selall" data-group="o" title="Tout sélectionner"></th>
            <th>Fichier</th><th>Taille</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($orphans as $o): ?>
            <tr>
              <td class="chkcol"><input type="checkbox" class="rowchk-o" name="files[]" value="<?= htmlspecialchars($o['name']) ?>"></td>
              <td><?= htmlspecialchars($o['name']) ?></td>
              <td><?= human_size($o['size']) ?></td>
              <td>
                <a class="btn" href="uploads/<?= rawurlencode($o['name']) ?>" download>Télécharger</a>
                <button class="btn btn-danger" type="submit" name="del_one_orphan" value="<?= htmlspecialchars($o['name']) ?>"
                        onclick="return confirm('Supprimer définitivement ce fichier ?');">Supprimer</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="bulkbar">
          <label class="selall-lbl"><input type="checkbox" class="selall" data-group="o"> Tout sélectionner</label>
          <button class="btn btn-danger" type="submit" name="action" value="bulk_delete_orphans"
                  onclick="return confirmBulk('o','fichier(s) orphelin(s)');">🗑️ Supprimer la sélection</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <script>
    // Cases « tout sélectionner » (en-tête ET bas de tableau, synchronisées par groupe)
    document.querySelectorAll('.selall').forEach(function (sa) {
      sa.addEventListener('change', function () {
        var g = sa.dataset.group;
        document.querySelectorAll('.rowchk-' + g).forEach(function (b) { b.checked = sa.checked; });
        document.querySelectorAll('.selall[data-group="' + g + '"]').forEach(function (o) { o.checked = sa.checked; });
      });
    });
    // Confirmation de suppression groupée + garde-fou si rien n'est coché
    function confirmBulk(group, label) {
      var n = document.querySelectorAll('.rowchk-' + group + ':checked').length;
      if (n === 0) { alert('Aucun élément sélectionné.'); return false; }
      return confirm('Supprimer définitivement ' + n + ' ' + label + ' ?');
    }
  </script>

<?php endif; ?>

</body>
</html>
