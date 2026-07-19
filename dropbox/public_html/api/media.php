<?php
// === Sert une image, seulement si elle appartient au compte connecté ===
//   media.php?id=12          -> image complète
//   media.php?id=12&thumb=1  -> miniature
// Accès : session web (galerie) OU jeton du compte (app, ?token= / X-Auth-Token).

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

// L'admin voit les photos de tout le monde ; sinon, seul le propriétaire.
// Admin = clé maître de la base (admin_ok) OU compte Google promu administrateur.
$uid = Auth::currentUserId();
$isAdmin = !empty($_SESSION['admin_ok']) || ($uid !== null && Auth::isAdmin($uid));
if (!$isAdmin && $uid === null) { http_response_code(403); exit('Accès refusé'); }

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('id manquant'); }

if ($isAdmin) {
    $stmt = Db::pdo()->prepare('SELECT user_id, stored_path, deleted_at FROM ' . TBL_PHOTOS . ' WHERE id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = Db::pdo()->prepare('SELECT user_id, stored_path, deleted_at FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
}
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit('Introuvable'); }

$baseDir = empty($row['deleted_at']) ? UPLOAD_DIR : Photos::trashDir();
$path = realpath($baseDir . '/' . $row['stored_path']);
$base = realpath(UPLOAD_DIR);
if ($path === false || $base === false || strpos($path, $base) !== 0 || !is_file($path)) {
    // Fichier disparu : on nettoie l'entrée en base pour ne plus jamais l'afficher.
    Photos::deleteForever($id, (int) $row['user_id']);
    http_response_code(404); exit('Fichier absent');
}

$mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
$wantThumb = !empty($_GET['thumb']);

// Vignette demandée pour un fichier NON image (vidéo, audio, document…) : on renvoie
// une icône SVG légère adaptée au type, au lieu de transférer le fichier entier.
if ($wantThumb && strpos($mime, 'image/') !== 0) {
    $cat = Photos::categoryOf(basename($path), (string) $row['stored_path'], $mime);
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=86400');
    echo Photos::iconSvg($cat);
    exit;
}

if ($wantThumb && extension_loaded('gd') && strpos($mime, 'image/') === 0) {
    $cacheDir = Photos::thumbDir();
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    $cache = Photos::thumbFile($id);
    if (!is_file($cache) || filemtime($cache) < filemtime($path)) Photos::makeThumb($path, $cache, 500);
    if (is_file($cache)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . filesize($cache));
        readfile($cache);
        exit;
    }
}

// Diffusion du fichier avec support des requêtes HTTP Range.
// Indispensable pour la lecture/avance des vidéos (les MP4 ont souvent leur
// index « moov » à la fin : le lecteur réclame des morceaux via Range).
serveFileWithRange($path, $mime);

/** Sert un fichier en gérant l'en-tête Range (206 Partial Content) et le seek vidéo. */
function serveFileWithRange(string $path, string $mime): void
{
    $size = filesize($path);
    $fp = @fopen($path, 'rb');
    if ($fp === false) { http_response_code(500); exit('Lecture impossible'); }

    // On évite que la bufferisation garde tout le fichier en mémoire (grosses vidéos).
    while (ob_get_level() > 0) { ob_end_clean(); }
    @set_time_limit(0);

    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=86400');
    header('Accept-Ranges: bytes');

    $start = 0;
    $end   = $size - 1;

    if (isset($_SERVER['HTTP_RANGE']) &&
        preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            // Forme « bytes=-N » : les N derniers octets.
            $start = max(0, $size - (int) $m[2]);
        } else {
            if ($m[1] !== '') $start = (int) $m[1];
            if ($m[2] !== '') $end   = (int) $m[2];
        }
        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416); // Range non satisfaisable
            fclose($fp);
            exit;
        }
        http_response_code(206); // Partial Content
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $length = $end - $start + 1;
    header('Content-Length: ' . $length);

    fseek($fp, $start);
    $chunk = 8192;
    $remaining = $length;
    while ($remaining > 0 && !feof($fp) && connection_status() === CONNECTION_NORMAL) {
        $read = $remaining > $chunk ? $chunk : $remaining;
        echo fread($fp, $read);
        flush();
        $remaining -= $read;
    }
    fclose($fp);
    exit;
}
