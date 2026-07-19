<?php
// === Vérification de configuration au 1er lancement (par compte) ===
//   POST setup.php   (X-Auth-Token: <jeton du compte>)
//   - confirme que le compte est valide
//   - garantit le schéma DB par compte (colonnes/index user_id)
//   - garantit que l'espace de stockage du membre existe et est inscriptible
//   Réponse : {"ok":true,"user_id":N,"space":"N","schema":true}
//        ou : {"ok":false,"error":"..."}

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

// 1) Compte : jeton de l'app OU session web.
$uid = Auth::currentUserId();
if ($uid === null) Api::fail('Compte non identifié (reconnecte-toi)', 401);

// 2) Schéma DB par compte (idempotent : colonnes user_id / index uniq_user_sha).
try {
    Auth::ensureSchema();
} catch (Throwable $e) {
    Api::fail('Schéma DB indisponible : ' . $e->getMessage(), 500);
}

// 3) Espace de stockage PROPRE à ce membre : uploads/<user_id>, créé + inscriptible.
$dir = UPLOAD_DIR . '/' . $uid;
if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    Api::fail('Impossible de créer l’espace du membre', 500);
}
$probe = $dir . '/.write_test';
if (@file_put_contents($probe, '1') === false) {
    Api::fail('Espace du membre non inscriptible (droits du dossier uploads)', 500);
}
@unlink($probe);

Api::json(['ok' => true, 'user_id' => $uid, 'space' => (string) $uid, 'schema' => true]);
