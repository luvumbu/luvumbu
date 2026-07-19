<?php
// === Liste JSON des photos du compte (pour l'app) ===
//   GET feed.php?page=1   (X-Auth-Token: <jeton du compte>)

require __DIR__ . '/../lib/bootstrap.php';
Api::header();

$uid = Auth::userIdFromToken();
if ($uid === null) Api::fail('Jeton de compte invalide', 401);

$perPage = 60;
$page = max(1, (int) ($_GET['page'] ?? 1));
$db = Db::pdo();

// Filet : crée la colonne 'source' si elle manque (déploiement à chaud, sans re-login).
try { $db->query("SELECT source FROM " . TBL_PHOTOS . " LIMIT 0"); }
catch (Throwable $e) {
    try { $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'phone'"); }
    catch (Throwable $e2) {}
}

$cnt = $db->prepare('SELECT COUNT(*) c FROM ' . TBL_PHOTOS . ' WHERE user_id = ? AND deleted_at IS NULL AND hidden = 0');
$cnt->execute([$uid]);
$total = (int) $cnt->fetch(PDO::FETCH_ASSOC)['c'];

$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT id, original_name, taken_at, uploaded_at, stored_path, deleted_at, source
     FROM " . TBL_PHOTOS . " WHERE user_id = :uid AND deleted_at IS NULL AND hidden = 0
     ORDER BY COALESCE(taken_at, uploaded_at) DESC
     LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();

$photos = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    // Fichier disparu du disque : on efface l'entrée et on ne la renvoie pas.
    if (!Photos::fileExists($r)) {
        Photos::deleteForever((int) $r['id'], $uid);
        $total = max(0, $total - 1);
        continue;
    }
    $photos[] = [
        'id'     => (int) $r['id'],
        'name'   => $r['original_name'],
        'date'   => $r['taken_at'] ?: $r['uploaded_at'],
        'video'  => isVideoRow($r),
        'source' => $r['source'] ?? 'phone',
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
