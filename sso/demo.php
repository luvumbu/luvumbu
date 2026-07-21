<?php
/* LUVUMBU ID — page de DÉMONSTRATION protégée par SSO.
   Montre l'intégration en 2 lignes : require client + luvumbu_require_login(). */
require __DIR__ . '/client.php';

// Déconnexion locale de la démo
if (isset($_GET['logout'])) { luvumbu_logout(true, luvumbu_here_base()); }
function luvumbu_here_base(){ $s = sso_https()?'https':'http'; return $s.'://'.($_SERVER['HTTP_HOST']??'localhost').strtok((string)($_SERVER['REQUEST_URI']??'/'),'?'); }

$user = luvumbu_require_login('demo');   // ← redirige vers le hub si non connecté
function he($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Démo SSO — protégée</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0e1526;color:#eaf0ff;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:20px}
  .card{background:#151d31;border:1px solid #2a3450;border-radius:18px;padding:30px;max-width:440px;text-align:center}
  img{width:72px;height:72px;border-radius:50%;border:2px solid #2a3450;object-fit:cover}
  h1{margin:14px 0 2px;font-size:1.3rem}.muted{color:#9fb0d0}
  .ok{color:#37c98b;font-weight:600;margin:14px 0}
  a{color:#5b8cff}
  pre{text-align:left;background:#0b1220;border:1px solid #2a3450;border-radius:10px;padding:12px;overflow:auto;font-size:.8rem}
</style></head><body>
  <div class="card">
    <?php if (!empty($user['picture'])): ?><img src="<?= he($user['picture']) ?>" alt=""><?php endif; ?>
    <h1><?= he($user['name'] ?? '') ?></h1>
    <div class="muted"><?= he($user['email'] ?? '') ?></div>
    <p class="ok">✅ Connecté via Luvumbu ID</p>
    <p class="muted" style="font-size:.85rem">Cette page était protégée. Toute app qui inclut le client
       reconnaît la même identité, sans nouvelle connexion.</p>
    <pre>require '.../sso/client.php';
$user = luvumbu_require_login();</pre>
    <p><a href="?logout=1">Se déconnecter (global)</a> · <a href="index.php">Hub</a></p>
  </div>
</body></html>
