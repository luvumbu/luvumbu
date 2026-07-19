<?php
// === Comptes & authentification multi-comptes ===
// Regroupe : schéma des comptes, jetons, identification (app ou session web),
// vérification d'identifiants et flux de connexion des pages web.

final class Auth
{
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
                pass_hash  VARCHAR(255) NOT NULL,
                api_token  CHAR(64)     NOT NULL UNIQUE,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Mot de passe SPÉCIFIQUE de l'album masqué (haché). NULL = utiliser le mot de passe du compte.
        if (!$db->query("SHOW COLUMNS FROM " . TBL_USERS . " LIKE 'hidden_pass_hash'")->fetch()) {
            $db->exec("ALTER TABLE " . TBL_USERS . " ADD COLUMN hidden_pass_hash VARCHAR(255) NULL DEFAULT NULL");
        }

        // Albums (dossiers virtuels) partageables par lien, éventuellement protégés.
        $db->exec(
            "CREATE TABLE IF NOT EXISTS " . TBL_ALBUMS . " (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    INT UNSIGNED NOT NULL,
                name       VARCHAR(120) NOT NULL,
                token      CHAR(32)     NOT NULL UNIQUE,
                pass_hash  VARCHAR(255) NULL DEFAULT NULL,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_album_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS " . TBL_ALBUM_PHOTOS . " (
                album_id INT UNSIGNED    NOT NULL,
                photo_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (album_id, photo_id),
                INDEX idx_ap_photo (photo_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // La table photos doit exister (créée par install.php). On y ajoute ce qu'il faut.
        if (!$db->query("SHOW COLUMNS FROM " . TBL_PHOTOS . " LIKE 'user_id'")->fetch()) {
            $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN user_id INT UNSIGNED NULL DEFAULT NULL, ADD INDEX idx_user (user_id)");
        }
        if (!$db->query("SHOW COLUMNS FROM " . TBL_PHOTOS . " LIKE 'deleted_at'")->fetch()) {
            $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL, ADD INDEX idx_deleted (deleted_at)");
        }
        // Colonne « masquée » : photo cachée de la galerie, visible seulement dans l'album protégé.
        if (!$db->query("SHOW COLUMNS FROM " . TBL_PHOTOS . " LIKE 'hidden'")->fetch()) {
            $db->exec("ALTER TABLE " . TBL_PHOTOS . " ADD COLUMN hidden TINYINT(1) NOT NULL DEFAULT 0, ADD INDEX idx_hidden (hidden)");
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

    /** Vérifie un identifiant + mot de passe. Renvoie la ligne user, ou null. */
    public static function verifyCredentials(string $username, string $password): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, pass_hash, api_token FROM ' . TBL_USERS . ' WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($password, $u['pass_hash'])) return $u;
        return null;
    }

    /**
     * Flux de connexion partagé par les pages web (gallery.php, upload_web.php) :
     * démarre la session, assure le schéma, traite déconnexion et connexion (POST),
     * redirige le cas échéant, puis renvoie ['uid' => ?int, 'uname' => string, 'error' => string].
     */
    public static function webSession(string $self): array
    {
        self::startSession();
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
            // Page de confirmation SANS bouton de connexion : aucune reconnexion automatique.
            $selfSafe = htmlspecialchars($self, ENT_QUOTES);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
               . '<meta name="viewport" content="width=device-width, initial-scale=1">'
               . '<title>Déconnecté — PhotoSync</title>'
               . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
               . 'background:#0b1220;color:#e6edf7;font-family:system-ui,-apple-system,sans-serif;text-align:center}'
               . '.card{background:#16213a;padding:36px 32px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.5);max-width:340px}'
               . 'h1{margin:0 0 8px;font-size:22px}p{color:#8da2c0;margin:0 0 6px}'
               . 'a.btn{display:inline-block;margin-top:20px;background:#1565C0;color:#fff;text-decoration:none;'
               . 'padding:13px 24px;border-radius:12px;font-weight:700}</style></head><body>'
               . '<div class="card"><h1>✅ Déconnecté</h1>'
               . '<p>Tu es bien déconnecté de ta session.</p>'
               . '<a class="btn" href="' . $selfSafe . '">Se reconnecter</a></div>'
               . '</body></html>';
            exit;
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
            $username = trim($_POST['username']);
            $user = self::verifyCredentials($username, $_POST['password']);
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['uid']   = (int) $user['id'];
                $_SESSION['uname'] = $username;
                header("Location: $self");
                exit;
            }
            $error = 'Identifiant ou mot de passe incorrect.';
        }

        return [
            'uid'   => $_SESSION['uid'] ?? null,
            'uname' => $_SESSION['uname'] ?? '',
            'error' => $error,
        ];
    }
}
