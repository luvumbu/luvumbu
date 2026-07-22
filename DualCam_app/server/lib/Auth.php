<?php
// === Comptes & authentification multi-comptes ===
// Regroupe : schéma des comptes, jetons, identification (app ou session web),
// vérification d'identifiants et flux de connexion des pages web.

final class Auth
{
    /** Nombre d'essais ratés autorisés avant déclenchement d'un blocage. */
    const MAX_ATTEMPTS = 4;
    /** Durée du 1er blocage (1re récidive) en secondes : 30 minutes. */
    const LOCK_1 = 30 * 60;
    /** Durée du 2e blocage (2e récidive) en secondes : 4 heures. */
    const LOCK_2 = 4 * 60 * 60;

    /** Démarre la session web si ce n'est pas déjà fait. */
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /** Crée/complète les tables nécessaires aux comptes (idempotent). */
    public static function ensureSchema(): void
    {
        $db = Db::pdo();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS " . TBL_USERS . " (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username   VARCHAR(64)  NOT NULL UNIQUE,
                pass_hash  VARCHAR(255) NULL DEFAULT NULL,
                google_sub VARCHAR(64)  NULL DEFAULT NULL,
                email      VARCHAR(255) NULL DEFAULT NULL,
                is_admin   TINYINT(1)   NOT NULL DEFAULT 0,
                api_token  CHAR(64)     NOT NULL UNIQUE,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Migration des comptes existants vers la connexion Google :
        // colonnes google_sub / email, et pass_hash devient facultatif.
        foreach ([
            'google_sub' => 'VARCHAR(64) NULL DEFAULT NULL',
            'email'      => 'VARCHAR(255) NULL DEFAULT NULL',
            'is_admin'   => 'TINYINT(1) NOT NULL DEFAULT 0',
        ] as $col => $ddl) {
            if (!$db->query("SHOW COLUMNS FROM " . TBL_USERS . " LIKE '$col'")->fetch()) {
                try { $db->exec("ALTER TABLE " . TBL_USERS . " ADD COLUMN $col $ddl"); } catch (Throwable $e) {}
            }
        }
        if (!$db->query("SHOW INDEX FROM " . TBL_USERS . " WHERE Key_name='uniq_google_sub'")->fetch()) {
            try { $db->exec("ALTER TABLE " . TBL_USERS . " ADD UNIQUE KEY uniq_google_sub (google_sub)"); } catch (Throwable $e) {}
        }
        try { $db->exec("ALTER TABLE " . TBL_USERS . " MODIFY pass_hash VARCHAR(255) NULL DEFAULT NULL"); } catch (Throwable $e) {}

        // Partage public (global par compte) : ACTIVÉ par défaut (DEFAULT 1 s'applique aussi
        // aux comptes existants). share_token = identifiant du lien public partageable.
        foreach ([
            'public_share' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'share_token'  => 'CHAR(32) NULL DEFAULT NULL',
        ] as $col => $ddl) {
            if (!$db->query("SHOW COLUMNS FROM " . TBL_USERS . " LIKE '$col'")->fetch()) {
                try { $db->exec("ALTER TABLE " . TBL_USERS . " ADD COLUMN $col $ddl"); } catch (Throwable $e) {}
            }
        }

        // Filet de sécurité : s'il y a des comptes mais AUCUN admin, on promeut le plus ancien.
        // (Évite de se retrouver sans aucun administrateur, ex. comptes créés avant cette colonne.)
        try {
            $hasAdmin = (int) $db->query('SELECT COUNT(*) c FROM ' . TBL_USERS . ' WHERE is_admin = 1')->fetch(PDO::FETCH_ASSOC)['c'];
            $nbUsers  = (int) $db->query('SELECT COUNT(*) c FROM ' . TBL_USERS)->fetch(PDO::FETCH_ASSOC)['c'];
            if ($hasAdmin === 0 && $nbUsers > 0) {
                $db->exec("UPDATE " . TBL_USERS . " SET is_admin = 1 ORDER BY id ASC LIMIT 1");
            }
        } catch (Throwable $e) {}

        // Suivi des tentatives de connexion par IP (anti-force-brute).
        $db->exec(
            "CREATE TABLE IF NOT EXISTS " . TBL_ATTEMPTS . " (
                ip           VARCHAR(45)  NOT NULL PRIMARY KEY,
                fails        INT UNSIGNED NOT NULL DEFAULT 0,
                strikes      INT UNSIGNED NOT NULL DEFAULT 0,
                locked_until DATETIME     NULL DEFAULT NULL,
                blocked      TINYINT(1)   NOT NULL DEFAULT 0,
                updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // La table photos doit exister (créée par install.php). On y ajoute ce qu'il faut.
        if (!$db->query("SHOW COLUMNS FROM " . TBL_PHOTOS . " LIKE 'user_id'")->fetch()) {
            $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN user_id INT UNSIGNED NULL DEFAULT NULL, ADD INDEX idx_user (user_id)");
        }
        if (!$db->query("SHOW COLUMNS FROM " . TBL_PHOTOS . " LIKE 'deleted_at'")->fetch()) {
            $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL, ADD INDEX idx_deleted (deleted_at)");
        }
        // Origine de chaque média : 'phone' (app) ou 'web' (page d'envoi du site).
        if (!$db->query("SHOW COLUMNS FROM " . TBL_PHOTOS . " LIKE 'source'")->fetch()) {
            $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'phone'");
        }

        // Le hash ne doit plus être unique GLOBALEMENT, mais unique PAR COMPTE.
        if ($db->query("SHOW INDEX FROM " . TBL_PHOTOS . " WHERE Key_name='sha256'")->fetch()) {
            try { $db->exec("ALTER TABLE " . TBL_PHOTOS . " DROP INDEX sha256"); } catch (Throwable $e) {}
        }
        if (!$db->query("SHOW INDEX FROM " . TBL_PHOTOS . " WHERE Key_name='uniq_user_sha'")->fetch()) {
            try { $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD UNIQUE KEY uniq_user_sha (user_id, sha256)"); } catch (Throwable $e) {}
        }
    }

    /** Jeton aléatoire (64 caractères hexadécimaux). */
    public static function genToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    // ====================================================================
    // ===  Partage public (global par compte)                         ===
    // ====================================================================

    /** Le partage public est-il actif pour ce compte ? (défaut : oui) */
    public static function isPublicShare(int $uid): bool
    {
        $st = Db::pdo()->prepare('SELECT public_share FROM ' . TBL_USERS . ' WHERE id = ?');
        $st->execute([$uid]);
        return (bool) ($st->fetch(PDO::FETCH_ASSOC)['public_share'] ?? 0);
    }

    /** Active (true) ou désactive (false) le partage public du compte. */
    public static function setPublicShare(int $uid, bool $on): void
    {
        Db::pdo()->prepare('UPDATE ' . TBL_USERS . ' SET public_share = ? WHERE id = ?')
            ->execute([$on ? 1 : 0, $uid]);
    }

    /** Jeton de lien public du compte (créé à la première demande, 32 hexa). */
    public static function shareToken(int $uid): string
    {
        $db = Db::pdo();
        $st = $db->prepare('SELECT share_token FROM ' . TBL_USERS . ' WHERE id = ?');
        $st->execute([$uid]);
        $t = (string) ($st->fetch(PDO::FETCH_ASSOC)['share_token'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
            $t = bin2hex(random_bytes(16));
            $db->prepare('UPDATE ' . TBL_USERS . ' SET share_token = ? WHERE id = ?')->execute([$t, $uid]);
        }
        return $t;
    }

    /** Compte dont le partage est ACTIF à partir d'un jeton de lien public ; sinon null. */
    public static function userIdFromShareToken(string $t): ?int
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $t)) return null;
        $st = Db::pdo()->prepare('SELECT id FROM ' . TBL_USERS . ' WHERE share_token = ? AND public_share = 1');
        $st->execute([$t]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? (int) $r['id'] : null;
    }

    /** URL absolue de la page de partage public pour un jeton donné. */
    public static function shareUrl(string $token): string
    {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Racine DualCam = deux niveaux au-dessus du script courant (/DualCam/api/x ou /DualCam/web/x).
        $root   = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        return $scheme . '://' . $host . $root . '/web/share.php?t=' . $token;
    }

    // ====================================================================
    // ===  Connexion « Se connecter avec Google »                      ===
    // ====================================================================

    /**
     * Vérifie un jeton d'identité Google (ID token) auprès de Google et renvoie
     * le profil ['sub','email','name'] si tout est valide, sinon null.
     * 'sub' = identifiant Google permanent et unique du compte.
     */
    public static function verifyGoogleIdToken(string $idToken): ?array
    {
        $idToken = trim($idToken);
        if ($idToken === '') return null;
        if (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === '') {
            throw new RuntimeException("Connexion Google non configurée : renseigne GOOGLE_CLIENT_ID dans lib/config.php.");
        }

        $raw = self::httpsGet('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
        if ($raw === null) return null;

        $d = json_decode($raw, true);
        if (!is_array($d) || empty($d['sub'])) return null;

        // Le jeton doit avoir été émis POUR notre application, par Google, et non expiré.
        if (($d['aud'] ?? '') !== GOOGLE_CLIENT_ID) return null;
        $iss = $d['iss'] ?? '';
        if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') return null;
        if (!isset($d['exp']) || (int) $d['exp'] < time()) return null;

        return [
            'sub'   => (string) $d['sub'],
            'email' => (string) ($d['email'] ?? ''),
            'name'  => (string) ($d['name'] ?? ($d['email'] ?? 'Utilisateur')),
        ];
    }

    /** Requête HTTPS GET simple (cURL si dispo, sinon flux). Renvoie le corps ou null. */
    private static function httpsGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($res !== false && $code === 200) ? $res : null;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : $res;
    }

    /** Récupère le jeton d'API interne d'un compte (utilisé par l'app). */
    private static function tokenForUser(int $uid): string
    {
        $st = Db::pdo()->prepare('SELECT api_token FROM ' . TBL_USERS . ' WHERE id = ?');
        $st->execute([$uid]);
        return (string) ($st->fetch(PDO::FETCH_ASSOC)['api_token'] ?? '');
    }

    /** Cet utilisateur (par son id) est-il administrateur ? */
    public static function isAdmin(int $uid): bool
    {
        if ($uid <= 0) return false;
        $st = Db::pdo()->prepare('SELECT is_admin FROM ' . TBL_USERS . ' WHERE id = ?');
        $st->execute([$uid]);
        return (bool) ($st->fetch(PDO::FETCH_ASSOC)['is_admin'] ?? 0);
    }

    /** L'e-mail figure-t-il dans la liste des administrateurs (config ADMIN_EMAILS) ? */
    public static function isAdminEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !defined('ADMIN_EMAILS') || ADMIN_EMAILS === '') return false;
        foreach (explode(',', ADMIN_EMAILS) as $a) {
            if (strtolower(trim($a)) === $email) return true;
        }
        return false;
    }

