<?php
// === Lecteur en ligne (image / vidéo / audio) ===
//   view.php?id=12   — lit le fichier dans le navigateur (streaming via api/media.php).
//   Accès : session web du compte (chaque membre ne voit que ses fichiers).

require __DIR__ . '/../lib/bootstrap.php';

$sess = Auth::webSession('view.php');
$uid  = $sess['uid'];
if (!$uid) { header('Location: gallery.php'); exit; }

$isAdmin = Auth::isAdmin((int) $uid);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) { header('Location: gallery.php'); exit; }

// Récupère le fichier (l'admin peut tout voir ; sinon seulement le sien).
if ($isAdmin) {
    $st = Db::pdo()->prepare('SELECT original_name, stored_path FROM ' . TBL_PHOTOS . ' WHERE id = ?');
    $st->execute([$id]);
} else {
    $st = Db::pdo()->prepare('SELECT original_name, stored_path FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
}
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo 'Fichier introuvable'; exit; }

$name  = (string) $row['original_name'];
$cat   = Photos::categoryOf($name, (string) $row['stored_path']);
$media = '../api/media.php?id=' . $id;   // streaming (avec en-têtes Range pour la vidéo)
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title><?= htmlspecialchars($name) ?> — PhotoSync</title>
<style>
  :root{ --bg:#0b1220; --line:#22304f; --ink:#e6edf7; --muted:#8da2c0; --accent:#4f8cff; }
  * { box-sizing:border-box; }
  body { margin:0; min-height:100vh; display:flex; flex-direction:column; color:var(--ink);
         font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
         background:radial-gradient(1200px 600px at 10% -10%, #1b2c52 0%, transparent 55%), var(--bg); }
  header { display:flex; align-items:center; gap:12px; padding:14px 22px; border-bottom:1px solid var(--line);
           background:rgba(8,14,28,.7); -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px); }
  header a.back { text-decoration:none; color:#bcd0ef; background:#16213a; border:1px solid var(--line);
                  padding:8px 13px; border-radius:10px; font-size:.9rem; }
  header a.back:hover { border-color:var(--accent); }
  header .title { font-size:15px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; }
  header a.dl { text-decoration:none; color:#fff; background:linear-gradient(135deg,#2563eb,#1565C0);
                padding:9px 16px; border-radius:10px; font-weight:700; font-size:.9rem; }
  main { flex:1; display:flex; align-items:center; justify-content:center; padding:22px; }
  .stage { width:100%; max-width:1000px; text-align:center; }
  video, img { max-width:100%; max-height:80vh; border-radius:14px; background:#000;
               box-shadow:0 18px 50px rgba(0,0,0,.6); }
  audio { width:100%; max-width:560px; }
  .audio-wrap { background:linear-gradient(160deg,#111c33,#0e1830); border:1px solid var(--line);
                border-radius:18px; padding:42px 28px; }
  .audio-ico { font-size:64px; margin-bottom:18px; }
  .fname { color:var(--muted); margin-top:16px; font-size:14px; word-break:break-word; }
</style>
</head>
<body>
  <header>
    <a class="back" href="javascript:history.length>1?history.back():location.href='gallery.php'">‹ Retour</a>
    <span class="title"><?= htmlspecialchars($name) ?></span>
    <a class="dl" href="<?= $media ?>" download="<?= htmlspecialchars($name) ?>">⬇️ Télécharger</a>
  </header>
  <main>
    <div class="stage">
      <?php if ($cat === 'video'): ?>
        <video controls autoplay playsinline preload="metadata">
          <source src="<?= $media ?>">
          Ton navigateur ne peut pas lire cette vidéo. <a href="<?= $media ?>" download>Télécharger</a>.
        </video>
      <?php elseif ($cat === 'audio'): ?>
        <div class="audio-wrap">
          <div class="audio-ico">🎵</div>
          <audio controls autoplay preload="metadata">
            <source src="<?= $media ?>">
            Ton navigateur ne peut pas lire cet audio. <a href="<?= $media ?>" download>Télécharger</a>.
          </audio>
          <div class="fname"><?= htmlspecialchars($name) ?></div>
        </div>
      <?php elseif ($cat === 'photo'): ?>
        <img src="<?= $media ?>" alt="<?= htmlspecialchars($name) ?>">
      <?php else: ?>
        <div class="audio-wrap">
          <div class="audio-ico">📄</div>
          <div class="fname"><?= htmlspecialchars($name) ?></div>
          <p style="margin-top:18px"><a class="dl" href="<?= $media ?>" download="<?= htmlspecialchars($name) ?>">⬇️ Télécharger le fichier</a></p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
