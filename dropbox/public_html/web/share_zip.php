<?php
// === Téléchargement de tout un album partagé, en une archive ZIP ===
//   web/share_zip.php?a=<token>
// Mêmes conditions d'accès que share.php : jeton valide, album non expiré et
// mot de passe déjà saisi dans la session le cas échéant.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

header('X-Robots-Tag: noindex, nofollow');

function zipFail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

if (!Db::isReady()) zipFail(503, 'Serveur momentanément indisponible.');
Albums::ensureSchema();

$token = (string) ($_GET['a'] ?? '');
$album = Albums::byToken($token);
if (!$album)                    zipFail(404, "Ce lien de partage n'existe pas ou a été remplacé.");
if (Albums::isExpired($album))  zipFail(410, 'Ce lien de partage a expiré.');

// Album protégé : on exige le déverrouillage fait sur share.php (même session).
if (!empty($album['pass_hash']) && empty($_SESSION['album_ok'][$token])) {
    header('Location: share.php?a=' . urlencode($token));
    exit;
}

if (!class_exists('ZipArchive')) zipFail(500, "L'extension ZIP n'est pas disponible sur ce serveur.");

$photos = Photos::filterExisting(Albums::photos((int) $album['id']), (int) $album['user_id']);
if (!$photos) zipFail(404, 'Cet album est vide.');

$zip = Photos::buildZip($photos);
if ($zip === null) zipFail(500, "Impossible de préparer l'archive (aucun fichier disponible).");

// Nom d'archive lisible, dérivé du nom de l'album.
Photos::sendZip($zip['path'], $album['name'] !== '' ? $album['name'] : 'album');