    /** Fabrique un identifiant d'affichage unique à partir d'un email ou d'un nom. */
    private static function uniqueUsername(string $seed): string
    {
        $base = explode('@', $seed)[0];
        $base = strtolower((string) preg_replace('/[^A-Za-z0-9_.-]/', '', $base));
        if (strlen($base) < 3) $base = 'user' . $base;
        $base = substr($base, 0, 50);

        $db = Db::pdo();
        $name = $base;
        $i = 1;
        while (true) {
            $st = $db->prepare('SELECT id FROM ' . TBL_USERS . ' WHERE username = ?');
            $st->execute([$name]);
            if (!$st->fetch()) return $name;
            $name = $base . (++$i);
        }
    }

    /**
     * Connexion via Google : vérifie le jeton, retrouve OU crée le compte lié au
     * compte Google, et renvoie le jeton interne de l'app (logique app + web).
     * Renvoie ['ok'=>true,'uid','token','username'] ou ['ok'=>false,'error','code'].
     */
    public static function loginWithGoogle(string $idToken): array
    {
        self::ensureSchema();

        $profile = self::verifyGoogleIdToken($idToken);
        if ($profile === null) {
            return ['ok' => false, 'error' => 'Connexion Google refusée (jeton invalide ou expiré).', 'code' => 401];
        }

        $db = Db::pdo();

        $emailIsAdmin = self::isAdminEmail($profile['email']);

        // Compte déjà lié à ce compte Google ?
        $st = $db->prepare('SELECT id, username FROM ' . TBL_USERS . ' WHERE google_sub = ?');
        $st->execute([$profile['sub']]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $uid = (int) $u['id'];
            // On garde l'e-mail à jour et on (re)donne l'admin si l'e-mail est dans la liste.
            if ($profile['email'] !== '') {
                $db->prepare('UPDATE ' . TBL_USERS . ' SET email = ? WHERE id = ?')->execute([$profile['email'], $uid]);
            }
            if ($emailIsAdmin) {
                $db->prepare('UPDATE ' . TBL_USERS . ' SET is_admin = 1 WHERE id = ?')->execute([$uid]);
            }
            return ['ok' => true, 'uid' => $uid, 'token' => self::tokenForUser($uid), 'username' => $u['username']];
        }

        // Premier passage de ce compte Google : on crée le compte
        // (admin direct si son e-mail est dans la liste ADMIN_EMAILS).
        $username = self::uniqueUsername($profile['email'] !== '' ? $profile['email'] : $profile['name']);
        $token    = self::genToken();
        $db->prepare('INSERT INTO ' . TBL_USERS . ' (username, google_sub, email, is_admin, api_token) VALUES (?,?,?,?,?)')
           ->execute([$username, $profile['sub'], $profile['email'], $emailIsAdmin ? 1 : 0, $token]);
        $uid = (int) $db->lastInsertId();

        // Tout premier compte du serveur : il récupère les photos « orphelines »
        // et devient automatiquement administrateur.
        $nbUsers = (int) $db->query('SELECT COUNT(*) c FROM ' . TBL_USERS)->fetch(PDO::FETCH_ASSOC)['c'];
        if ($nbUsers === 1) {
            $db->prepare('UPDATE ' . TBL_PHOTOS . ' SET user_id = ? WHERE user_id IS NULL')->execute([$uid]);
            $db->prepare('UPDATE ' . TBL_USERS . ' SET is_admin = 1 WHERE id = ?')->execute([$uid]);
        }

        return ['ok' => true, 'uid' => $uid, 'token' => $token, 'username' => $username];
    }

