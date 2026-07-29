<?php
// === Télécommande DualCam (depuis le PC) ===
//   https://luvumbu.com/DualCam/web/remote.php
//   Dépose un ordre « démarrer » ou « arrêter » que le téléphone relève.
//   Le téléphone n'obéit QUE si l'option « Déclenchement à distance » y est cochée.

require __DIR__ . '/../lib/bootstrap.php';

$sess  = Auth::webSession('remote.php');
$uid   = $sess['uid'];
$uname = $sess['uname'];
$error = $sess['error'];

// ---- Page de connexion (même habillage que dualcam.php) ----
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
        <div class="logo">🎬</div>
        <h1>Télécommande DualCam</h1><p class="sub">Connecte-toi avec ton compte Google</p>
        <div><?= Auth::googleButtonHtml() ?></div>
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </div></body></html>
    <?php
    exit;
}

// ---- Table des ordres (créée par api/remote.php ; filet si la page passe en premier) ----
Db::pdo()->exec(
    'CREATE TABLE IF NOT EXISTS ' . TBL_REMOTE . ' (
        user_id   INT UNSIGNED NOT NULL PRIMARY KEY,
        cmd       VARCHAR(8)   NOT NULL DEFAULT \'\',
        rec       TINYINT(1)   NOT NULL DEFAULT 0,
        rec_since DATETIME     NULL DEFAULT NULL,
        issued_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        polled_at DATETIME     NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// ---- Dépôt d'un ordre ----
$sent = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cmd = (string) ($_POST['cmd'] ?? '');
    if (in_array($cmd, ['start', 'stop'], true)) {
        Db::pdo()->prepare(
            'INSERT INTO ' . TBL_REMOTE . ' (user_id, cmd, issued_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE cmd = VALUES(cmd), issued_at = NOW()'
        )->execute([$uid, $cmd]);
        header('Location: remote.php?sent=' . $cmd);
        exit;
    }
}
$sent = $_GET['sent'] ?? '';

