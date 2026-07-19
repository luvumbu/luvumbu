<?php
// === Sert une image, seulement si elle appartient au compte connecté ===
//   media.php?id=12          -> image complète
//   media.php?id=12&thumb=1  -> miniature
// Accès : session web (galerie) OU jeton du compte (app, ?token= / X-Auth-Token).

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('id manquant'); }

// Accès possible : admin, propriétaire, OU via un album partagé (?a=token).
$isAdmin = !empty($_SESSION['admin_ok']);
$uid = Auth::currentUserId();

$albumTok = (string) ($_GET['a'] ?? '');
$viaAlbum = false;
if ($albumTok !== '' && Albums::photoInToken($id, $albumTok)) {
    $al = Albums::byToken($albumTok);
    // Album sans mot de passe, ou album déverrouillé dans cette session.
    if ($al && (empty($al['pass_hash']) || !empty($_SESSION['album_ok'][$albumTok]))) $viaAlbum = true;
}

if (!$isAdmin && !$viaAlbum && $uid === null) { http_response_code(403); exit('Accès refusé'); }

if ($isAdmin || $viaAlbum) {
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

// Vignette demandée pour un fichier NON image (vidéo…) : on renvoie une icône SVG
// légère au lieu de transférer le fichier entier dans une balise <img>.
if ($wantThumb && strpos($mime, 'image/') !== 0) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=86400');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
       . '<rect width="100" height="100" rx="10" fill="#16213a"/>'
       . '<polygon points="40,32 72,50 40,68" fill="#E8772E"/>'
       . '<text x="50" y="90" font-size="12" fill="#8aa0bd" text-anchor="middle" font-family="sans-serif">vidéo</text>'
       . '</svg>';
    exit;
}

if ($wantThumb && extension_loaded('gd') && strpos($mime, 'image/') === 0) {
    // Taille : 'micro' (200px, grilles, plus léger) ou défaut (500px).
    $micro = ($_GET['thumb'] === 'micro');
    $variant = $micro ? 'micro' : '';
    $maxPx   = $micro ? 200 : 500;
    $quality = $micro ? 72 : 82;
    $cacheDir = Photos::thumbDir();
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    $cache = Photos::thumbFile($id, $variant);
    if (!is_file($cache) || filemtime($cache) < filemtime($path)) Photos::makeThumb($path, $cache, $maxPx, $quality);
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