    /** Bloc HTML du bouton « Se connecter avec Google » (utilisé par les pages web). */
    public static function googleButtonHtml(): string
    {
        $cid = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
        if ($cid === '') {
            return '<p class="err">⚠️ Connexion Google non configurée : renseigne <b>GOOGLE_CLIENT_ID</b> dans lib/config.php.</p>';
        }
        $cidAttr = htmlspecialchars($cid, ENT_QUOTES);
        return <<<HTML
<!-- photosync-auth: v2-logout-fix -->
<script src="https://accounts.google.com/gsi/client" async></script>
<div id="g_id_onload" data-client_id="{$cidAttr}" data-callback="onGoogleCredential" data-auto_prompt="false" data-auto_select="false"></div>
<div class="g_id_signin" data-type="standard" data-theme="filled_blue" data-size="large" data-text="signin_with" data-locale="fr"></div>
<form id="googleForm" method="post" style="display:none"><input type="hidden" name="credential" id="googleCred"></form>
<script>
  function onGoogleCredential(r){document.getElementById('googleCred').value=r.credential;document.getElementById('googleForm').submit();}
  // Après une déconnexion : empêche Google de re-sélectionner le compte automatiquement.
  window.addEventListener('load', function(){
    try { if (window.google && google.accounts && google.accounts.id) google.accounts.id.disableAutoSelect(); } catch (e) {}
  });
</script>
HTML;
    }

