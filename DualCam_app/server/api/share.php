<?php
// === Réglage du partage public (pour l'application) ===
//   GET  share.php                      → état courant + lien public
//   POST share.php  enabled=1|0         → active/désactive le partage, renvoie l'état
//   En-tête : X-Auth-Token = jeton du compte.
//   Réponse : { ok, public_share:bool, share_url:string }

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

$uid = Auth::requireToken();

// Modification éventuelle (POST enabled=1|0 / true|false).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enabled'])) {
    $v = (string) $_POST['enabled'];
    Auth::setPublicShare($uid, ($v === '1' || strtolower($v) === 'true'));
}

Api::json([
    'ok'           => true,
    'public_share' => Auth::isPublicShare($uid),
    'share_url'    => Auth::shareUrl(Auth::shareToken($uid)),
]);
