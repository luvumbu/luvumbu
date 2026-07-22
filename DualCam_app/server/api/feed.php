<?php
// === Liste JSON des photos du compte (pour l'app) ===
//   GET feed.php?page=1   (X-Auth-Token: <jeton du compte>)

require __DIR__ . '/../lib/bootstrap.php';
Api::header();

$uid = Auth::requireToken();

$perPage = 60;
$db = Db::pdo();

// Filet : crée la colonne 'source' si elle manque (déploiement à chaud, sans re-login).
try { $db->query("SELECT source FROM " . TBL_PHOTOS . " LIMIT 0"); }
catch (Throwable $e) {
    try { $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'phone'"); }
    catch (Throwable $e2) {}
}

// Filtre par catégorie (photo/video/audio/document/other ; 'all' = tout) et tri.
$type = isset($_GET['type']) ? preg_replace('/[^a-z]/', '', strtolower((string) $_GET['type'])) : 'all';
$sort = isset($_GET['sort']) ? preg_replace('/[^a-z_]/', '', strtolower((string) $_GET['sort'])) : 'date_desc';
// Conditions inlinées (valeurs constantes côté code, pas d'entrée utilisateur) → compatibles
// avec les paramètres nommés ci-dessous.
$catCond = ($type !== '' && $type !== 'all') ? ' AND ' . Photos::categoryCondition($type) : '';
$orderBy = Photos::sortClause($sort);

$cnt = $db->prepare('SELECT COUNT(*) c FROM ' . TBL_PHOTOS . ' WHERE user_id = ? AND deleted_at IS NULL' . $catCond);
$cnt->execute([$uid]);
$total = (int) $cnt->fetch(PDO::FETCH_ASSOC)['c'];

['pages' => $pages, 'page' => $page, 'offset' => $offset] =
    Photos::paginate($total, (int) ($_GET['page'] ?? 1), $perPage);

$stmt = $db->prepare(
    "SELECT id, original_name, taken_at, uploaded_at, stored_path, deleted_at, source, size_bytes
     FROM " . TBL_PHOTOS . " WHERE user_id = :uid AND deleted_at IS NULL" . $catCond . "
     ORDER BY " . $orderBy . "
     LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();

// On exclut (et on efface) les entrées dont le fichier a disparu du disque.
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$kept = Photos::filterExisting($rows, $uid);
$total = max(0, $total - (count($rows) - count($kept)));

$photos = [];
foreach ($kept as $r) {
    $isVid = isVideoRow($r);
    // 'video' prioritaire (couvre les fichiers sans extension détectés par MIME),
    // sinon catégorie déduite du nom / du chemin de stockage.
    $cat = $isVid ? 'video' : Photos::categoryOf($r['original_name'], (string) $r['stored_path']);
    $photos[] = [
        'id'       => (int) $r['id'],
        'name'     => $r['original_name'],
        'date'     => $r['taken_at'] ?: $r['uploaded_at'],
        'video'    => $isVid,
        'category' => $cat,
        'size'     => (int) ($r['size_bytes'] ?? 0),
        'source'   => $r['source'] ?? 'phone',
    ];
}

/**
 * Détermine de façon fiable si une entrée est une vidéo :
 *  1) rangée dans un dossier .../videos/ (nouveaux envois séparés) ;
 *  2) sinon extension vidéo du nom ;
 *  3) sinon MIME réel du fichier sur le disque (anciens fichiers sans extension claire).
 */
function isVideoRow(array $r): bool
{
    $sp = (string) $r['stored_path'];
    if (strpos($sp, '/videos/') !== false) return true;
    if (preg_match('/\.(mp4|mov|mkv|avi|3gp|3gpp|webm|m4v|wmv|flv|ts|mts|m2ts|mpg|mpeg|ogv)$/i',
        (string) $r['original_name']) === 1) return true;
    if (function_exists('mime_content_type')) {
        $full = UPLOAD_DIR . '/' . $sp;
        if (is_file($full)) {
            $mt = mime_content_type($full);
            if (is_string($mt) && strpos($mt, 'video/') === 0) return true;
        }
    }
    return false;
}

Api::json([
    'ok' => true, 'total' => $total, 'page' => $page, 'pages' => $pages,
    'perPage' => $perPage, 'photos' => $photos,
]);
