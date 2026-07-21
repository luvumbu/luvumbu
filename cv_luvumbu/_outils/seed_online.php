<?php
/**
 * Script JETABLE — importeur de CV (profil riche) pour la base EN LIGNE.
 *
 * Auth : session web OU cle API (header X-API-Key / ?key=) avec le scope cv:write.
 * Entree : corps JSON { "profile": {...}, "cv_id": 0 }
 *   - profile : profil riche complet (firstName, sections, colors, photo, ...).
 *   - cv_id   : 0 = cree un NOUVEAU CV ; sinon applique le profil au CV existant.
 *
 * A SUPPRIMER du serveur apres usage.
 */

require dirname(__DIR__) . '/includes/db.php';
require dirname(__DIR__) . '/includes/guard.php';
ensure_ready();
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/api_keys.php';
require dirname(__DIR__) . '/includes/cv.php';

header('Content-Type: application/json; charset=utf-8');

// --- Authentification : session OU cle API (cv:write) ---
$userId = 0;
if (is_logged_in()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    $hdr = function_exists('getallheaders') ? getallheaders() : [];
    $key = $hdr['X-API-Key'] ?? $hdr['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '') ?: ($_GET['key'] ?? '');
    if ($key) {
        $auth = verify_api_key($key);
        if ($auth && key_has_scope($auth, 'cv:write')) {
            $userId = (int) $auth['user_id'];
        }
    }
}
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifie (session ou cle API cv:write requise).']);
    exit;
}

// --- Lecture du profil envoye ---
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['profile']) || !is_array($body['profile'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => "Corps JSON invalide : champ 'profile' attendu."]);
    exit;
}
$profile = $body['profile'];
$cvId    = (int) ($body['cv_id'] ?? 0);

// --- Champs texte derives du profil (coherence liste) ---
$first = trim((string) ($profile['firstName'] ?? ''));
$last  = trim((string) ($profile['lastName'] ?? ''));
$contact = is_array($profile['contact'] ?? null) ? $profile['contact'] : [];
$textData = [
    'full_name' => trim($first . ' ' . $last) ?: 'CV',
    'title'     => (string) ($profile['headline'] ?? ''),
    'email'     => (string) ($contact['email'] ?? ''),
    'phone'     => (string) ($contact['phone'] ?? ''),
    'summary'   => (string) ($profile['summary'] ?? ''),
];

try {
    if ($cvId > 0 && get_cv($userId, $cvId)) {
        save_cv_profile($userId, $cvId, $profile);
        echo json_encode(['ok' => true, 'cv_id' => $cvId, 'mode' => 'update']);
    } else {
        $id = create_cv($userId, $textData);
        save_cv_profile($userId, $id, $profile);
        echo json_encode(['ok' => true, 'cv_id' => $id, 'mode' => 'create']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Echec : ' . $e->getMessage()]);
}
