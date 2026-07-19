<?php
// === Page publique de partage d'un album ===
//   web/share.php?a=<token>
// Affiche les photos d'un album partagé. Si l'album a un mot de passe, le demande
// (déverrouillage mémorisé en session). Aucune connexion de compte nécessaire.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

$token = (string) ($_GET['a'] ?? '');
$album = Albums::byToken($token);

function shareError(string $msg): void {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;background:#0b1220;color:#e2e8f0;text-align:center;padding:60px;">'
       . '<h2>📁 Album indisponible</h2><p style="color:#8aa0bd;">' . htmlspecialchars($msg) . '</p></body>';
    exit;
}

if (!$album) shareError("Ce lien de partage n'existe pas ou a été supprimé.");

$needPass = !empty($album['pass_hash']);
$unlocked = empty($needPass) || !empty($_SESSION['album_ok'][$token]);

$err = '';
if ($needPass && !$unlocked && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_pass'])) {
    if (password_verify((string) $_POST['share_pass'], $album['pass_hash'])) {
        $_SESSION['album_ok'][$token] = true;
        header('Location: share.php?a=' . urlencode($token));
        exit;
    }
    $err = 'Mot de passe incorrect.';
}
$unlocked = empty($needPass) || !empty($_SESSION['album_ok'][$token]);

// ---- Demande de mot de passe ----
if (!$unlocked) {
    ?>
    <!doctype html><html lang="fr"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <title>Album protégé</title>
    <style>
      body { font-family:system-ui,sans-serif; background:#0b1220; color:#e2e8f0; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
      .card { background:#16213a; padding:28px; border-radius:16px; width:300px; box-shadow:0 10px 30px rgba(0,0,0,.5); }
      h1 { font-size:20px; margin:0 0 4px; } p.sub { color:#8aa0bd; font-size:13px; margin:0 0 18px; }
      input { width:100%; box-sizing:border-box; padding:12px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:15px; margin-top:10px; }
      button { width:100%; margin-top:14px; padding:12px; border:0; border-radius:10px; background:#1565C0; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
      .err { color:#f87171; font-size:13px; margin-top:10px; }
    </style></head>
    <body><form class="card" method="post">
      <h1>🔒 <?= htmlspecialchars($album['name']) ?></h1>
      <p class="sub">Cet album est protégé. Entre le mot de passe pour le voir.</p>
      <input type="password" name="share_pass" placeholder="Mot de passe" autofocus required>
      <button type="submit">Voir l'album</button>
      <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    </form></body></html>
    <?php
    exit;
}

// ---- Affichage de l'album ----
$photos = Albums::photos((int) $album['id']);
$tokQs = '&a=' . urlencode($token);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title><?= htmlspecialchars($album['name']) ?> — Album partagé</title>
<style>
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,sans-serif; margin:0; background:#0b1220; color:#e2e8f0; }
  header { padding:16px 20px; background:linear-gradient(135deg,#1565C0,#0b3a78); box-shadow:0 4px 20px rgba(0,0,0,.4); }
  header h1 { font-size:18px; margin:0; }
  header .sub { color:#cfe0ff; font-size:13px; margin-top:3px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:10px; padding:14px; max-width:1100px; margin:0 auto; }
  .cell { background:#16213a; border-radius:10px; overflow:hidden; }
  .cell img { width:100%; height:150px; object-fit:cover; display:block; background:#0f172a; }
  .empty { text-align:center; padding:60px 20px; color:#8aa0bd; }
  footer { text-align:center; color:#64748b; font-size:12px; padding:18px; }
</style>
</head>
<body>
  <header>
    <h1>📁 <?= htmlspecialchars($album['name']) ?></h1>
    <div class="sub"><?= count($photos) ?> photo(s) partagée(s)</div>
  </header>
  <?php if (!$photos): ?>
    <div class="empty">Cet album est vide pour l'instant.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($photos as $p): $id = (int) $p['id']; ?>
        <div class="cell">
          <a href="../api/media.php?id=<?= $id ?><?= $tokQs ?>" target="_blank" title="<?= htmlspecialchars($p['original_name']) ?>">
            <img loading="lazy" src="../api/media.php?id=<?= $id ?><?= $tokQs ?>&amp;thumb=micro" alt="">
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <footer>Partagé via PhotoSync</footer>
</body>
</html>
