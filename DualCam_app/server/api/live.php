<?php
// === Flux « quasi-direct » : liste les derniers fragments vidéo du compte ===
//
//   GET live.php?since=<id>   (session web OU X-Auth-Token)
//   → { ok, items:[{id, name, ts}], latest:<maxId>, recording:bool }
//
// Ne renvoie QUE les fragments DualCam récents (< LIVE_WINDOW s) et non supprimés,
// dans l'ordre chronologique. La page web les enchaîne pour un direct différé de
// quelques secondes (le temps d'enregistrer puis d'envoyer chaque fragment).

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

$uid = Auth::requireUser();

/** Fenêtre de « direct » : au-delà, un fragment est considéré comme du passé (galerie). */
const LIVE_WINDOW = 90;   // secondes

$since = isset($_GET['since']) ? (int) $_GET['since'] : 0;

// Colonne de temps disponible (uploaded_at si présente, sinon taken_at).
$hasUploaded = true;
try { Db::pdo()->query('SELECT uploaded_at FROM ' . TBL_PHOTOS . ' LIMIT 0'); }
catch (Throwable $e) { $hasUploaded = false; }
$tsCol = $hasUploaded ? 'uploaded_at' : 'taken_at';

// Fragments = segments envoyés en cours d'enregistrement (nom « ..._segN.mp4 »).
$sql =
    'SELECT id, original_name, ' . $tsCol . ' AS ts FROM ' . TBL_PHOTOS . '
      WHERE user_id = ?
        AND source = \'dualcam\'
        AND deleted_at IS NULL
        AND original_name LIKE \'%\_seg%\'
        AND id > ?
        AND ' . $tsCol . ' > (NOW() - INTERVAL ' . LIVE_WINDOW . ' SECOND)
      ORDER BY id ASC
      LIMIT 30';

$st = Db::pdo()->prepare($sql);
$st->execute([$uid, $since]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$items  = [];
$latest = $since;
foreach ($rows as $r) {
    $id = (int) $r['id'];
    $items[] = ['id' => $id, 'name' => (string) $r['original_name'], 'ts' => (string) $r['ts']];
    if ($id > $latest) $latest = $id;
}

// État « en direct » indépendant de `since` : y a-t-il UN fragment récent, même déjà relevé ?
// Sans ça, dès que la page rattrape le flux, elle croirait l'enregistrement terminé.
$live = Db::pdo()->prepare(
    'SELECT COUNT(*) c FROM ' . TBL_PHOTOS . '
      WHERE user_id = ? AND source = \'dualcam\' AND deleted_at IS NULL
        AND original_name LIKE \'%\_seg%\'
        AND ' . $tsCol . ' > (NOW() - INTERVAL ' . LIVE_WINDOW . ' SECOND)'
);
$live->execute([$uid]);
$recording = ((int) $live->fetch(PDO::FETCH_ASSOC)['c']) > 0;

Api::json([
    'ok'        => true,
    'items'     => $items,
    'latest'    => $latest,
    'recording' => $recording,
    'window'    => LIVE_WINDOW,
]);
