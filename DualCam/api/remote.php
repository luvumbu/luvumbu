<?php
// === Déclenchement à distance de l'enregistrement (depuis le PC) ===
//
//   Déposer un ordre (page web, session Google OU jeton d'app) :
//     POST remote.php   cmd=start|stop
//   Relever l'ordre en attente (application, en-tête X-Auth-Token) :
//     GET  remote.php?poll=1   → { ok, cmd:"start"|"stop"|"" }
//
// L'ordre est CONSOMMÉ à la relève : il ne se déclenche qu'une seule fois.
// Un ordre non relevé expire au bout de TTL secondes — sans quoi un « start » oublié
// pourrait allumer la caméra des heures plus tard.
//
// Côté téléphone, l'app n'interroge ce point d'entrée QUE si l'utilisateur a coché
// l'option « Déclenchement à distance » : sans elle, rien n'est jamais relevé.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

/** Durée de validité d'un ordre non relevé (secondes). */
const REMOTE_TTL = 120;

/** Crée la table des ordres si besoin (idempotent, comme le reste du schéma). */
function remote_ensure_schema(): void
{
    Db::pdo()->exec(
        'CREATE TABLE IF NOT EXISTS ' . TBL_REMOTE . ' (
            user_id   INT UNSIGNED NOT NULL PRIMARY KEY,
            cmd       VARCHAR(8)   NOT NULL DEFAULT \'\',
            issued_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            polled_at DATETIME     NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

remote_ensure_schema();
$db = Db::pdo();

// ------------------------------------------------------------------
// 1) L'APPLICATION relève l'ordre en attente (jeton obligatoire)
// ------------------------------------------------------------------
if (isset($_GET['poll'])) {
    $uid = Auth::requireToken();

    $st = $db->prepare('SELECT cmd, issued_at FROM ' . TBL_REMOTE . ' WHERE user_id = ?');
    $st->execute([$uid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $cmd = (string) ($row['cmd'] ?? '');
    if ($cmd !== '' && (time() - strtotime((string) $row['issued_at'])) > REMOTE_TTL) {
        $cmd = '';   // ordre périmé : on ne l'exécute pas
    }

    // Marque le passage (le PC saura que le téléphone écoute) et consomme l'ordre.
    $db->prepare(
        'INSERT INTO ' . TBL_REMOTE . ' (user_id, cmd, polled_at) VALUES (?, \'\', NOW())
         ON DUPLICATE KEY UPDATE cmd = \'\', polled_at = NOW()'
    )->execute([$uid]);

    Api::json(['ok' => true, 'cmd' => $cmd]);
}

// ------------------------------------------------------------------
// 2) LE PC dépose un ordre (session web, ou jeton pour un script)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') Api::fail('Méthode non autorisée', 405);

$uid = Auth::requireUser();
$cmd = (string) ($_POST['cmd'] ?? '');
if (!in_array($cmd, ['start', 'stop'], true)) Api::fail('Commande inconnue (start ou stop)', 400);

$db->prepare(
    'INSERT INTO ' . TBL_REMOTE . ' (user_id, cmd, issued_at) VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE cmd = VALUES(cmd), issued_at = NOW()'
)->execute([$uid, $cmd]);

// Dernier contact connu du téléphone : permet d'avertir « le téléphone n'écoute pas ».
$st = $db->prepare('SELECT polled_at FROM ' . TBL_REMOTE . ' WHERE user_id = ?');
$st->execute([$uid]);
$polled = $st->fetch(PDO::FETCH_ASSOC)['polled_at'] ?? null;

Api::json([
    'ok'          => true,
    'cmd'         => $cmd,
    'ttl'         => REMOTE_TTL,
    'last_seen'   => $polled,
    'seen_ago_s'  => $polled ? max(0, time() - strtotime((string) $polled)) : null,
]);
