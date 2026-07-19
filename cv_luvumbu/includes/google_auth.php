<?php
/**
 * Connexion avec Google (OpenID Connect, sans bibliothèque externe).
 *
 * Principe pour cette application mono-compte :
 *  - l'administrateur renseigne son adresse e-mail dans Paramètres ;
 *  - il colle l'« ID client » et le « secret client » Google dans Paramètres ;
 *  - toute connexion Google dont l'e-mail VÉRIFIÉ correspond à celui du compte
 *    ouvre la session. Aucune liaison de compte séparée n'est nécessaire.
 *
 * L'« URI de redirection autorisé » à déclarer dans la console Google est
 * affiché dans Paramètres : c'est <base>/google_callback.php.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/account.php';

const GOOGLE_AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

/*
 * Identifiants Google par défaut.
 * Les vraies valeurs sont stockées HORS dépôt dans « google_secrets.local.php »
 * (ignoré par Git). Copie « google_secrets.local.example.php » vers ce nom et
 * renseigne tes clés pour un fonctionnement « clés en main ».
 * Les valeurs saisies dans Paramètres → Connexion avec Google gardent la priorité.
 */
$__google_secrets_file = __DIR__ . '/google_secrets.local.php';
if (is_file($__google_secrets_file)) {
    require $__google_secrets_file;
}
if (!defined('GOOGLE_DEFAULT_CLIENT_ID')) {
    define('GOOGLE_DEFAULT_CLIENT_ID', '');
}
if (!defined('GOOGLE_DEFAULT_CLIENT_SECRET')) {
    define('GOOGLE_DEFAULT_CLIENT_SECRET', '');
}

/** Identifiants Google effectifs : valeurs de Paramètres, sinon valeurs par défaut. */
function google_config(): array
{
    $id     = trim((string) get_setting('google_client_id', ''));
    $secret = trim((string) get_setting('google_client_secret', ''));
    if ($id === '') {
        $id = GOOGLE_DEFAULT_CLIENT_ID;
    }
    if ($secret === '') {
        $secret = GOOGLE_DEFAULT_CLIENT_SECRET;
    }
    return ['client_id' => $id, 'client_secret' => $secret];
}

/** La connexion Google est-elle configurée (ID + secret présents) ? */
function google_enabled(): bool
{
    $c = google_config();
    return $c['client_id'] !== '' && $c['client_secret'] !== '';
}

/**
 * Liste des adresses Google autorisées à se connecter (liste blanche).
 * Stockée en un seul réglage (séparées par retour à la ligne, virgule ou point-virgule).
 * Renvoyée normalisée : minuscules, dédoublonnée, e-mails valides uniquement.
 */
function google_allowed_emails(): array
{
    $raw   = (string) get_setting('google_allowed_emails', '');
    $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $emails = [];
    foreach ($parts as $p) {
        $e = strtolower(trim($p));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $e;
        }
    }
    return array_values(array_unique($emails));
}

/** L'adresse Google donnée figure-t-elle dans la liste blanche ? */
function is_google_email_allowed(string $email): bool
{
    $email = strtolower(trim($email));
    return $email !== '' && in_array($email, google_allowed_emails(), true);
}

/** Enregistre la liste blanche (normalise, dédoublonne, ignore les invalides). */
function set_google_allowed_emails(array $emails): void
{
    $clean = [];
    foreach ($emails as $e) {
        $e = strtolower(trim($e));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $clean[] = $e;
        }
    }
    set_setting('google_allowed_emails', implode("\n", array_values(array_unique($clean))));
}

/** Ajoute une adresse à la liste blanche. Renvoie true si ajoutée, false sinon. */
function google_add_allowed_email(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false; // invalide
    }
    $list = google_allowed_emails();
    if (in_array($email, $list, true)) {
        return false; // déjà présente
    }
    $list[] = $email;
    set_google_allowed_emails($list);
    return true;
}

/** Retire une adresse de la liste blanche. */
function google_remove_allowed_email(string $email): void
{
    $email = strtolower(trim($email));
    $list  = array_filter(google_allowed_emails(), static fn($e) => $e !== $email);
    set_google_allowed_emails($list);
}

/** URI de redirection à déclarer dans la console Google. */
function google_redirect_uri(): string
{
    return app_base_url() . '/google_callback.php';
}

/**
 * Construit l'URL d'autorisation Google et mémorise un jeton anti-CSRF (state)
 * en session. À appeler après session_start().
 */
function google_auth_url(): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id'     => google_config()['client_id'],
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    return GOOGLE_AUTH_ENDPOINT . '?' . http_build_query($params);
}

/** Vérifie le paramètre state renvoyé par Google (protection CSRF). */
function google_check_state(?string $state): bool
{
    $expected = $_SESSION['google_oauth_state'] ?? null;
    unset($_SESSION['google_oauth_state']); // usage unique
    return $expected !== null && is_string($state) && hash_equals($expected, $state);
}

/**
 * Échange le code d'autorisation contre les jetons puis renvoie les infos
 * du profil Google : ['sub','email','email_verified','name'].
 * Lève une exception en cas d'échec.
 */
function google_exchange_code(string $code): array
{
    $cfg = google_config();
    $resp = http_post_form(GOOGLE_TOKEN_ENDPOINT, [
        'code'          => $code,
        'client_id'     => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);

    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data['id_token'])) {
        throw new RuntimeException("Réponse Google invalide lors de l'échange du code.");
    }

    // Le jeton d'identité provient DIRECTEMENT du point de terminaison Google via
    // HTTPS : sa charge utile peut être lue sans revérifier la signature.
    $claims = decode_jwt_payload($data['id_token']);
    if (!is_array($claims) || empty($claims['sub'])) {
        throw new RuntimeException("Jeton d'identité Google illisible.");
    }

    return [
        'sub'            => (string) ($claims['sub'] ?? ''),
        'email'          => (string) ($claims['email'] ?? ''),
        'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'name'           => (string) ($claims['name'] ?? ''),
    ];
}

/** Décode (sans vérifier la signature) la charge utile d'un JWT. */
function decode_jwt_payload(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return null;
    }
    $payload = strtr($parts[1], '-_', '+/');
    $payload = str_pad($payload, strlen($payload) + (4 - strlen($payload) % 4) % 4, '=');
    $json = base64_decode($payload, true);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * POST application/x-www-form-urlencoded. Utilise cURL si disponible,
 * sinon un flux HTTP (file_get_contents). Renvoie le corps de la réponse.
 */
function http_post_form(string $url, array $fields): string
{
    $body = http_build_query($fields);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException("Appel à Google impossible : " . $err);
        }
        return (string) $resp;
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => $body,
        'timeout'       => 15,
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        throw new RuntimeException("Appel à Google impossible (réseau).");
    }
    return $resp;
}
