<?php
// === Déclenchement à distance + retour d'état de l'enregistrement ===
//
//   Déposer un ordre (page web, session Google OU jeton d'app) :
//     POST remote.php   cmd=start|stop
//   Relever l'ordre + signaler son état (application, X-Auth-Token) :
//     GET  remote.php?poll=1&rec=1|0   → { ok, cmd:"start"|"stop"|"" }
//   Lire l'état pour l'affichage (page web) :
//     GET  remote.php?status=1         → { ok, recording:bool, seen_ago_s, has_pending }
//
// L'ordre est CONSOMMÉ à la relève (une seule fois) et reste valable REMOTE_TTL.
// Le téléphone n'interroge ce point d'entrée QUE si l'option « Déclenchement à
// distance » est cochée : sans elle, rien n'est relevé ni signalé.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

/** Durée de validité d'un ordre non relevé (secondes). 1 h : le téléphone peut être
 *  hors ligne un moment (poche, veille) et déclencher dès qu'il se reconnecte. */
const REMOTE_TTL = 3600;

/** Crée / met à jour la table des ordres (idempotent). */
function remote_ensure_schema(): void
{
    $db = Db::pdo();
    $db->exec(
        'CREATE TABLE IF NOT EXISTS ' . TBL_REMOTE . ' (
            user_id   INT UNSIGNED NOT NULL PRIMARY KEY,
            cmd       VARCHAR(8)   NOT NULL DEFAULT \'\',
            rec       TINYINT(1)   NOT NULL DEFAULT 0,
            rec_since DATETIME     NULL DEFAULT NULL,
            issued_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            polled_at DATETIME     NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    // Migrations : ajoute les colonnes manquantes si la table existait déjà sans elles.
    foreach (['rec' => 'TINYINT(1) NOT NULL DEFAULT 0', 'rec_since' => 'DATETIME NULL DEFAULT NULL'] as $col => $ddl) {
        if (!$db->query('SHOW COLUMNS FROM ' . TBL_REMOTE . ' LIKE \'' . $col . '\'')->fetch()) {
            try { $db->exec('ALTER TABLE ' . TBL_REMOTE . ' ADD COLUMN ' . $col . ' ' . $ddl); } catch (Throwable $e) {}
        }
    }
}

remote_ensure_schema();
$db = Db::pdo();

// ------------------------------------------------------------------
// 1) L'APPLICATION relève l'ordre + signale si elle enregistre
// ------------------------------------------------------------------
if (isset($_GET['poll'])) {
    $uid = Auth::requireToken();
    $rec = (isset($_GET['rec']) && $_GET['rec'] === '1') ? 1 : 0;

    $st = $db->prepare('SELECT cmd, issued_at, rec, rec_since FROM ' . TBL_REMOTE . ' WHERE user_id = ?');
    $st->execute([$uid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $cmd = (string) ($row['cmd'] ?? '');
    if ($cmd !== '' && (time() - strtotime((string) $row['issued_at'])) > REMOTE_TTL) {
        $cmd = '';   // ordre périmé : on ne l'exécute pas
    }

    // Heure de début d'enregistrement : figée à la transition 0→1 (pour un chrono juste),
    // conservée tant que ça filme, remise à NULL dès l'arrêt.
    $prevRec   = (int) ($row['rec'] ?? 0);
    $prevSince = $row['rec_since'] ?? null;
    if ($rec === 1) {
        $recSince = ($prevRec === 1 && $prevSince) ? $prevSince : date('Y-m-d H:i:s');
    } else {
        $recSince = null;
    }

    // Consomme l'ordre, mémorise le passage ET l'état d'enregistrement du téléphone.
    $db->prepare(
        'INSERT INTO ' . TBL_REMOTE . ' (user_id, cmd, rec, rec_since, polled_at) VALUES (?, \'\', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE cmd = \'\', rec = VALUES(rec), rec_since = VALUES(rec_since), polled_at = NOW()'
    )->execute([$uid, $rec, $recSince]);

    Api::json(['ok' => true, 'cmd' => $cmd]);
}

// ------------------------------------------------------------------
// 2) LA PAGE WEB lit l'état (le téléphone enregistre-t-il ?)
// ------------------------------------------------------------------
if (isset($_GET['status'])) {
    $uid = Auth::requireUser();
    $st = $db->prepare('SELECT cmd, rec, rec_since, polled_at FROM ' . TBL_REMOTE . ' WHERE user_id = ?');
    $st->execute([$uid]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $ago = !empty($row['polled_at']) ? max(0, time() - strtotime((string) $row['polled_at'])) : null;
    // Le téléphone interroge toutes les ~5 s : au-delà de 30 s sans contact, son état
    // « enregistre » n'est plus fiable → on ne prétend pas qu'il filme encore.
    $recording = ((int) ($row['rec'] ?? 0) === 1) && $ago !== null && $ago <= 30;
    // Temps écoulé depuis le début de l'enregistrement (pour le chrono web).
    $elapsed = ($recording && !empty($row['rec_since']))
        ? max(0, time() - strtotime((string) $row['rec_since'])) : null;

    // Dernière capture (photo avant/arrière ou capture d'écran) réellement ARRIVÉE sur le serveur.
    // Sert au web à confirmer par un flash que l'image est bien en ligne (pas juste « ordre reçu »).
    $cap = $db->prepare(
        'SELECT id, original_name FROM ' . TBL_PHOTOS . '
          WHERE user_id = ? AND source = \'dualcam\' AND deleted_at IS NULL
            AND (original_name LIKE \'DualCam\_photo\_%\' OR original_name LIKE \'DualCam\_screenshot\_%\')
          ORDER BY id DESC LIMIT 1'
    );
    $cap->execute([$uid]);
    $capRow = $cap->fetch(PDO::FETCH_ASSOC) ?: [];

    Api::json([
        'ok'               => true,
        'recording'        => $recording,
        'rec_elapsed_s'    => $elapsed,
        'seen_ago_s'       => $ago,
        'online'           => $ago !== null && $ago <= 30,
        'has_pending'      => ((string) ($row['cmd'] ?? '')) !== '',
        'last_capture_id'  => isset($capRow['id']) ? (int) $capRow['id'] : null,
    ]);
}

// ------------------------------------------------------------------
// 3) LE PC dépose un ordre (session web, ou jeton pour un script)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Api::fail('Méthode non autorisée', 405);

$uid = Auth::requireUser();
$cmd = (string) ($_POST['cmd'] ?? '');
$allowed = ['start', 'stop', 'photo_front', 'photo_back', 'screenshot'];
if (!in_array($cmd, $allowed, true)) Api::fail('Commande inconnue', 400);

$db->prepare(
    'INSERT INTO ' . TBL_REMOTE . ' (user_id, cmd, issued_at) VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE cmd = VALUES(cmd), issued_at = NOW()'
)->execute([$uid, $cmd]);

$st = $db->prepare('SELECT polled_at FROM ' . TBL_REMOTE . ' WHERE user_id = ?');
$st->execute([$uid]);
$polled = $st->fetch(PDO::FETCH_ASSOC)['polled_at'] ?? null;

Api::json([
    'ok'         => true,
    'cmd'        => $cmd,
    'ttl'        => REMOTE_TTL,
    'seen_ago_s' => $polled ? max(0, time() - strtotime((string) $polled)) : null,
]);