    /**
     * Crée un compte en base (logique partagée par l'app et le web).
     * Valide les champs, refuse les doublons, insère l'utilisateur et — pour le
     * tout premier compte — lui rattache les photos « orphelines ».
     *
     * Renvoie ['ok' => true, 'uid' => int, 'token' => string, 'username' => string]
     * en cas de succès, ou ['ok' => false, 'error' => string, 'code' => int] sinon.
     */
    public static function createAccount(string $username, string $password): array
    {
        self::ensureSchema();

        $username = trim($username);
        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
            return ['ok' => false, 'error' => 'Identifiant : 3 à 64 caractères (lettres, chiffres, _ . -)', 'code' => 400];
        }
        if (strlen($password) < 4) {
            return ['ok' => false, 'error' => 'Mot de passe : 4 caractères minimum', 'code' => 400];
        }

        $db = Db::pdo();

        $st = $db->prepare('SELECT id FROM ' . TBL_USERS . ' WHERE username = ?');
        $st->execute([$username]);
        if ($st->fetch()) {
            return ['ok' => false, 'error' => 'Cet identifiant est déjà pris', 'code' => 409];
        }

        $token = self::genToken();
        $db->prepare('INSERT INTO ' . TBL_USERS . ' (username, pass_hash, api_token) VALUES (?,?,?)')
           ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $token]);
        $uid = (int) $db->lastInsertId();

        // Premier compte créé : il récupère les photos « orphelines » envoyées avant les comptes.
        $nbUsers = (int) $db->query('SELECT COUNT(*) c FROM ' . TBL_USERS)->fetch(PDO::FETCH_ASSOC)['c'];
        if ($nbUsers === 1) {
            $db->prepare('UPDATE ' . TBL_PHOTOS . ' SET user_id = ? WHERE user_id IS NULL')->execute([$uid]);
        }

        return ['ok' => true, 'uid' => $uid, 'token' => $token, 'username' => $username];
    }

    /** Identifie le compte depuis le jeton de l'app (X-Auth-Token / ?token / POST token). */
    public static function userIdFromToken(): ?int
    {
        $t = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? ($_GET['token'] ?? ($_POST['token'] ?? ''));
        if (!is_string($t) || strlen($t) < 32) return null;
        $st = Db::pdo()->prepare('SELECT id FROM ' . TBL_USERS . ' WHERE api_token = ?');
        $st->execute([$t]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? (int) $r['id'] : null;
    }

    /** Compte courant : session web (uid) en priorité, sinon jeton app. */
    public static function currentUserId(): ?int
    {
        if (!empty($_SESSION['uid'])) return (int) $_SESSION['uid'];
        return self::userIdFromToken();
    }

    /** Exige un compte identifié par le jeton de l'app ; sinon réponse JSON 401 (API). */
    public static function requireToken(): int
    {
        $uid = self::userIdFromToken();
        if ($uid === null) Api::fail('Jeton de compte invalide', 401);
        return $uid;
    }

    /** Exige un compte (jeton d'app OU session web) ; sinon réponse JSON 401 (API). */
    public static function requireUser(): int
    {
        $uid = self::currentUserId();
        if ($uid === null) Api::fail('Compte non identifié (reconnecte-toi)', 401);
        return $uid;
    }

    /** Vérifie un identifiant + mot de passe. Renvoie la ligne user, ou null. */
    public static function verifyCredentials(string $username, string $password): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, pass_hash, api_token FROM ' . TBL_USERS . ' WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($password, $u['pass_hash'])) return $u;
        return null;
    }

    /** Adresse IP du client (gère un éventuel proxy de confiance). */
    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return substr($ip, 0, 45);
    }

    /**
     * Vérifie si l'IP courante a le droit de tenter une connexion.
     * Renvoie '' si la voie est libre, sinon un message d'erreur expliquant le blocage.
     */
    public static function guardCheck(string $ip): string
    {
        $st = Db::pdo()->prepare('SELECT blocked, locked_until FROM ' . TBL_ATTEMPTS . ' WHERE ip = ?');
        $st->execute([$ip]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return '';

        if ((int) $row['blocked'] === 1) {
            return 'Trop de tentatives répétées : votre adresse IP a été bloquée. Contactez l\'administrateur.';
        }
        if (!empty($row['locked_until'])) {
            $remaining = strtotime($row['locked_until']) - time();
            if ($remaining > 0) {
                $min = (int) ceil($remaining / 60);
                return 'Trop de tentatives. Réessayez dans ' . $min . ' minute' . ($min > 1 ? 's' : '') . '.';
            }
        }
        return '';
    }

    /**
     * Enregistre un échec de connexion pour l'IP et applique l'escalade :
     * 4 essais ratés → blocage 30 min ; récidive → 4 h ; récidive → blocage IP définitif.
     * Renvoie le message d'erreur à afficher.
     */
    public static function recordFailure(string $ip): string
    {
        $db = Db::pdo();
        // Crée la ligne au besoin puis incrémente le compteur d'échecs.
        $db->prepare(
            'INSERT INTO ' . TBL_ATTEMPTS . ' (ip, fails, updated_at)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE fails = fails + 1, updated_at = NOW()'
        )->execute([$ip]);

        $st = $db->prepare('SELECT fails, strikes FROM ' . TBL_ATTEMPTS . ' WHERE ip = ?');
        $st->execute([$ip]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $fails   = (int) $row['fails'];
        $strikes = (int) $row['strikes'];

        if ($fails < self::MAX_ATTEMPTS) {
            $left = self::MAX_ATTEMPTS - $fails;
            return 'Identifiant ou mot de passe incorrect. Il vous reste ' . $left . ' essai' . ($left > 1 ? 's' : '') . '.';
        }

        // Seuil atteint : on déclenche une sanction et on remet le compteur d'essais à zéro.
        $strikes++;
        if ($strikes === 1) {
            $db->prepare('UPDATE ' . TBL_ATTEMPTS . ' SET fails = 0, strikes = ?, locked_until = ?, updated_at = NOW() WHERE ip = ?')
               ->execute([$strikes, date('Y-m-d H:i:s', time() + self::LOCK_1), $ip]);
            return 'Trop de tentatives. Connexion bloquée pendant 30 minutes.';
        }
        if ($strikes === 2) {
            $db->prepare('UPDATE ' . TBL_ATTEMPTS . ' SET fails = 0, strikes = ?, locked_until = ?, updated_at = NOW() WHERE ip = ?')
               ->execute([$strikes, date('Y-m-d H:i:s', time() + self::LOCK_2), $ip]);
            return 'Récidive : connexion bloquée pendant 4 heures.';
        }
        // 3e sanction : blocage définitif de l'IP.
        $db->prepare('UPDATE ' . TBL_ATTEMPTS . ' SET fails = 0, strikes = ?, blocked = 1, locked_until = NULL, updated_at = NOW() WHERE ip = ?')
           ->execute([$strikes, $ip]);
        return 'Récidive répétée : votre adresse IP a été bloquée définitivement.';
    }

    /** Réinitialise le suivi des tentatives après une connexion réussie. */
    public static function clearFailures(string $ip): void
    {
        Db::pdo()->prepare('DELETE FROM ' . TBL_ATTEMPTS . ' WHERE ip = ?')->execute([$ip]);
    }

    /**
     * Flux de connexion partagé par les pages web (gallery.php, upload_web.php) :
     * démarre la session, assure le schéma, traite déconnexion et connexion (POST),
     * redirige le cas échéant, puis renvoie ['uid' => ?int, 'uname' => string, 'error' => string].
     */
    public static function webSession(string $self): array
    {
        self::startSession();

        // Base pas encore configurée → on envoie vers l'assistant (les pages web sont dans web/).
        if (!Db::isReady()) {
            header('Location: ../install.php');
            exit;
        }
        self::ensureSchema();

        if (isset($_GET['logout'])) {
            $_SESSION = [];
            // Efface aussi le cookie de session, sinon la session peut « ressusciter ».
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            // Page de confirmation SANS bouton Google : impossible d'être reconnecté
            // automatiquement. On désactive aussi la re-sélection auto de Google pour la suite.
            $selfSafe = htmlspecialchars($self, ENT_QUOTES);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
               . '<meta name="viewport" content="width=device-width, initial-scale=1">'
               . '<title>Déconnecté — PhotoSync</title>'
               . '<script src="https://accounts.google.com/gsi/client" async></script>'
               . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
               . 'background:#0b1220;color:#e6edf7;font-family:system-ui,-apple-system,sans-serif;text-align:center}'
               . '.card{background:#16213a;padding:36px 32px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.5);max-width:340px}'
               . 'h1{margin:0 0 8px;font-size:22px}p{color:#8da2c0;margin:0 0 6px}'
               . 'a.btn{display:inline-block;margin-top:20px;background:#1565C0;color:#fff;text-decoration:none;'
               . 'padding:13px 24px;border-radius:12px;font-weight:700}</style></head><body>'
               . '<div class="card"><h1>✅ Déconnecté</h1>'
               . '<p>Tu es bien déconnecté de ta session.</p>'
               . '<a class="btn" href="' . $selfSafe . '">Se reconnecter</a></div>'
               . '<script>'
               . 'function _d(){try{if(window.google&&google.accounts&&google.accounts.id){google.accounts.id.disableAutoSelect();return true}}catch(e){}return false}'
               . 'var _t=setInterval(function(){if(_d())clearInterval(_t)},200);setTimeout(function(){clearInterval(_t)},4000);'
               . '</script></body></html>';
            exit;
        }

        $error = '';
        // Connexion via Google : la page renvoie le jeton d'identité dans "credential".
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
            try {
                $res = self::loginWithGoogle((string) $_POST['credential']);
            } catch (Throwable $e) {
                $res = ['ok' => false, 'error' => $e->getMessage()];
            }
            if (!empty($res['ok'])) {
                session_regenerate_id(true);
                $_SESSION['uid']   = (int) $res['uid'];
                $_SESSION['uname'] = $res['username'];
                header("Location: $self");
                exit;
            }
            $error = $res['error'] ?? 'Connexion Google impossible.';
        }

        return [
            'uid'   => $_SESSION['uid'] ?? null,
            'uname' => $_SESSION['uname'] ?? '',
            'error' => $error,
        ];
    }
}
