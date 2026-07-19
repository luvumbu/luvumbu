<?php
// === Réception des photos (rattachées au compte) ===
// L'app envoie un POST multipart : "photo" + "taken_at", avec X-Auth-Token = jeton du compte.

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Api::header();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Api::fail('Méthode non autorisée', 405);

// Authentification par compte : jeton de l'app OU session web.
$uid = Auth::requireUser();

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    Api::fail('Fichier manquant ou erreur de transfert', 400);
}

$tmp  = $_FILES['photo']['tmp_name'];
$name = basename((string) $_FILES['photo']['name']);
$size = (int) $_FILES['photo']['size'];

if ($size <= 0) Api::fail('Fichier vide', 400);
if (MAX_BYTES > 0 && $size > MAX_BYTES) Api::fail('Fichier trop volumineux', 413);
if (!is_uploaded_file($tmp)) Api::fail('Transfert invalide', 400);

$sha = hash_file('sha256', $tmp);
$db = Db::pdo();

// Filet : crée la colonne 'source' si elle manque (déploiement à chaud, sans re-login).
try { $db->query("SELECT source FROM " . TBL_PHOTOS . " LIMIT 0"); }
catch (Throwable $e) {
    try { $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'phone'"); }
    catch (Throwable $e2) {}
}

// Filet : crée les colonnes de géolocalisation si elles manquent (déploiement à chaud).
try { $db->query("SELECT latitude, longitude FROM " . TBL_PHOTOS . " LIMIT 0"); }
catch (Throwable $e) {
    try {
        $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN latitude DECIMAL(10,7) NULL, ADD COLUMN longitude DECIMAL(10,7) NULL");
    } catch (Throwable $e2) {}
}

// Doublon POUR CE COMPTE ?
$stmt = $db->prepare('SELECT stored_path FROM ' . TBL_PHOTOS . ' WHERE user_id = ? AND sha256 = ?');
$stmt->execute([$uid, $sha]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    Api::json(['ok' => true, 'duplicate' => true, 'path' => $row['stored_path']]);
}

// Rangement : photos et vidéos séparées —
//   uploads/<user_id>/photos/<année>/<mois>/   et   uploads/<user_id>/videos/<année>/<mois>/
$takenMs = $_POST['taken_at'] ?? null;
$dt = null;
if (is_string($takenMs) && ctype_digit($takenMs) && (int) $takenMs > 0) {
    $dt = (new DateTime())->setTimestamp((int) ((int) $takenMs / 1000));
}
// Type du fichier : vidéo (d'après le MIME réel, avec repli sur l'extension) ou photo.
$mimeUp  = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: '') : '';
$isVideo = strpos($mimeUp, 'video/') === 0
    || preg_match('/\.(mp4|mov|mkv|avi|3gp|3gpp|webm|m4v|wmv|flv|ts|mts|m2ts)$/i', $name) === 1;
$kind    = $isVideo ? 'videos' : 'photos';
$subdir  = $uid . '/' . $kind . '/' . ($dt ? $dt->format('Y/m') : date('Y/m'));
$destDir = UPLOAD_DIR . '/' . $subdir;
if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
    Api::fail('Impossible de créer le dossier de destination', 500);
}

$safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'photo.jpg';
$dest = $destDir . '/' . substr($sha, 0, 12) . '_' . $safe;
if (!move_uploaded_file($tmp, $dest)) Api::fail('Échec de l’enregistrement du fichier', 500);

$rel = $subdir . '/' . basename($dest);
// Origine du média. L'app (en-tête X-Auth-Token) = 'phone'. Sinon (web, session cookie),
// on lit le bouton utilisé via le champ 'source' : 'computer' (bouton Ordinateur)
// ou 'web' (bouton Application / page d'envoi) ; défaut 'web'.
if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
    // App mobile : 'phone' par défaut. Une app dédiée (ex. DualCam) peut préciser sa
    // propre source pour être affichée sur sa page web à part.
    $s = $_POST['source'] ?? '';
    $source = in_array($s, ['dualcam'], true) ? $s : 'phone';
} else {
    $s = $_POST['source'] ?? '';
    $source = in_array($s, ['computer', 'web'], true) ? $s : 'web';
}
// Géolocalisation optionnelle : l'app envoie latitude / longitude (degrés décimaux).
// On valide les bornes ; hors bornes ou absent => NULL (position simplement inconnue).
$lat = $_POST['latitude']  ?? null;
$lng = $_POST['longitude'] ?? null;
$lat = (is_numeric($lat) && (float) $lat >= -90  && (float) $lat <= 90)  ? round((float) $lat, 7) : null;
$lng = (is_numeric($lng) && (float) $lng >= -180 && (float) $lng <= 180) ? round((float) $lng, 7) : null;
// Une coordonnée seule est inexploitable : il faut le couple complet.
if ($lat === null || $lng === null) { $lat = null; $lng = null; }

$db->prepare(
    'INSERT INTO ' . TBL_PHOTOS . ' (user_id, sha256, original_name, stored_path, size_bytes, taken_at, source, latitude, longitude)
     VALUES (?,?,?,?,?,?,?,?,?)'
)->execute([$uid, $sha, $name, $rel, $size, $dt ? $dt->format('Y-m-d H:i:s') : null, $source, $lat, $lng]);

Api::json(['ok' => true, 'duplicate' => false, 'path' => $rel], 201);
