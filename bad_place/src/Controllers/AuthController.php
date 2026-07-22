<?php

namespace App\Controllers;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Authentification : inscription, connexion, session, rafraîchissement, déconnexion.
 * Émet un access token (JWT court) + un refresh token (stocké/révocable en base).
 */
final class AuthController
{
    /** POST /api/v1/auth/register */
    public function register(Request $request): Response
    {
        $data = Validator::make($request->all(), [
            'display_name' => 'required|string|min:2|max:100',
            'email'        => 'required|email|max:255',
            'password'     => 'required|string|min:8|max:200',
        ], [
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'email.email'  => 'Adresse email invalide.',
        ])->validate();

        $email = mb_strtolower(trim($data['email']));

        if (Database::selectOne('SELECT id FROM users WHERE email = ?', [$email])) {
            throw HttpException::validation(['email' => ['Cette adresse email est déjà utilisée.']]);
        }

        $userId = Database::insert(
            'INSERT INTO users (uuid, email, password_hash, display_name, role, status, locale, email_verified_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL)',
            [str_uuid(), $email, password_hash($data['password'], PASSWORD_DEFAULT),
             trim($data['display_name']), 'member', 'active', 'fr']
        );

        // Trace du consentement (RGPD)
        Database::execute(
            'INSERT INTO consents (user_id, consent_type, granted, version, ip_hash) VALUES (?, ?, 1, ?, ?)',
            [$userId, 'terms', '1.0', Crypto::pseudonymize($request->ip())]
        );

        return $this->issueSession($request, $userId, 201);
    }

