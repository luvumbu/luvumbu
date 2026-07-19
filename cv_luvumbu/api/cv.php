<?php
/**
 * Endpoint API des CV — authentification par cle API (header X-API-Key ou Bearer).
 * Permet le pilotage COMPLET d'un CV a distance (texte + profil riche : titre, photo,
 * couleurs, sections, site web, ...).
 *
 *   GET    /api/cv.php                      -> liste des CV                     (cv:read)
 *   GET    /api/cv.php?id=12                -> detail texte d'un CV             (cv:read)
 *   GET    /api/cv.php?id=12&profile=1      -> { cv, profile } (profil riche)   (cv:read)
 *   GET    /api/cv.php?whoami=1             -> { user_id, scopes }
 *   POST   /api/cv.php   { ...champs, profile? }        -> cree un CV           (cv:write)
 *   PUT    /api/cv.php   { id, ...champs?, profile? }   -> met a jour un CV     (cv:write)
 *   DELETE /api/cv.php?id=12[&force=1]      -> supprime (corbeille / definitif) (cv:write)
 *
 * Le corps des requetes POST/PUT est du JSON. 'profile' = profil riche complet
 * (firstName, lastName, headline, summary, contact{location,phone,email,website,permis},
 *  template, colors, photo, sections[...]). Fournir 'profile' remplace entierement le
 *  profil riche du CV.
 */

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/api_keys.php';
require __DIR__ . '/../includes/cv.php';

/** Reponse JSON + arret. */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Corps JSON de la requete (ou tableau vide). */
function json_body(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST; // tolere aussi un POST de formulaire
    }
    return is_array($data) ? $data : [];
}

/** Met a jour les champs texte fournis d'un CV (parmi cv_fields). */
function api_update_cv_text(int $userId, int $cvId, array $data): void
{
    $set = [];
    $vals = [];
    foreach (cv_fields() as $f) {
        if (array_key_exists($f, $data)) {
            $set[]  = "$f = ?";
            $vals[] = (string) $data[$f];
        }
    }
    if (!$set) {
        return;
    }
    $vals[] = $cvId;
    $vals[] = $userId;
    $sql = "UPDATE cvs SET " . implode(', ', $set) . " WHERE id = ? AND user_id = ?";
    db()->prepare($sql)->execute($vals);
}

/** Derive les champs texte a partir d'un profil riche (pour rester coherent avec la liste). */
function api_text_from_profile(array $profile): array
{
    $first = trim((string) ($profile['firstName'] ?? ''));
    $last  = trim((string) ($profile['lastName'] ?? ''));
    $c = is_array($profile['contact'] ?? null) ? $profile['contact'] : [];
    return [
        'full_name' => trim($first . ' ' . $last),
        'title'     => (string) ($profile['headline'] ?? ''),
        'email'     => (string) ($c['email'] ?? ''),
        'phone'     => (string) ($c['phone'] ?? ''),
        'summary'   => (string) ($profile['summary'] ?? ''),
    ];
}

// --- 1) Recuperation de la cle ---
$headers = function_exists('getallheaders') ? getallheaders() : [];
$key = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
if (!$key && !empty($_SERVER['HTTP_X_API_KEY'])) {
    $key = $_SERVER['HTTP_X_API_KEY'];
}
if (!$key && !empty($_SERVER['HTTP_AUTHORIZATION'])
    && preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $key = $m[1];
}
if (!$key) {
    respond(401, ['error' => 'Cle API manquante (header X-API-Key).']);
}

// --- 2) Verification ---
try {
    $auth = verify_api_key($key);
} catch (Throwable $e) {
    respond(500, ['error' => 'Erreur serveur.']);
}
if ($auth === null) {
    respond(401, ['error' => 'Cle API invalide ou revoquee.']);
}

