<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/sync_keys.php';
require_once __DIR__ . '/../includes/sync_dump.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Methode non autorisee', 405);
}

$token          = (string)($_POST['token'] ?? '');
$reqMode        = $_POST['mode'] ?? 'miroir';
$mode           = in_array($reqMode, ['fusion', 'miroir', 'upsert'], true) ? $reqMode : 'miroir';
$includeDb      = ($_POST['include_db']      ?? '1') === '1';
$includeUploads = ($_POST['include_uploads'] ?? '1') === '1';
$dryRun         = ($_POST['dry_run']         ?? '0') === '1';

if (!$includeDb && !$includeUploads) {
    json_error('Rien a synchroniser : include_db ou include_uploads doit etre actif.', 400);
}

// On valide le payload AVANT de toucher a la cle : ainsi un envoi incomplet
// (payload trop gros, coupure reseau) ne "brule" pas une cle a usage unique.
if (empty($_FILES['payload']) || $_FILES['payload']['error'] !== UPLOAD_ERR_OK) {
    $code = isset($_FILES['payload']['error']) ? $_FILES['payload']['error'] : 'absent';
    json_error("Fichier payload manquant ou en erreur (code $code). "
        . "Si le ZIP est volumineux, verifie upload_max_filesize / post_max_size cote serveur.", 400);
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

if ($dryRun) {
    // Dry-run : on VERIFIE la cle sans la consommer (un test ne doit pas la consommer).
    if (!sync_key_check($token)) {
        json_error('Cle invalide, expiree ou deja utilisee', 403);
    }
    $zip = new ZipArchive();
    if ($zip->open($_FILES['payload']['tmp_name']) !== true) {
        json_error('Payload ZIP illisible (dry-run)', 400);
    }
    $hasJson = $zip->getFromName('data.json') !== false;
    $entries = $zip->numFiles;
    $zip->close();
    json_response([
        'ok'          => true,
        'dry_run'     => true,
        'message'     => 'Dry-run OK : cle valide, payload lisible. Rien n\'a ete applique.',
        'mode'        => $mode,
        'has_json'    => $hasJson,
        'zip_entries' => $entries,
    ]);
}

// Application reelle : on consomme la cle maintenant, apres validation du payload.
if (!sync_key_consume($token)) {
    json_error('Cle invalide, expiree ou deja utilisee', 403);
}

try {
    $uploadsDir = __DIR__ . '/../uploads';
    $summary = sync_apply_payload($pdo, $_FILES['payload']['tmp_name'], $uploadsDir, [
        'mode'            => $mode,
        'include_db'      => $includeDb,
        'include_uploads' => $includeUploads,
    ]);
    json_response([
        'ok'      => true,
        'message' => 'Synchronisation terminee',
        'summary' => $summary,
    ]);
} catch (Throwable $e) {
    json_error('Erreur sync : ' . $e->getMessage(), 500);
}
