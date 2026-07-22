<?php
// === Suppression de vidéos DualCam par NOM (depuis l'application) ===
//   POST dualcam_delete.php   names[]=DualCam_x.mp4   (ou JSON {"names":[...]})
//   En-tête : X-Auth-Token = jeton du compte.
//   Les vidéos sont mises à la CORBEILLE (récupérables 30 j), jamais effacées d'un coup.
//   Scopé au compte + source='dualcam' : impossible de toucher les fichiers d'un autre.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Api::fail('Méthode non autorisée', 405);

$uid = Auth::requireToken();

// Noms depuis le formulaire (names[]) ou depuis un corps JSON {"names":[...]}.
$names = $_POST['names'] ?? null;
if (!is_array($names)) {
    $j = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($j) && isset($j['names']) && is_array($j['names'])) $names = $j['names'];
}
if (!is_array($names) || !$names) Api::fail('Aucun nom fourni', 400);

$db = Db::pdo();
$trashed = 0;
foreach ($names as $name) {
    $name = basename((string) $name);
    if ($name === '') continue;
    $st = $db->prepare(
        'SELECT id FROM ' . TBL_PHOTOS .
        " WHERE user_id = ? AND original_name = ? AND source = 'dualcam' AND deleted_at IS NULL"
    );
    $st->execute([$uid, $name]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        Photos::trash((int) $r['id'], $uid);
        $trashed++;
    }
}

Api::json(['ok' => true, 'trashed' => $trashed]);
