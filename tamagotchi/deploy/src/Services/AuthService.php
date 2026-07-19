<?php
namespace App\Services;

use App\Core\Request;
use App\Repositories\UserRepository;

/**
 * Authentification des PARENTS via Google.
 *
 *  1. Le navigateur obtient un « ID token » Google (JWT) → l'envoie ici.
 *  2. On le vérifie auprès de Google (bon client, non expiré).
 *  3. On crée / retrouve le parent, puis on émet notre PROPRE jeton de session
 *     (signé HMAC) que le front renvoie à chaque requête.
 */
class AuthService
{
    private const DEFAULT_CLIENT_ID =
        '878381681024-6qnsarrvcrj935f56vln5uugc091gg7c.apps.googleusercontent.com';

    private string $clientId;
    private string $secret;
    private UserRepository $users;

    public function __construct(array $cfg)
    {
        $this->clientId = $cfg['google']['client_id'] ?? self::DEFAULT_CLIENT_ID;
        $this->secret   = $cfg['learning']['secret']  ?? 'tamagotchi-secret';
        $this->users    = new UserRepository();
    }

    // ---------- Connexion Google ----------

    /** Vérifie un ID token Google. Retourne les infos (sub, email, name, picture) ou null. */
    public function verifyGoogle(string $idToken): ?array
    {
        $data = $this->fetchTokenInfo($idToken);
        if (!$data) {
            return null;
        }
        // Le jeton doit être destiné à NOTRE application et non expiré.
        if (($data['aud'] ?? '') !== $this->clientId) {
            return null;
        }
        if ((int) ($data['exp'] ?? 0) < time()) {
            return null;
        }
        if (empty($data['sub'])) {
            return null;
        }
        return $data;
    }

    /** Connecte (ou inscrit) un parent à partir d'un ID token Google. Retourne l'utilisateur. */
    public function loginWithGoogle(string $idToken): ?array
    {
        $g = $this->verifyGoogle($idToken);
        if (!$g) {
            return null;
        }
        $sub     = $g['sub'];
        $email   = $g['email'] ?? ('g' . $sub . '@tamagotchi.local');
        $picture = $g['picture'] ?? null;

        $user = $this->users->findByGoogleSub($sub);
        if ($user) {
            return $user;
        }
        // Même email déjà connu → on rattache Google au compte existant.
        $existing = $this->users->findByEmail($email);
        if ($existing) {
            $this->users->linkGoogle((int) $existing['id'], $sub, $picture);
            return $this->users->findByGoogleSub($sub);
        }
        // Sinon nouveau parent.
        return $this->users->createGoogle($sub, $email, 'g_' . $sub, $picture);
    }

    // ---------- Jeton de session (le nôtre) ----------

    public function token(int $userId): string
    {
        $payload = 'u' . $userId . '.' . time();
        $sig     = hash_hmac('sha256', $payload, $this->secret);
        return base64_encode($payload . '|' . $sig);
    }

    public function userIdFromToken(?string $token): ?int
    {
        if (!$token) {
            return null;
        }
        $raw = base64_decode($token, true);
        if ($raw === false || !str_contains($raw, '|')) {
            return null;
        }
        [$payload, $sig] = explode('|', $raw, 2);
        $expected = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        if (!preg_match('/^u(\d+)\./', $payload, $m)) {
            return null;
        }
        return (int) $m[1];
    }

    /** Récupère l'ID du parent connecté à partir de la requête courante (ou null). */
    public function currentUserId(): ?int
    {
        return $this->userIdFromToken($this->readToken());
    }

    /** Lit le jeton : en-tête Authorization, sinon ?token=, sinon champ JSON "token". */
    private function readToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        if (!empty($_GET['token'])) {
            return (string) $_GET['token'];
        }
        $body = Request::body();
        return $body['token'] ?? null;
    }

    // ---------- Vérification auprès de Google ----------

    private function fetchTokenInfo(string $idToken): ?array
    {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        $json = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res !== false && $code === 200) {
                $json = $res;
            }
        }
        if ($json === null) {
            $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
            $res = @file_get_contents($url, false, $ctx);
            if ($res !== false) {
                $json = $res;
            }
        }
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }
}
