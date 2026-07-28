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
<!-- Rafraîchit l'état de présence du téléphone sans intervention. -->
<meta http-equiv="refresh" content="15">
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
    a{color:#5eead4;}
</style></head>
<body><div class="card">
    <h1>🎬 Télécommande DualCam</h1>
    <p class="sub">Connecté en tant que <?= htmlspecialchars($uname) ?></p>

    <?php if ($sent === 'start'): ?>
        <div class="ok">✅ Ordre « démarrer » envoyé. Le téléphone le relèvera d'ici ~10 s.</div>
    <?php elseif ($sent === 'stop'): ?>
        <div class="ok">✅ Ordre « arrêter » envoyé. Le téléphone le relèvera d'ici ~10 s.</div>
    <?php endif; ?>

    <div class="state <?= $online ? 'on' : 'off' ?>">
        <span class="dot <?= $online ? 'on' : 'off' ?>"></span>
        <?php if ($online): ?>
            Téléphone à l'écoute — vu il y a <?= (int) $agoSec ?> s
        <?php elseif ($agoSec !== null): ?>
            Dernier contact il y a <?= (int) round($agoSec / 60) ?> min — l'ordre l'attendra à sa reconnexion.
        <?php else: ?>
            Le téléphone n'a pas encore contacté le serveur — l'ordre l'attendra dès qu'il se connecte.
        <?php endif; ?>
    </div>

    <!-- Boutons TOUJOURS actifs : l'ordre est déposé et attend que le téléphone se reconnecte. -->
    <form method="post">
        <button class="start" name="cmd" value="start">▶️ Démarrer l'enregistrement</button>
        <button class="stop"  name="cmd" value="stop">⏹ Arrêter l'enregistrement</button>
    </form>

    <?php if ($pending !== ''): ?>
        <p class="sub" style="margin:0;">⏳ Ordre « <?= htmlspecialchars($pending) ?> » en attente — se déclenchera à la reconnexion du téléphone.</p>
    <?php endif; ?>

    <div class="note">
        Le téléphone n'obéit que si l'option <b>« Déclenchement à distance »</b> est cochée
        dans DualCam (écran Activation). Sans elle, aucun ordre n'est relevé.<br>
        Un ordre en attente reste valable <b>1 heure</b> : le téléphone le déclenche dès qu'il se reconnecte.<br>
        <a href="dualcam.php">🎞️ Mes vidéos</a> ·
        <a href="gallery.php">📸 Galerie PhotoSync</a>
    </div>
</div></body></html>
