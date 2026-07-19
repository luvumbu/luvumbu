<?php
// === Fin de session DualCam : dédoublonnage ===
//   POST dualcam_finalize.php   champ : session   (X-Auth-Token = jeton du compte)
//   Supprime les fragments de 30 s d'une session UNIQUEMENT si la vidéo complète
//   reconstituée est bien présente → aucune perte de preuve, pas de doublons.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Api::fail('Méthode non autorisée', 405);

$uid = Auth::requireUser();

$session = (string) ($_POST['session'] ?? '');
if ($session === '' || !preg_match('/^[A-Za-z0-9_]+$/', $session)) {
    Api::fail('Session invalide', 400);
}

$db = Db::pdo();
$fullName = "DualCam_{$session}.mp4";

// 1) La vidéo complète doit exister pour ce compte — sinon on NE supprime rien.
$chk = $db->prepare(
    'SELECT COUNT(*) c FROM ' . TBL_PHOTOS . ' WHERE user_id = ? AND original_name = ? AND deleted_at IS NULL'
);
$chk->execute([$uid, $fullName]);
if ((int) $chk->fetch(PDO::FETCH_ASSOC)['c'] === 0) {
    // Vidéo complète absente (ex. envoi interrompu) → on garde les fragments.
    Api::fail('Vidéo complète absente — fragments conservés', 409);
}

// 2) Suppression des fragments de cette session (préfixe vérifié en PHP pour éviter tout sur-effacement).
$prefix = "DualCam_{$session}_seg";
$like   = $prefix . '%';
$sel = $db->prepare(
    'SELECT id, original_name FROM ' . TBL_PHOTOS . ' WHERE user_id = ? AND original_name LIKE ?'
);
$sel->execute([$uid, $like]);

$deleted = 0;
foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (strpos((string) $r['original_name'], $prefix) !== 0) continue; // garde-fou
    Photos::deleteForever((int) $r['id'], $uid);
    $deleted++;
}

Api::json(['ok' => true, 'deleted' => $deleted]);