$userId = (int) $auth['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// --- 3) Routage ---
if ($method === 'GET') {
    if (isset($_GET['whoami'])) {
        respond(200, ['user_id' => $userId, 'scopes' => $auth['scopes']]);
    }
    if (!key_has_scope($auth, 'cv:read')) {
        respond(403, ['error' => "Permission 'cv:read' requise."]);
    }
    if (isset($_GET['id'])) {
        $cv = get_cv($userId, (int) $_GET['id']);
        if (!$cv) {
            respond(404, ['error' => 'CV introuvable.']);
        }
        if (isset($_GET['profile'])) {
            $profile = get_cv_profile($userId, (int) $_GET['id']) ?? seed_profile_from_cv($cv);
            respond(200, ['cv' => $cv, 'profile' => $profile]);
        }
        respond(200, ['cv' => $cv]);
    }
    respond(200, ['cvs' => list_cvs($userId)]);
}

if ($method === 'POST') {
    if (!key_has_scope($auth, 'cv:write')) {
        respond(403, ['error' => "Permission 'cv:write' requise."]);
    }
    $data    = json_body();
    $profile = (isset($data['profile']) && is_array($data['profile'])) ? $data['profile'] : null;

    // full_name : explicite, sinon derive du profil.
    if (empty($data['full_name']) && $profile) {
        $data = array_merge($data, api_text_from_profile($profile));
    }
    if (empty($data['full_name'])) {
        respond(422, ['error' => "Le champ 'full_name' (ou un 'profile' avec firstName/lastName) est obligatoire."]);
    }

    try {
        $id = create_cv($userId, $data);
        if ($profile) {
            save_cv_profile($userId, $id, $profile);
        }
        $out = ['created' => true, 'cv' => get_cv($userId, $id)];
        if ($profile) {
            $out['profile'] = get_cv_profile($userId, $id);
        }
        respond(201, $out);
    } catch (Throwable $e) {
        respond(500, ['error' => 'Creation impossible : ' . $e->getMessage()]);
    }
}

if ($method === 'PUT' || $method === 'PATCH') {
    if (!key_has_scope($auth, 'cv:write')) {
        respond(403, ['error' => "Permission 'cv:write' requise."]);
    }
    $data = json_body();
    $cvId = (int) ($data['id'] ?? $_GET['id'] ?? 0);
    if ($cvId <= 0 || !get_cv($userId, $cvId)) {
        respond(404, ['error' => 'CV introuvable (champ id requis).']);
    }
    try {
        api_update_cv_text($userId, $cvId, $data); // champs texte fournis
        if (isset($data['profile']) && is_array($data['profile'])) {
            save_cv_profile($userId, $cvId, $data['profile']); // profil riche complet
        }
        // Partage public : { "share": true } active, { "share": false } desactive.
        if (array_key_exists('share', $data)) {
            if ($data['share']) {
                enable_cv_share($userId, $cvId);
            } else {
                disable_cv_share($userId, $cvId);
            }
        }
        $cvRow = get_cv($userId, $cvId);
        $out = ['updated' => true, 'cv' => $cvRow];
        if (isset($data['profile'])) {
            $out['profile'] = get_cv_profile($userId, $cvId);
        }
        if (!empty($cvRow['share_token'])) {
            $out['share_url'] = cv_public_url($cvRow['share_token']);
        }
        respond(200, $out);
    } catch (Throwable $e) {
        respond(500, ['error' => 'Mise a jour impossible : ' . $e->getMessage()]);
    }
}

if ($method === 'DELETE') {
    if (!key_has_scope($auth, 'cv:write') && !key_has_scope($auth, 'cv:delete')) {
        respond(403, ['error' => "Permission 'cv:write' ou 'cv:delete' requise."]);
    }
    $cvId = (int) ($_GET['id'] ?? 0);
    if ($cvId <= 0) {
        respond(422, ['error' => "Parametre 'id' requis."]);
    }
    $force = !empty($_GET['force']);
    try {
        if ($force) {
            // Suppression definitive.
            $stmt = db()->prepare("DELETE FROM cvs WHERE id = ? AND user_id = ?");
            $stmt->execute([$cvId, $userId]);
            respond(200, ['deleted' => true, 'mode' => 'definitif', 'rows' => $stmt->rowCount()]);
        }
        // Corbeille (suppression douce) si la colonne existe, sinon definitive.
        try {
            $stmt = db()->prepare("UPDATE cvs SET deleted_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$cvId, $userId]);
            respond(200, ['deleted' => true, 'mode' => 'corbeille', 'rows' => $stmt->rowCount()]);
        } catch (Throwable $inner) {
            $stmt = db()->prepare("DELETE FROM cvs WHERE id = ? AND user_id = ?");
            $stmt->execute([$cvId, $userId]);
            respond(200, ['deleted' => true, 'mode' => 'definitif', 'rows' => $stmt->rowCount()]);
        }
    } catch (Throwable $e) {
        respond(500, ['error' => 'Suppression impossible : ' . $e->getMessage()]);
    }
}

respond(405, ['error' => 'Methode non autorisee.']);
