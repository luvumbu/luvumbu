<?php
/* ═══════════════════════════════════════════════════════════════════════
   LUVUMBU ID — bibliothèque SSO (identité partagée entre toutes les apps).
   - JWT signé HS256 avec un secret partagé (sso/secret.local.php).
   - Vérification d'un jeton Google (identité de départ).
   - Cookie partagé LUVID (single-session sur le domaine).
   Aucune base de données requise : l'identité tient dans le jeton signé.
   ═══════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

const LUVID_COOKIE = 'LUVID';
const LUVID_TTL    = 86400 * 7;   // 7 jours

/* ─── Configuration / secret (hors dépôt) ─── */
function sso_config(): array {
    $f = __DIR__ . '/secret.local.php';
    $c = is_file($f) ? require $f : [];
    return array_merge([
        'secret'           => '',   // clé HMAC — OBLIGATOIRE (voir README)
        'google_client_id' => '',   // ID client Google (facultatif mais recommandé)
        'cookie_domain'    => '',    // ex. '.luvumbu.com' pour partager entre sous-domaines
    ], is_array($c) ? $c : []);
}

function sso_ready(): bool { $c = sso_config(); return strlen((string)$c['secret']) >= 24; }

/* ─── Base64 URL ─── */
function sso_b64(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function sso_b64d(string $d): string { return (string)base64_decode(strtr($d, '-_', '+/')); }

/* ─── JWT HS256 ─── */
function sso_jwt_issue(array $claims, ?int $ttl = null): string {
    $c   = sso_config();
    $now = time();
    $claims += ['iss' => 'luvumbu-id', 'iat' => $now, 'exp' => $now + ($ttl ?? LUVID_TTL)];
    $h = sso_b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = sso_b64(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $s = sso_b64(hash_hmac('sha256', "$h.$p", (string)$c['secret'], true));
    return "$h.$p.$s";
}

function sso_jwt_verify(string $jwt): ?array {
    $c = sso_config();
    if ((string)$c['secret'] === '') return null;
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    [$h, $p, $sig] = $parts;
    $calc = sso_b64(hash_hmac('sha256', "$h.$p", (string)$c['secret'], true));
    if (!hash_equals($calc, $sig)) return null;                 // signature invalide
    $payload = json_decode(sso_b64d($p), true);
    if (!is_array($payload)) return null;
    if (isset($payload['exp']) && time() > (int)$payload['exp']) return null;   // expiré
    return $payload;
}

/* ─── Vérification d'un jeton Google (identité de départ) ─── */
function sso_google_verify(string $idToken): ?array {
    $c  = sso_config();
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $raw = curl_exec($ch); curl_close($ch);
    } else {
        $raw = @file_get_contents($url);
    }
    $d = json_decode((string)$raw, true);
    if (!is_array($d) || empty($d['email'])) return null;
    // si un client_id est configuré, l'audience doit correspondre
    if ((string)$c['google_client_id'] !== '' && ($d['aud'] ?? '') !== $c['google_client_id']) return null;
    return [
        'email'   => strtolower((string)$d['email']),
        'name'    => (string)($d['name'] ?? $d['email']),
        'sub'     => (string)($d['sub'] ?? ''),
        'picture' => (string)($d['picture'] ?? ''),
    ];
}

/* ─── Cookie partagé (single-session sur le domaine) ─── */
function sso_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function sso_cookie_set(string $jwt): void {
    $c = sso_config();
    setcookie(LUVID_COOKIE, $jwt, [
        'expires'  => time() + LUVID_TTL,
        'path'     => '/',
        'domain'   => (string)$c['cookie_domain'],
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => sso_https(),
    ]);
}
function sso_cookie_clear(): void {
    $c = sso_config();
    setcookie(LUVID_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'domain' => (string)$c['cookie_domain']]);
}

/* Identité courante d'après le cookie partagé (ou null). */
function sso_current(): ?array {
    $jwt = (string)($_COOKIE[LUVID_COOKIE] ?? '');
    return $jwt !== '' ? sso_jwt_verify($jwt) : null;
}