// ---- Dernier contact du téléphone ----
$st = Db::pdo()->prepare('SELECT cmd, polled_at FROM ' . TBL_REMOTE . ' WHERE user_id = ?');
$st->execute([$uid]);
$row     = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$polled  = $row['polled_at'] ?? null;
$pending = (string) ($row['cmd'] ?? '');
$agoSec  = $polled ? max(0, time() - strtotime((string) $polled)) : null;
// Le téléphone interroge toutes les ~10 s : au-delà de 60 s sans contact, il n'écoute plus.
$online  = $agoSec !== null && $agoSec <= 60;
?>
<!doctype html><html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Télécommande DualCam</title>
<style>
    *{box-sizing:border-box;}
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;min-height:100vh;
         display:flex;align-items:center;justify-content:center;padding:20px;color:#e6f2ef;
         background:radial-gradient(1000px 500px at 20% -10%,#0f3d3a 0%,transparent 55%),radial-gradient(800px 500px at 110% 10%,#13294a 0%,transparent 50%),#08111a;}
    .card{width:100%;max-width:460px;background:rgba(16,32,40,.85);border:1px solid rgba(148,163,184,.15);
          padding:32px 28px;border-radius:22px;box-shadow:0 20px 60px rgba(0,0,0,.55);}
    h1{font-size:22px;margin:0 0 4px;font-weight:800;text-align:center;}
    p.sub{color:#94a3b8;font-size:14px;margin:0 0 22px;text-align:center;}
    .state{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;font-size:14px;margin-bottom:20px;}
    .on{background:rgba(6,78,59,.45);border:1px solid #047857;color:#a7f3d0;}
    .off{background:rgba(127,29,29,.3);border:1px solid #7f1d1d;color:#fca5a5;}
    .dot{width:10px;height:10px;border-radius:50%;flex:none;}
    .dot.on{background:#10b981;} .dot.off{background:#ef4444;}
    button{width:100%;border:0;border-radius:14px;padding:16px;font-size:16px;font-weight:700;
           color:#fff;cursor:pointer;margin-bottom:12px;}
    .start{background:linear-gradient(135deg,#059669,#10b981);}
    .stop{background:linear-gradient(135deg,#b91c1c,#ef4444);}
    button:disabled{opacity:.45;cursor:not-allowed;}
    .ok{background:rgba(6,78,59,.45);border:1px solid #047857;color:#a7f3d0;padding:11px 14px;border-radius:11px;font-size:13px;margin-bottom:18px;}
    .note{color:#64748b;font-size:12px;line-height:1.6;margin-top:18px;border-top:1px solid rgba(148,163,184,.15);padding-top:16px;}
    /* Retour serveur (fichiers réellement reçus) + erreur remontée par le téléphone */
    .feed{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
          background:rgba(30,41,59,.55);border:1px solid rgba(148,163,184,.18);color:#cbd5e1;
          padding:11px 14px;border-radius:11px;font-size:13px;margin-bottom:14px;text-align:left;}
    .feed.fresh{background:rgba(6,78,59,.45);border-color:#047857;color:#a7f3d0;}
    .feed a{color:#5eead4;text-decoration:none;font-weight:700;white-space:nowrap;}
    .phone-err{background:rgba(127,29,29,.35);border:1px solid #7f1d1d;color:#fca5a5;
               padding:11px 14px;border-radius:11px;font-size:13px;margin-bottom:14px;text-align:left;}
    a{color:#5eead4;}

    /* Tuiles d'accès (logos bien en avant), même style que l'accueil DualCam. */
    .quicklinks{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:20px;}
    .ql{display:flex;flex-direction:column;align-items:center;text-align:center;gap:4px;padding:22px 14px;border-radius:20px;text-decoration:none;color:#e6f2ef;
        background:linear-gradient(135deg,rgba(20,184,166,.16),rgba(99,102,241,.16));border:1px solid rgba(148,163,184,.15);
        transition:transform .14s ease,border-color .14s ease,box-shadow .14s ease;}
    .ql:hover{transform:translateY(-5px);border-color:#14b8a6;box-shadow:0 16px 36px rgba(0,0,0,.5);}
    .ql .ql-ic{font-size:52px;line-height:1;filter:drop-shadow(0 6px 14px rgba(0,0,0,.45));}
    .ql b{font-size:1.1rem;margin-top:10px;font-weight:800;}
    .ql small{color:#94a3b8;font-size:.85rem;margin-top:2px;}

    /* Effet REC : cadre rouge pulsant sur TOUTE la page quand le téléphone filme. */
    #recFx{position:fixed;inset:0;pointer-events:none;z-index:50;display:none;
           border:10px solid #ef4444;box-shadow:inset 0 0 120px rgba(239,68,68,.55);animation:recpulse 1.3s infinite;}
    #recFx.show{display:block;}
    @keyframes recpulse{0%,100%{opacity:1;}50%{opacity:.35;}}
    #recBadge{position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:51;display:none;
              align-items:center;gap:10px;background:#b91c1c;color:#fff;font-weight:800;font-size:16px;
              padding:9px 18px;border-radius:24px;box-shadow:0 8px 24px rgba(0,0,0,.5);letter-spacing:.5px;}
    #recBadge.show{display:flex;}
    #recBadge .b{width:13px;height:13px;border-radius:50%;background:#fff;animation:recpulse 1.3s infinite;}
    #recBadge .chrono{font-variant-numeric:tabular-nums;background:rgba(0,0,0,.28);padding:2px 10px;border-radius:14px;margin-left:4px;}
</style></head>
<body>
<!-- Effet REC plein écran (piloté par l'état réel renvoyé par le téléphone) -->
<div id="recFx"></div>
<div id="recBadge"><span class="b"></span>REC<span class="chrono" id="chrono">00:00</span></div>

<div class="card">
    <h1>🎬 Télécommande DualCam</h1>
    <p class="sub">Connecté en tant que <?= htmlspecialchars($uname) ?></p>

    <div id="msg" class="ok" style="display:none"></div>

    <div id="stateBox" class="state off">
        <span class="dot off" id="stateDot"></span>
        <span id="stateText">Vérification de l'état du téléphone…</span>
    </div>

    <!-- Retour serveur : ce qui est RÉELLEMENT monté (fragments puis vidéo complète).
         Sans ce bloc, on voyait REC tourner sans savoir si un fichier arrivait. -->
    <div id="feedBox" class="feed">
        <span id="feedText">Aucun fichier reçu pour l'instant.</span>
        <a id="feedLink" href="dualcam.php" style="display:none">→ Mes vidéos</a>
    </div>

    <!-- Erreur remontée par le TÉLÉPHONE lui-même (envoi refusé, trop gros, hors ligne…). -->
    <div id="errBox" class="phone-err" style="display:none"></div>

    <!-- Boutons TOUJOURS actifs : l'ordre est déposé et attend que le téléphone se reconnecte. -->
    <form id="cmdForm" method="post">
        <button class="start" name="cmd" value="start">▶️ Démarrer l'enregistrement</button>
        <button class="stop"  name="cmd" value="stop">⏹ Arrêter l'enregistrement</button>
    </form>

    <div class="quicklinks">
        <a class="ql" href="dualcam.php"><span class="ql-ic">🎞️</span><b>Mes vidéos</b><small>Tous les enregistrements</small></a>
        <a class="ql" href="gallery.php"><span class="ql-ic">📸</span><b>Galerie PhotoSync</b><small>Photos &amp; autres médias</small></a>
    </div>

    <div class="note">
        Le téléphone n'obéit que si l'option <b>« Déclenchement à distance »</b> est cochée
        dans DualCam (écran Activation). Sans elle, aucun ordre n'est relevé.<br>
        Un ordre en attente reste valable <b>1 heure</b> : le téléphone le déclenche dès qu'il se reconnecte.
    </div>
</div>

<script>
const msg      = document.getElementById('msg');
const stateBox = document.getElementById('stateBox');
const stateDot = document.getElementById('stateDot');
const stateTxt = document.getElementById('stateText');
const feedBox  = document.getElementById('feedBox');
const feedTxt  = document.getElementById('feedText');
const feedLink = document.getElementById('feedLink');
const errBox   = document.getElementById('errBox');
const recFx    = document.getElementById('recFx');
const recBadge = document.getElementById('recBadge');
const chrono   = document.getElementById('chrono');
let waitingStart = false;   // on vient de cliquer « Démarrer », on attend le REC

// Chrono : recalé sur l'heure de début RÉELLE renvoyée par le serveur, puis il avance seul.
let recStartMs = null;      // instant (ms local) correspondant à « début d'enregistrement »
function setElapsed(sec) {
    // sec = temps écoulé côté serveur ; on en déduit l'instant de départ local.
    if (sec === null || sec === undefined) { recStartMs = null; chrono.textContent = '00:00'; return; }
    recStartMs = Date.now() - sec * 1000;
}
function tickChrono() {
    if (recStartMs === null) return;
    let s = Math.floor((Date.now() - recStartMs) / 1000);
    const h = Math.floor(s / 3600); s -= h * 3600;
    const m = Math.floor(s / 60);   s -= m * 60;
    const pad = n => String(n).padStart(2, '0');
    chrono.textContent = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
}
setInterval(tickChrono, 250);

function flash(text) { msg.textContent = text; msg.style.display = 'block'; }

// Envoi de l'ordre SANS recharger la page (sinon l'effet REC repartirait de zéro).
document.getElementById('cmdForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cmd = e.submitter && e.submitter.value ? e.submitter.value : 'start';
    const labels = { start: 'démarrer l\'enregistrement', stop: 'arrêter l\'enregistrement' };
    flash('✅ Ordre « ' + (labels[cmd] || cmd) + ' » envoyé — le téléphone le relèvera d\'ici ~5 s.');
    if (cmd === 'start') waitingStart = true;
    if (cmd === 'stop')  waitingStart = false;
    try {
        await fetch('../api/remote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cmd=' + cmd
        });
    } catch (err) { flash('⚠️ Envoi impossible : ' + (err.message || err)); }
    poll();   // rafraîchit l'état tout de suite
});

// Surveillance de l'état réel renvoyé par le téléphone.
async function poll() {
    try {
        const r = await fetch('../api/remote.php?status=1', { cache: 'no-store' });
        const j = await r.json();
        if (!j.ok) return;

        // --- Effet REC + chrono pilotés par l'état RÉEL du téléphone ---
        const rec = !!j.recording;
        recFx.classList.toggle('show', rec);
        recBadge.classList.toggle('show', rec);
        if (rec) setElapsed(j.rec_elapsed_s); else setElapsed(null);
        if (rec && waitingStart) { waitingStart = false; flash('🔴 Enregistrement CONFIRMÉ sur le téléphone.'); }

        // --- Ligne d'état ---
        const online = !!j.online;
        stateBox.className = 'state ' + (rec ? 'on' : online ? 'on' : 'off');
        stateDot.className = 'dot ' + (online ? 'on' : 'off');
        if (rec)             stateTxt.textContent = '🔴 Le téléphone enregistre (vu il y a ' + j.seen_ago_s + ' s)';
        else if (online)     stateTxt.textContent = 'Téléphone à l\'écoute — vu il y a ' + j.seen_ago_s + ' s';
        else if (j.seen_ago_s !== null) stateTxt.textContent = 'Dernier contact il y a ' + Math.round(j.seen_ago_s / 60) + ' min — l\'ordre l\'attendra à sa reconnexion.';
        else                 stateTxt.textContent = 'Le téléphone n\'a pas encore contacté le serveur — l\'ordre l\'attendra dès qu\'il se connecte.';

        // --- Retour serveur : fichiers réellement reçus ---
        const m = j.last_media;
        if (m) {
            const mo   = (m.size / 1048576).toFixed(1);
            const when = m.ago_s < 60 ? 'il y a ' + m.ago_s + ' s'
                       : m.ago_s < 3600 ? 'il y a ' + Math.round(m.ago_s / 60) + ' min'
                       : 'il y a ' + Math.round(m.ago_s / 3600) + ' h';
            const cnt = (rec && j.session_files > 0) ? ' · ' + j.session_files + ' fichier(s) sur cet enregistrement' : '';
            feedTxt.textContent = '📥 Dernier fichier reçu : ' + m.name + ' (' + mo + ' Mo) ' + when + cnt;
            feedLink.style.display = '';
            // Vert tant que c'est frais (moins de 2 min) : le retour est en train de se faire.
            feedBox.classList.toggle('fresh', m.ago_s <= 120);
        } else {
            feedTxt.textContent = rec
                ? '⏳ Enregistrement en cours — aucun fichier encore arrivé sur le serveur.'
                : 'Aucun fichier reçu pour l\'instant.';
            feedLink.style.display = 'none';
            feedBox.classList.remove('fresh');
        }

        // --- Erreur remontée par le téléphone (sinon on cherche à l'aveugle) ---
        if (j.phone_err) {
            errBox.textContent = '⚠️ Téléphone : ' + j.phone_err;
            errBox.style.display = 'block';
        } else {
            errBox.style.display = 'none';
        }
    } catch (e) { /* réseau : on réessaie */ }
}

poll();
setInterval(poll, 2000);
</script>
</body></html>