    /** POST /api/v1/auth/login */
    public function login(Request $request): Response
    {
        $data = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ])->validate();

        $email = mb_strtolower(trim($data['email']));
        $user = Database::selectOne('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        if (!$user || !$user['password_hash'] || !password_verify($data['password'], $user['password_hash'])) {
            throw new HttpException('Email ou mot de passe incorrect.', 401, [], 'INVALID_CREDENTIALS');
        }
        if (in_array($user['status'], ['banned', 'deleted'], true)) {
            throw HttpException::forbidden('Ce compte est désactivé.');
        }

        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        return $this->issueSession($request, (int) $user['id']);
    }

    /** GET /api/v1/auth/me  (protégé) */
    public function me(Request $request): Response
    {
        $user = $request->user();
        return Response::success(['user' => $this->publicUser($user)]);
    }

    /** POST /api/v1/auth/refresh */
    public function refresh(Request $request): Response
    {
        $token = $request->input('refresh_token') ?: $request->bearerToken();
        if (!$token) {
            throw HttpException::unauthorized('Refresh token manquant.');
        }

        $payload = Jwt::decode($token);
        if (($payload['type'] ?? null) !== 'refresh') {
            throw HttpException::unauthorized('Type de jeton invalide.');
        }

        $stored = Database::selectOne(
            'SELECT * FROM refresh_tokens WHERE jti = ? LIMIT 1',
            [$payload['jti'] ?? '']
        );
        if (!$stored || $stored['revoked_at'] !== null || strtotime($stored['expires_at']) < time()) {
            throw HttpException::unauthorized('Session expirée, reconnectez-vous.');
        }

        // Rotation : on révoque l'ancien refresh token
        Database::execute('UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = ?', [$stored['id']]);

        return $this->issueSession($request, (int) $stored['user_id']);
    }

    /** POST /api/v1/auth/logout  (protégé) */
    public function logout(Request $request): Response
    {
        $token = $request->input('refresh_token');
        if ($token) {
            try {
                $payload = Jwt::decode($token);
                if (($payload['jti'] ?? null)) {
                    Database::execute('UPDATE refresh_tokens SET revoked_at = NOW() WHERE jti = ?', [$payload['jti']]);
                }
            } catch (\Throwable) { /* jeton déjà invalide : rien à faire */ }
        }
        return Response::success(['message' => 'Déconnecté.']);
    }

    /** GET /api/v1/auth/providers — indique au front quels fournisseurs OAuth sont actifs. */
    public function providers(Request $request): Response
    {
        return Response::success([
            'google' => [
                'enabled'   => (string) config('oauth.google.client_id') !== '',
                'client_id' => (string) config('oauth.google.client_id'),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/google
     * Reçoit le "credential" (ID token) de Google Identity Services, le vérifie,
     * puis connecte ou crée l'utilisateur (liaison par email si le compte existe déjà).
     */
    public function google(Request $request): Response
    {
        $clientId = (string) config('oauth.google.client_id');
        if ($clientId === '') {
            throw new HttpException('La connexion Google n\'est pas configurée.', 503, [], 'OAUTH_DISABLED');
        }

        $credential = (string) $request->input('credential', '');
        if ($credential === '') {
            throw HttpException::validation(['credential' => ['Jeton Google manquant.']]);
        }

        $claims = $this->verifyGoogleToken($credential, $clientId);

        $sub     = (string) ($claims['sub'] ?? '');
        $email   = mb_strtolower(trim((string) ($claims['email'] ?? '')));
        $name    = trim((string) ($claims['name'] ?? '')) ?: ($email ? explode('@', $email)[0] : 'Utilisateur Google');
        $picture = $claims['picture'] ?? null;

        if ($sub === '') {
            throw HttpException::unauthorized('Jeton Google incomplet.');
        }

        $userId = Database::transaction(function () use ($sub, $email, $name, $picture, $request) {
            // 1) Déjà lié à ce compte Google ?
            $link = Database::selectOne(
                "SELECT user_id FROM oauth_accounts WHERE provider = 'google' AND provider_user_id = ? LIMIT 1",
                [$sub]
            );
            if ($link) {
                return (int) $link['user_id'];
            }

            // 2) Un compte existe déjà avec cet email → on le lie
            $existing = $email ? Database::selectOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]) : null;
            if ($existing) {
                $uid = (int) $existing['id'];
            } else {
                // 3) Création d'un nouveau compte
                $uid = Database::insert(
                    'INSERT INTO users (uuid, email, email_verified_at, display_name, role, status, locale, avatar_url)
                     VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)',
                    [str_uuid(), $email ?: null, $name, 'member', 'active', 'fr', $picture]
                );
                Database::execute(
                    'INSERT INTO consents (user_id, consent_type, granted, version, ip_hash) VALUES (?, ?, 1, ?, ?)',
                    [$uid, 'terms', '1.0', Crypto::pseudonymize($request->ip())]
                );
            }

            Database::execute(
                'INSERT INTO oauth_accounts (user_id, provider, provider_user_id) VALUES (?, ?, ?)',
                [$uid, 'google', $sub]
            );
            return $uid;
        });

        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);

        return $this->issueSession($request, $userId);
    }

    /** Vérifie la signature et les claims d'un ID token Google. */
    private function verifyGoogleToken(string $credential, string $clientId): array
    {
        try {
            $keys    = \Firebase\JWT\JWK::parseKeySet($this->googleJwks());
            $decoded = (array) \Firebase\JWT\JWT::decode($credential, $keys);
        } catch (\Throwable $e) {
            logger('Vérification Google échouée: ' . $e->getMessage(), 'WARN');
            throw HttpException::unauthorized('Jeton Google invalide ou expiré.');
        }

        $iss = $decoded['iss'] ?? '';
        if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw HttpException::unauthorized('Émetteur du jeton Google invalide.');
        }
        if (($decoded['aud'] ?? '') !== $clientId) {
            throw HttpException::unauthorized('Jeton Google destiné à une autre application.');
        }
        return $decoded;
    }

    /** Récupère (avec cache 1h) les clés publiques Google pour vérifier les jetons. */
    private function googleJwks(): array
    {
        $cacheFile = storage_path('cache/google_jwks.json');
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $ch = curl_init('https://www.googleapis.com/oauth2/v3/certs');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            throw new HttpException('Impossible de contacter Google pour vérifier le jeton.', 503, [], 'OAUTH_UNAVAILABLE');
        }
        $jwks = json_decode((string) $body, true);
        if (!is_array($jwks)) {
            throw new HttpException('Réponse de vérification Google invalide.', 503);
        }
        @mkdir(dirname($cacheFile), 0775, true);
        @file_put_contents($cacheFile, $body);
        return $jwks;
    }

    /** Crée la session (access + refresh) et renvoie l'utilisateur. */
    private function issueSession(Request $request, int $userId, int $status = 200): Response
    {
        $user = Database::selectOne('SELECT * FROM users WHERE id = ?', [$userId]);

        $jti = str_uuid();
        Database::insert(
            'INSERT INTO refresh_tokens (user_id, jti, expires_at, user_agent, ip_hash)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)',
            [$userId, $jti, (int) config('jwt.refresh_ttl'),
             mb_substr((string) $request->header('User-Agent', ''), 0, 255),
             Crypto::pseudonymize($request->ip())]
        );

        return Response::success([
            'user'          => $this->publicUser($user),
            'access_token'  => Jwt::issueAccessToken($userId, $user['role']),
            'refresh_token' => Jwt::issueRefreshToken($userId, $jti),
            'token_type'    => 'Bearer',
            'expires_in'    => (int) config('jwt.access_ttl'),
        ], $status);
    }

    /** Représentation publique (sans données sensibles). */
    private function publicUser(array $user): array
    {
        return [
            'id'           => (int) $user['id'],
            'uuid'         => $user['uuid'],
            'display_name' => $user['display_name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'avatar_url'   => $user['avatar_url'] ?? null,
        ];
    }
}
