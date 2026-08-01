<?php
// === Téléchargement groupé depuis la galerie, en une archive ZIP ===
//   POST depuis gallery.php :
//     - ids[]        : les fichiers cochés (« Télécharger la sélection »)
//     - zip_all=1    : TOUS les fichiers de la vue courante (filtres src/type compris),
//                      pas seulement ceux de la page affichée
//   Tout est scopé au compte connecté ; fonctionne aussi depuis la corbeille.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

header('X-Robots-Tag: noindex, nofollow');

/** Page d'erreur lisible (le téléchargement n'a pas encore commencé). */
function dlFail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Téléchargement impossible — PhotoSync</title></head>'
       . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
       . 'background:#0b1220;color:#e6edf7;font-family:system-ui,-apple-system,sans-serif;text-align:center;padding:24px">'
       . '<div style="max-width:420px"><div style="font-size:38px;margin-bottom:12px">📦</div>'
       . '<p style="font-size:16px;line-height:1.6">' . htmlspecialchars($msg) . '</p>'
       . '<p><a href="gallery.php" style="color:#8ab4ff">← Retour à la galerie</a></p></div></body></html>';
    exit;
}

if (!Db::isReady()) dlFail(503, 'Serveur momentanément indisponible.');

$uid = (int) ($_SESSION['uid'] ?? 0);
if ($uid <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gallery.php');
    exit;
}
if (!class_exists('ZipArchive')) dlFail(500, "L'extension ZIP n'est pas disponible sur ce serveur.");

$inTrash = ($_POST['view'] ?? '') === 'corbeille';
$all     = !empty($_POST['zip_all']);

$where  = 'user_id = :uid AND ' . ($inTrash ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL');
$params = [':uid' => $uid];

if ($all) {
    // « Toutes les pages » : on rejoue exactement les filtres de la galerie.
    $src = in_array($_POST['src'] ?? '', ['phone', 'computer', 'web'], true) ? $_POST['src'] : '';
    if ($src !== '') { $where .= ' AND source = :src'; $params[':src'] = $src; }
    $type = in_array($_POST['type'] ?? '', ['photo', 'video', 'audio', 'document', 'other'], true) ? $_POST['type'] : '';
    if ($type !== '') $where .= ' AND ' . Photos::categoryCondition($type);
} else {
    $ids = Request::ids();
    if (!$ids) dlFail(400, 'Aucun fichier sélectionné : coche au moins une case.');
    // Entiers positifs validés par Request::ids() : sûrs à inliner.
    $where .= ' AND id IN (' . implode(',', $ids) . ')';
}

$st = Db::pdo()->prepare(
    'SELECT id, original_name, stored_path, deleted_at, size_bytes
       FROM ' . TBL_PHOTOS . " WHERE $where ORDER BY COALESCE(taken_at, uploaded_at) DESC, id DESC"
);
$st->execute($params);
$rows = Photos::filterExisting($st->fetchAll(PDO::FETCH_ASSOC), $uid);
if (!$rows) dlFail(404, 'Aucun fichier disponible pour cette sélection.');

// Garde-fou : au-delà de la limite, l'archive saturerait le disque temporaire du serveur.
$bytes = Photos::totalBytes($rows);
if ($bytes > Photos::ZIP_MAX_BYTES) {
    dlFail(413, 'Sélection trop volumineuse (' . Photos::humanSize($bytes) . ' pour ' . count($rows)
              . ' fichiers) : la limite est de ' . Photos::humanSize(Photos::ZIP_MAX_BYTES)
              . ' par archive. Télécharge en plusieurs fois (par exemple en filtrant par type).');
}

$zip = Photos::buildZip($rows);
if ($zip === null) dlFail(500, "Impossible de préparer l'archive.");

Photos::sendZip($zip['path'], ($inTrash ? 'photosync_corbeille_' : 'photosync_') . date('Y-m-d'));
