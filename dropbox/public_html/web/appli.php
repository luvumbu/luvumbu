<?php
// === « Installer l'application » ===
// Explique comment ajouter PhotoSync à l'écran d'accueil, sans App Store ni
// Play Store. Page volontairement publique (aucune donnée affichée) : on peut
// donc envoyer le lien à quelqu'un pour qu'il installe l'app sur son téléphone.
//   https://luvumbu.com/dropbox/web/appli.php

require __DIR__ . '/../lib/bootstrap.php';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?= Pwa::head('..') ?>
<title>PhotoSync — Installer l'application</title>
<style>
  * { box-sizing:border-box; }
  body { margin:0; min-height:100vh; padding:24px 16px 48px; color:#e6edf7;
         font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
         background: radial-gradient(1200px 600px at 10% -10%, #1b2c52 0%, transparent 55%),
                     radial-gradient(900px 500px at 110% 10%, #143042 0%, transparent 50%), #0b1220; }
  .wrap { max-width:620px; margin:0 auto; }
  .hero { text-align:center; margin-bottom:26px; }
  .hero img { width:104px; height:104px; border-radius:26px; box-shadow:0 14px 40px rgba(79,140,255,.35); }
  h1 { font-size:24px; margin:16px 0 6px; font-weight:800; }
  .sub { color:#94a3b8; font-size:14.5px; line-height:1.6; margin:0; }
  .panel { background:rgba(22,33,58,.82); border:1px solid rgba(148,163,184,.16); border-radius:18px;
           padding:22px; margin-top:18px; box-shadow:0 14px 40px rgba(0,0,0,.4); }
  .panel h2 { font-size:17px; margin:0 0 4px; display:flex; align-items:center; gap:9px; }
  .panel .why { color:#8da2c0; font-size:13px; margin:0 0 16px; }
  ol { margin:0; padding-left:22px; }
  li { margin:11px 0; font-size:14.5px; line-height:1.6; color:#dbe6f7; }
  .key { display:inline-flex; align-items:center; gap:5px; background:rgba(79,140,255,.16);
         border:1px solid rgba(79,140,255,.4); border-radius:8px; padding:2px 8px; font-weight:700; color:#bfd4ff; }
  .ok { background:rgba(52,211,153,.12); border-color:rgba(52,211,153,.45); }
  .ok h2 { color:#6ee7b7; }
  .note { color:#8da2c0; font-size:13px; line-height:1.65; margin-top:14px; padding-top:14px; border-top:1px solid rgba(148,163,184,.14); }
  .btn { display:inline-block; border:0; cursor:pointer; text-decoration:none; text-align:center; color:#fff;
         font-weight:700; font-size:15px; padding:14px 22px; border-radius:13px;
         background:linear-gradient(135deg,#4f8cff,#a78bfa); }
  .btn.ghost { background:#16213a; border:1px solid #22304f; color:#bcd0ef; }
  .actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:24px; }
  .hidden { display:none; }
</style>
</head>
<body>
<div class="wrap">

  <div class="hero">
    <img src="../assets/icon-512.png?v=1" alt="Icône PhotoSync">
    <h1>Installer PhotoSync</h1>
    <p class="sub">PhotoSync s'installe directement depuis le navigateur : une vraie icône sur l'écran d'accueil, une ouverture en plein écran, sans barre d'adresse.
       <b>Aucun passage par l'App Store ni le Play Store.</b></p>
  </div>

  <!-- Déjà installée : on remplace tout le mode d'emploi par une confirmation. -->
  <div class="panel ok hidden" id="pInstalled">
    <h2>✅ C'est bon, tu y es</h2>
    <p class="why">Tu utilises PhotoSync depuis l'écran d'accueil. Rien à faire de plus.</p>
  </div>

  <div id="pSteps">
    <!-- iPhone / iPad : Safari n'a pas de bouton d'installation, il faut passer par Partager. -->
    <div class="panel" id="pIos">
      <h2>📱 iPhone / iPad</h2>
      <p class="why">À faire depuis <b>Safari</b> (Chrome ou Firefox sur iPhone ne savent pas installer).</p>
      <ol>
        <li>Ouvre cette page dans <b>Safari</b>.</li>
        <li>Touche le bouton <span class="key">Partager ⬆️</span> en bas de l'écran.</li>
        <li>Fais défiler, puis choisis <span class="key">Sur l'écran d'accueil</span>.</li>
        <li>Touche <span class="key">Ajouter</span> en haut à droite.</li>
      </ol>
      <p class="note">L'icône PhotoSync apparaît avec tes autres apps. Petite particularité d'iOS : l'app installée a sa propre session, tu devras te reconnecter une fois à l'intérieur — ensuite tu y restes.</p>
    </div>

    <!-- Android : Chrome propose l'installation, on la déclenche nous-mêmes si possible. -->
    <div class="panel" id="pAndroid">
      <h2>🤖 Android</h2>
      <p class="why">Depuis <b>Chrome</b> (ou Edge, Samsung Internet).</p>
      <ol>
        <li>Touche le menu <span class="key">⋮</span> en haut à droite.</li>
        <li>Choisis <span class="key">Installer l'application</span> (ou « Ajouter à l'écran d'accueil »).</li>
        <li>Confirme avec <span class="key">Installer</span>.</li>
      </ol>
    </div>

    <div class="panel" id="pDesktop">
      <h2>💻 Ordinateur (Chrome / Edge)</h2>
      <p class="why">PhotoSync s'ouvre alors dans sa propre fenêtre, comme un logiciel.</p>
      <ol>
        <li>Clique sur l'icône <span class="key">⊕ Installer</span> à droite de la barre d'adresse.</li>
        <li>Ou menu <span class="key">⋮</span> → <span class="key">Installer PhotoSync…</span></li>
      </ol>
    </div>
  </div>

  <div class="actions">
    <button class="btn hidden" id="btnInstall">⬇️ Installer maintenant</button>
    <a class="btn ghost" href="gallery.php">← Retour à la galerie</a>
  </div>

</div>

<script>
  // Déjà lancée depuis l'écran d'accueil ? On masque le mode d'emploi.
  if (<?= Pwa::isStandaloneJs() ?>) {
    document.getElementById('pInstalled').classList.remove('hidden');
    document.getElementById('pSteps').classList.add('hidden');
  } else {
    // On met en avant la fiche correspondant à l'appareil, sans masquer les autres :
    // l'utilisateur peut lire la marche à suivre pour le téléphone d'un proche.
    var ua = navigator.userAgent;
    var isIos = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var target = isIos ? 'pIos' : (/Android/.test(ua) ? 'pAndroid' : 'pDesktop');
    var el = document.getElementById(target);
    el.style.borderColor = 'rgba(79,140,255,.55)';
    el.parentNode.prepend(el);
  }

  // Chrome/Edge : on capte l'invite d'installation pour l'offrir sur un bouton.
  var deferred = null, btn = document.getElementById('btnInstall');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    btn.classList.remove('hidden');
  });
  btn.addEventListener('click', async function () {
    if (!deferred) return;
    deferred.prompt();
    await deferred.userChoice;
    deferred = null;
    btn.classList.add('hidden');
  });
  window.addEventListener('appinstalled', function () {
    btn.classList.add('hidden');
  });
</script>
</body>
</html>
