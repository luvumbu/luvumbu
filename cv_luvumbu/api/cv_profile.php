<?php
/**
 * Endpoint du profil riche de CV (éditeur WYSIWYG) — session web + CSRF.
 *
 *   GET  /api/cv_profile.php?id=12   -> { ok, profile|null }
 *   POST /api/cv_profile.php?id=12   -> enregistre le profil (corps JSON { csrf, profile })
 *
 * Réservé au propriétaire du CV (vérifié via la session, pas par clé API).
 */

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/guard.php';

ensure_ready();

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/cv.php';

/** Réponse JSON + arrêt. */
function out(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    out(401, ['ok' => false, 'error' => 'Non authentifié.']);
}

$userId = (int) $_SESSION['user_id'];
$cvId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($cvId <= 0) {
    out(422, ['ok' => false, 'error' => 'Identifiant de CV manquant.']);
}

$cv = get_cv($userId, $cvId);
if (!$cv) {
    out(404, ['ok' => false, 'error' => 'CV introuvable.']);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $profile = get_cv_profile($userId, $cvId);
    // Aucun profil encore enregistré : on en propose un, amorcé depuis les champs texte.
    if ($profile === null) {
        $profile = seed_profile_from_cv($cv);
    }
    out(200, ['ok' => true, 'profile' => $profile]);
}

if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        out(422, ['ok' => false, 'error' => 'Corps JSON invalide.']);
    }
    if (!csrf_check($data['csrf'] ?? null)) {
        out(403, ['ok' => false, 'error' => 'Jeton CSRF invalide ou expiré.']);
    }
    $profile = $data['profile'] ?? null;
    if (!is_array($profile)) {
        out(422, ['ok' => false, 'error' => "Champ 'profile' manquant ou invalide."]);
    }

    try {
        $saved = save_cv_profile($userId, $cvId, $profile);
        $saved
            ? out(200, ['ok' => true])
            : out(404, ['ok' => false, 'error' => 'CV introuvable.']);
    } catch (Throwable $e) {
        out(500, ['ok' => false, 'error' => 'Enregistrement impossible.']);
    }
}

out(405, ['ok' => false, 'error' => 'Méthode non autorisée.']);
