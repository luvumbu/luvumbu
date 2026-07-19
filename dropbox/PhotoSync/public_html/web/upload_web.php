<?php
// === Page web d'envoi de photos (idéale pour iPhone, sans app) ===
//   https://luvumbu.com/web/upload_web.php
// Connexion par compte (même identifiants que la galerie), puis sélection
// de photos depuis l'appareil et envoi vers le compte.

require __DIR__ . '/../lib/bootstrap.php';

// Connexion / déconnexion (session web partagée avec gallery.php).
$sess  = Auth::webSession('upload_web.php');
$uid   = $sess['uid'];
$uname = $sess['uname'];
$error = $sess['error'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<title>PhotoSync — Envoyer des photos</title>
<style>
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,sans-serif; margin:0; background:#0b1220; color:#e2e8f0; }
  .wrap { max-width:520px; margin:0 auto; padding:20px; }
  h1 { font-size:22px; }
  .card { background:#16213a; border-radius:16px; padding:22px; box-shadow:0 8px 24px rgba(0,0,0,.4); }
  input[type=text], input[type=password] { width:100%; padding:13px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:16px; margin-top:10px; }
  .btn { display:inline-block; width:100%; margin-top:14px; padding:14px; border:0; border-radius:12px; background:#1565C0; color:#fff; font-size:16px; font-weight:700; cursor:pointer; text-align:center; }
  .file { width:100%; margin-top:8px; color:#cbd5e1; }
  .pickzone { border:2px dashed #334155; border-radius:14px; padding:22px; text-align:center; color:#8aa0bd; }
  .err { color:#f87171; margin-top:10px; }
  .row { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
  .row a { color:#93c5fd; text-decoration:none; font-size:14px; }
  #log { margin-top:16px; font-size:14px; }
  .bar { height:10px; background:#0b1220; border-radius:8px; overflow:hidden; margin-top:14px; }
  .bar > div { height:100%; width:0; background:#16a34a; transition:width .2s; }
  .ok { color:#34d399; } .dup { color:#fbbf24; } .ko { color:#f87171; }
</style>
</head>
<body>
<div class="wrap">
  <h1>📤 PhotoSync</h1>

  <?php if (!$uid): ?>
    <div class="card">
      <p>Connecte-toi à ton compte pour envoyer des photos.</p>
      <form method="post">
        <input type="text" name="username" placeholder="Identifiant" autofocus required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button class="btn" type="submit">Se connecter</button>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      </form>
      <p style="margin-top:16px;text-align:center;color:#64748b;font-size:13px;">
        Pas encore de compte ? <a href="register.php" style="color:#93c5fd;text-decoration:none;">Créer un compte</a>
      </p>
    </div>
  <?php else: ?>
    <div class="row">
      <span>Connecté : <b><?= htmlspecialchars($uname) ?></b></span>
      <span><a href="gallery.php">Galerie</a> &nbsp;·&nbsp; <a href="upload_web.php?logout=1">Déconnexion</a></span>
    </div>
    <div class="card">
      <label class="pickzone" for="files">
        📎 Touche ici pour <b>choisir des fichiers</b><br>
        <small>(photos, vidéos, documents… plusieurs possibles)</small>
        <input id="files" class="file" type="file" accept="*/*" multiple style="display:none">
      </label>
      <button type="button" id="browse" class="btn" style="background:#334155;margin-top:10px;">📁 Choisir un fichier (ordinateur)</button>
      <div id="count" style="margin-top:12px;color:#8aa0bd;"></div>
      <button id="send" class="btn" disabled>Envoyer</button>
      <div class="bar"><div id="progress"></div></div>
      <div id="log"></div>
    </div>
  <?php endif; ?>
</div>

<?php if ($uid): ?>
<script>
  const input = document.getElementById('files');
  const sendBtn = document.getElementById('send');
  const countEl = document.getElementById('count');
  const logEl = document.getElementById('log');
  const bar = document.getElementById('progress');

  // Bouton explicite « Choisir un fichier » : ouvre l'explorateur (bureau) / le sélecteur (mobile).
  document.getElementById('browse').addEventListener('click', () => input.click());

  input.addEventListener('change', () => {
    const n = input.files.length;
    countEl.textContent = n ? n + ' fichier(s) sélectionné(s)' : '';
    sendBtn.disabled = n === 0;
  });

  sendBtn.addEventListener('click', async () => {
    const files = Array.from(input.files);
    if (!files.length) return;
    sendBtn.disabled = true;
    input.disabled = true;
    let ok = 0, dup = 0, ko = 0;

    for (let i = 0; i < files.length; i++) {
      const f = files[i];
      const fd = new FormData();
      fd.append('photo', f, f.name);
      fd.append('taken_at', String(f.lastModified || 0));
      try {
        const r = await fetch('../api/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (j.ok && j.duplicate) dup++;
        else if (j.ok) ok++;
        else ko++;
      } catch (e) { ko++; }
      bar.style.width = Math.round(((i + 1) / files.length) * 100) + '%';
      logEl.innerHTML = `<span class="ok">✅ ${ok} envoyée(s)</span> · ` +
                        `<span class="dup">⏭️ ${dup} déjà présente(s)</span> · ` +
                        `<span class="ko">❌ ${ko} échec(s)</span> &nbsp;(${i + 1}/${files.length})`;
    }
    logEl.innerHTML += '<br><br><b>Terminé !</b> <a href="gallery.php" style="color:#93c5fd;">Voir la galerie ›</a>';
    input.disabled = false;
  });
</script>
<?php endif; ?>
</body>
</html>
