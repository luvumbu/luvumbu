<?php
// === Téléchargement ZIP des photos ===
//   web/download.php            (session web) -> photos du compte connecté
//   ?ids=1,2,3  ou  ids[]=...   -> seulement la sélection
//   ?all=1                      -> toutes les photos du compte
//   ?user=ID                    -> (ADMIN uniquement) cible le compte ID
// Construit une archive ZIP puis l'envoie en téléchargement.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

$isAdmin    = !empty($_SESSION['admin_ok']);
$sessionUid = Auth::currentUserId();

// Détermine le compte ciblé.
$reqUser  = (int) ($_REQUEST['user'] ?? 0);
$ownerUid = ($isAdmin && $reqUser > 0) ? $reqUser : (int) ($sessionUid ?? 0);
if ($ownerUid <= 0) { http_response_code(403); exit('Accès refusé'); }

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit("L'extension ZIP n'est pas disponible sur ce serveur.");
}

// Sélection ou tout ?
$all = !empty($_REQUEST['all']);
$ids = [];
if (!$all) {
    $raw = $_REQUEST['ids'] ?? [];
    if (is_string($raw)) $raw = explode(',', $raw);
    if (is_array($raw)) foreach ($raw as $v) { $i = (int) $v; if ($i > 0) $ids[] = $i; }
    if (!$ids) { http_response_code(400); exit('Aucune photo sélectionnée.'); }
}

$db = Db::pdo();
if ($all) {
    // « Tout télécharger » n'inclut pas les photos masquées.
    $st = $db->prepare(
        'SELECT id, original_name, stored_path FROM ' . TBL_PHOTOS . '
         WHERE user_id = ? AND deleted_at IS NULL AND hidden = 0
         ORDER BY COALESCE(taken_at, uploaded_at)'
    );
    $st->execute([$ownerUid]);
} else {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare(
        'SELECT id, original_name, stored_path FROM ' . TBL_PHOTOS . "
         WHERE user_id = ? AND deleted_at IS NULL AND id IN ($ph)"
    );
    $st->execute(array_merge([$ownerUid], $ids));
}
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) { http_response_code(404); exit('Rien à télécharger.'); }

// Construit le ZIP dans un fichier temporaire.
$tmp = tempnam(sys_get_temp_dir(), 'psync_zip_');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    exit('Impossible de créer l’archive.');
}

$added = 0;
$usedNames = [];
foreach ($rows as $r) {
    $path = realpath(UPLOAD_DIR . '/' . $r['stored_path']);
    $base = realpath(UPLOAD_DIR);
    if ($path === false || $base === false || strpos($path, $base) !== 0 || !is_file($path)) continue;

    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $r['original_name'] ?: ('photo_' . $r['id'] . '.jpg'));
    // Évite les doublons de noms dans l'archive.
    $entry = $safe;
    if (isset($usedNames[$entry])) $entry = $r['id'] . '_' . $safe;
    $usedNames[$entry] = true;

    $zip->addFile($path, $entry);
    $added++;
}
$zip->close();

if ($added === 0) { @unlink($tmp); http_response_code(404); exit('Fichiers introuvables sur le disque.'); }

// Nettoie tout buffer puis envoie le fichier.
$fname = 'photosync_' . date('Ymd_His') . '.zip';
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . filesize($tmp));
header('X-Accel-Buffering: no');
readfile($tmp);
@unlink($tmp);
exit;
