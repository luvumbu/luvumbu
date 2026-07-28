<?php
// === Direct différé DualCam (depuis le PC) ===
//   https://luvumbu.com/DualCam/web/live.php
//   Enchaîne les fragments vidéo envoyés par le téléphone pendant l'enregistrement.
//   Décalage = durée d'un fragment (réglage « Fragment (envoi) toutes les » dans l'app)
//   + le temps d'envoi. Le son est inclus. Rien à activer : lit ce que le téléphone
//   envoie déjà en filmant.

require __DIR__ . '/../lib/bootstrap.php';

$sess  = Auth::webSession('live.php');
$uid   = $sess['uid'];
$uname = $sess['uname'];
$error = $sess['error'];

// ---- Page de connexion (même habillage que les autres pages DualCam) ----
if (!$uid) {
    ?>
    <!doctype html><html lang="fr"><head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>DualCam — Connexion</title>
        <style>
            *{box-sizing:border-box;}
            body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#e6f2ef;
                 background:radial-gradient(1000px 500px at 20% -10%,#0f3d3a 0%,transparent 55%),radial-gradient(800px 500px at 110% 10%,#13294a 0%,transparent 50%),#08111a;}
            .card{width:100%;max-width:360px;background:rgba(16,32,40,.85);border:1px solid rgba(148,163,184,.15);padding:34px 28px;border-radius:22px;box-shadow:0 20px 60px rgba(0,0,0,.55);text-align:center;}
            .logo{width:72px;height:72px;margin:0 auto 16px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:38px;background:linear-gradient(135deg,#14b8a6,#6366f1);}
            h1{font-size:23px;margin:0 0 6px;font-weight:800;}
            p.sub{color:#94a3b8;font-size:14px;margin:0 0 24px;}
            .err{color:#fca5a5;font-size:13px;margin-top:14px;background:rgba(127,29,29,.3);border:1px solid #7f1d1d;padding:10px;border-radius:10px;}
        </style></head>
    <body><div class="card">
        <div class="logo">📡</div>
        <h1>Direct DualCam</h1><p class="sub">Connecte-toi avec ton compte Google</p>
        <div><?= Auth::googleButtonHtml() ?></div>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </div></body></html>
    <?php
    exit;
}
?>
<!doctype html><html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Direct DualCam</title>
<style>
    *{box-sizing:border-box;}
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;min-height:100vh;color:#e6f2ef;
         display:flex;flex-direction:column;align-items:center;padding:20px;
         background:radial-gradient(1000px 500px at 20% -10%,#0f3d3a 0%,transparent 55%),radial-gradient(800px 500px at 110% 10%,#13294a 0%,transparent 50%),#08111a;}
    h1{font-size:20px;margin:4px 0 2px;font-weight:800;text-align:center;}
    .sub{color:#94a3b8;font-size:13px;margin:0 0 16px;text-align:center;}
    .stage{width:100%;max-width:720px;background:#000;border-radius:18px;overflow:hidden;
           border:1px solid rgba(148,163,184,.18);box-shadow:0 20px 60px rgba(0,0,0,.55);position:relative;aspect-ratio:9/16;max-height:78vh;}
    video{width:100%;height:100%;object-fit:contain;background:#000;display:block;}
    .badge{position:absolute;top:12px;left:12px;display:flex;align-items:center;gap:7px;
           background:rgba(0,0,0,.55);padding:6px 11px;border-radius:20px;font-size:13px;font-weight:700;}
    .dot{width:9px;height:9px;border-radius:50%;background:#ef4444;}
    .dot.live{background:#10b981;animation:pulse 1.4s infinite;}
    @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.3;}}
    .overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;
             gap:10px;color:#94a3b8;font-size:14px;text-align:center;padding:24px;}
    .spin{width:34px;height:34px;border:3px solid rgba(148,163,184,.25);border-top-color:#14b8a6;border-radius:50%;animation:spin 1s linear infinite;}
    @keyframes spin{to{transform:rotate(360deg);}}
    .bar{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;justify-content:center;}
    button,a.btn{border:0;border-radius:12px;padding:12px 18px;font-size:14px;font-weight:700;color:#fff;cursor:pointer;text-decoration:none;}
    .live{background:linear-gradient(135deg,#059669,#10b981);}
    .muted{background:#1f2937;}
    a.link{color:#5eead4;font-size:13px;margin-top:16px;text-decoration:none;}
    .hint{color:#64748b;font-size:12px;max-width:560px;text-align:center;margin-top:14px;line-height:1.6;}
</style></head>
<body>
    <h1>📡 Direct DualCam</h1>
    <div class="sub">Connecté : <?= htmlspecialchars($uname) ?></div>

    <div class="stage">
        <video id="v" playsinline></video>
        <div class="badge"><span class="dot" id="dot"></span><span id="badgeText">Hors ligne</span></div>
        <div class="overlay" id="overlay">
            <div class="spin"></div>
            <div id="ovText">En attente du téléphone…<br>Lance un enregistrement dans l'app DualCam.</div>
        </div>
    </div>

    <div class="bar">
        <button class="live" id="jumpBtn">⏭ Revenir au direct</button>
        <button class="muted" id="soundBtn">🔊 Activer le son</button>
    </div>

    <div class="hint">
        Décalage normal de quelques secondes : le téléphone filme un fragment, l'envoie, puis on le lit.
        Diminue « Fragment (envoi) toutes les… » dans l'app pour réduire le délai.
    </div>
    <a class="link" href="remote.php">🎬 Télécommande</a>&nbsp;·&nbsp;<a class="link" href="dualcam.php">🎞 Toutes les vidéos</a>

    <script>
    const video   = document.getElementById('v');
    const dot     = document.getElementById('dot');
    const badge   = document.getElementById('badgeText');
    const overlay = document.getElementById('overlay');
    const ovText  = document.getElementById('ovText');

    let sinceId = 0;          // dernier fragment déjà mis en file
    let queue = [];           // fragments à lire { id, url }
    let playing = false;
    let recording = false;
    let muted = true;         // l'autoplay navigateur exige le muet au départ
    video.muted = true;

    const mediaUrl = id => '../api/media.php?id=' + id;

    // ---- Récupère les nouveaux fragments toutes les ~4 s ----
    async function poll() {
        try {
            const r = await fetch('../api/live.php?since=' + sinceId, { cache: 'no-store' });
            const j = await r.json();
            if (j.ok) {
                recording = !!j.recording;
                for (const it of j.items) queue.push({ id: it.id, url: mediaUrl(it.id) });
                if (j.latest > sinceId) sinceId = j.latest;
                updateBadge();
                // Anti-retard : si trop de fragments en attente, on saute au plus récent.
                if (queue.length > 3) queue = queue.slice(-1);
                if (!playing) playNext();
            }
        } catch (e) { /* réseau : on réessaiera au prochain tick */ }
    }

    // ---- Lit le fragment suivant, puis enchaîne ----
    function playNext() {
        if (queue.length === 0) {
            playing = false;
            if (!recording) showOverlay("Enregistrement terminé.<br>En attente d'un nouveau direct…");
            return;
        }
        playing = true;
        hideOverlay();
        const next = queue.shift();
        video.src = next.url;
        video.muted = muted;
        video.play().catch(() => { /* geste utilisateur requis : le bouton son le fera */ });
    }

    video.addEventListener('ended', playNext);
    video.addEventListener('error', () => { setTimeout(playNext, 300); });

    function updateBadge() {
        if (recording) { dot.classList.add('live'); badge.textContent = 'EN DIRECT'; }
        else { dot.classList.remove('live'); badge.textContent = 'Hors ligne'; }
    }
    function showOverlay(html) { ovText.innerHTML = html; overlay.style.display = 'flex'; }
    function hideOverlay() { overlay.style.display = 'none'; }

    // ---- Boutons ----
    document.getElementById('jumpBtn').addEventListener('click', () => {
        // Vide la file et repart sur le fragment le plus récent au prochain tick.
        queue = [];
        poll();
    });
    document.getElementById('soundBtn').addEventListener('click', (e) => {
        muted = !muted;
        video.muted = muted;
        if (!muted) video.play().catch(() => {});
        e.target.textContent = muted ? '🔊 Activer le son' : '🔇 Couper le son';
    });

    poll();
    setInterval(poll, 4000);
    </script>
</body></html>
