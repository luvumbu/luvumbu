<?php
// Helpers d'authentification par token pour l'API.
//
// Le token est cherche dans trois emplacements, dans cet ordre :
//   1. Header  Authorization: Bearer <token>
//   2. Header  X-Api-Key: <token>
//   3. Champ   api_key  (query string, corps POST, ou corps JSON)
//
// Les deux derniers existent parce qu'en FastCGI/FPM, Apache supprime souvent le
// header Authorization avant que PHP le voie : l'API repondait alors 401 meme
// avec un token valide. Un champ de requete, lui, arrive toujours.

function api_extract_token(): ?string {
    // 1. Authorization: Bearer ...
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']  // pose par la RewriteRule du .htaccess
        ?? '';
    if ($auth && stripos($auth, 'Bearer ') === 0) {
        $token = trim(substr($auth, 7));
        if ($token !== '') return $token;
    }

    // 2. X-Api-Key
    $key = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if (is_string($key) && trim($key) !== '') return trim($key);

    // 3. Champ api_key : query string, formulaire, ou JSON
    $field = $_GET['api_key'] ?? $_POST['api_key'] ?? null;
    if (!is_string($field) || trim($field) === '') {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $body = json_decode($raw, true);
            if (is_array($body) && isset($body['api_key']) && is_string($body['api_key'])) {
                $field = $body['api_key'];
            }
        }
    }
    if (is_string($field) && trim($field) !== '') return trim($field);

    return null;
}

function api_current_user(): ?array {
    global $pdo;
    $token = api_extract_token();
    if (!$token) return null;

    $stmt = $pdo->prepare("
        SELECT u.id, u.nom, u.prenom, u.email, u.is_admin
        FROM api_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token = ? AND t.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) return null;

    // mise à jour non bloquante de last_used_at
    $upd = $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token = ?');
    $upd->execute([$token]);

    return $user;
}

function api_require_user(): array {
    $user = api_current_user();
    if (!$user) json_error('Non authentifié', 401);
    return $user;
}

function api_generate_token(int $userId, int $daysValid = 30): string {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO api_tokens (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))');
    $stmt->execute([$token, $userId, $daysValid]);
    return $token;
}
