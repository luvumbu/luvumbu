<?php
/* LUVUMBU ID — 2ᵉ page protégée : simule une AUTRE application (App B).
   Sert à prouver le SSO : connecté sur demo.php → reconnu ici sans re-login. */
require __DIR__ . '/client.php';
$user = luvumbu_require_login('app-B');
function he($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html><html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>App B — protégée par SSO</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#101a12;color:#eaf0ff;
font-family:system-ui,Segoe UI,sans-serif;padding:20px}.card{background:#16261b;border:1px solid #2a4a34;
border-radius:18px;padding:30px;max-width:440px;text-align:center}.ok{color:#37c98b;font-weight:600}
.muted{color:#9fb0d0}a{color:#5b8cff}</style></head><body>
<div class="card">
  <h1>🅱️ Application B</h1>
  <p class="ok">✅ Reconnu par SSO sans nouvelle connexion</p>
  <p><b><?= he($user['name'] ?? '') ?></b><br><span class="muted"><?= he($user['email'] ?? '') ?></span></p>
  <p class="muted" style="font-size:.85rem">Tu ne t'es PAS reconnecté ici : la même identité Luvumbu ID
     est partagée avec les autres apps.</p>
  <p><a href="demo.php">← App A</a> · <a href="index.php">Hub</a></p>
</div></body></html>
