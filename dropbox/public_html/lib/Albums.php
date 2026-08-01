<?php
// === Albums partageables (dossiers virtuels) ===
// Un album regroupe des photos d'UN compte ; une même photo peut figurer dans
// plusieurs albums (table de liaison). Le partage se fait par un jeton secret
// dans l'URL : web/share.php?a=<token>. Options : mot de passe, date d'expiration.
// Le visiteur du lien n'a besoin d'aucun compte (lecture + téléchargement).

final class Albums
{
    /** Longueur du jeton en caractères hexadécimaux (16 octets = 32 hex). */
    const TOKEN_LEN = 32;

    /** Crée les tables si elles manquent (appelée par les pages qui s'en servent). */
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $db = Db::pdo();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS " . TBL_ALBUMS . " (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    INT UNSIGNED NOT NULL,
                name       VARCHAR(120)  NOT NULL,
                token      CHAR(32)      NOT NULL UNIQUE,
                pass_hash  VARCHAR(255)  NULL DEFAULT NULL,
                expires_at DATETIME      NULL DEFAULT NULL,
                created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS " . TBL_ALBUM_PHOTOS . " (
                album_id INT UNSIGNED    NOT NULL,
                photo_id BIGINT UNSIGNED NOT NULL,
                added_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (album_id, photo_id),
                INDEX idx_photo (photo_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /** Jeton de partage aléatoire (32 caractères hexadécimaux). */
    public static function token(): string { return bin2hex(random_bytes(16)); }

    /** Un jeton a-t-il la forme attendue ? (garde-fou avant toute requête) */
    public static function validToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f]{' . self::TOKEN_LEN . '}$/', $token);
    }

    /** Crée un album pour un compte et renvoie son id. */
    public static function create(int $uid, string $name): int
    {
        $name = trim($name);
        if ($name === '') $name = 'Album sans titre';
        if (mb_strlen($name) > 120) $name = mb_substr($name, 0, 120);

        $db = Db::pdo();
        $db->prepare('INSERT INTO ' . TBL_ALBUMS . ' (user_id, name, token) VALUES (?,?,?)')
           ->execute([$uid, $name, self::token()]);
        return (int) $db->lastInsertId();
    }

    /** Albums d'un compte, du plus récent au plus ancien, avec le nombre de photos. */
    public static function forUser(int $uid): array
    {
        $st = Db::pdo()->prepare(
            'SELECT a.*,
                    (SELECT COUNT(*) FROM ' . TBL_ALBUM_PHOTOS . ' ap
                      JOIN ' . TBL_PHOTOS . ' p ON p.id = ap.photo_id
                     WHERE ap.album_id = a.id AND p.deleted_at IS NULL) AS n,
                    (SELECT ap.photo_id FROM ' . TBL_ALBUM_PHOTOS . ' ap
                      JOIN ' . TBL_PHOTOS . ' p ON p.id = ap.photo_id
                     WHERE ap.album_id = a.id AND p.deleted_at IS NULL
                     ORDER BY COALESCE(p.taken_at, p.uploaded_at) DESC, p.id DESC LIMIT 1) AS cover
             FROM ' . TBL_ALBUMS . ' a
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC, a.id DESC'
        );
        $st->execute([$uid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vérifie qu'un album appartient bien au compte ; renvoie la ligne ou null. */
    public static function owned(int $albumId, int $uid): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM ' . TBL_ALBUMS . ' WHERE id = ? AND user_id = ?');
        $st->execute([$albumId, $uid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Album désigné par son jeton de partage (accès public) ; null si inconnu. */
    public static function byToken(string $token): ?array
    {
        if (!self::validToken($token)) return null;
        $st = Db::pdo()->prepare('SELECT * FROM ' . TBL_ALBUMS . ' WHERE token = ?');
        $st->execute([$token]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Le lien de partage est-il arrivé à échéance ? */
    public static function isExpired(array $album): bool
    {
        if (empty($album['expires_at'])) return false;
        $ts = strtotime((string) $album['expires_at']);
        return $ts !== false && $ts < time();
    }

    /** Ajoute des photos (du compte) à un album (du compte). Ignore le reste. */
    public static function addPhotos(int $albumId, int $uid, array $photoIds): int
    {
        if (!$photoIds || !self::owned($albumId, $uid)) return 0;

        $db  = Db::pdo();
        $ins = $db->prepare('INSERT IGNORE INTO ' . TBL_ALBUM_PHOTOS . ' (album_id, photo_id) VALUES (?,?)');
        // On ne rattache que des photos actives appartenant réellement au compte.
        $chk = $db->prepare('SELECT 1 FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ? AND deleted_at IS NULL');

        $added = 0;
        foreach ($photoIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            $chk->execute([$pid, $uid]);
            if (!$chk->fetch()) continue;
            $ins->execute([$albumId, $pid]);
            $added += $ins->rowCount();
        }
        return $added;
    }

    /** Retire des photos d'un album (les photos elles-mêmes ne sont pas touchées). */
    public static function removePhotos(int $albumId, int $uid, array $photoIds): void
    {
        if (!$photoIds || !self::owned($albumId, $uid)) return;
        $del = Db::pdo()->prepare('DELETE FROM ' . TBL_ALBUM_PHOTOS . ' WHERE album_id = ? AND photo_id = ?');
        foreach ($photoIds as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) $del->execute([$albumId, $pid]);
        }
    }

    /** Supprime l'album et ses liaisons — les photos restent dans la galerie. */
    public static function delete(int $albumId, int $uid): void
    {
        if (!self::owned($albumId, $uid)) return;
        Db::pdo()->prepare('DELETE FROM ' . TBL_ALBUM_PHOTOS . ' WHERE album_id = ?')->execute([$albumId]);
        Db::pdo()->prepare('DELETE FROM ' . TBL_ALBUMS . ' WHERE id = ? AND user_id = ?')->execute([$albumId, $uid]);
    }

    /** Retire une photo de TOUS les albums (appelé à la suppression définitive). */
    public static function forgetPhoto(int $photoId): void
    {
        try {
            Db::pdo()->prepare('DELETE FROM ' . TBL_ALBUM_PHOTOS . ' WHERE photo_id = ?')->execute([$photoId]);
        } catch (Throwable $e) {
            // Table pas encore créée (installation qui n'a jamais ouvert les albums) : sans conséquence.
        }
    }

    /** Renomme un album. */
    public static function rename(int $albumId, int $uid, string $name): void
    {
        $name = trim($name);
        if ($name === '' || !self::owned($albumId, $uid)) return;
        if (mb_strlen($name) > 120) $name = mb_substr($name, 0, 120);
        Db::pdo()->prepare('UPDATE ' . TBL_ALBUMS . ' SET name = ? WHERE id = ? AND user_id = ?')
                 ->execute([$name, $albumId, $uid]);
    }

    /** Définit (ou retire si chaîne vide) le mot de passe du lien de partage. */
    public static function setPassword(int $albumId, int $uid, string $pass): void
    {
        if (!self::owned($albumId, $uid)) return;
        $hash = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : null;
        Db::pdo()->prepare('UPDATE ' . TBL_ALBUMS . ' SET pass_hash = ? WHERE id = ? AND user_id = ?')
                 ->execute([$hash, $albumId, $uid]);
    }

    /** Définit (ou retire si chaîne vide) la date d'expiration. Format attendu : AAAA-MM-JJ. */
    public static function setExpiry(int $albumId, int $uid, string $date): void
    {
        if (!self::owned($albumId, $uid)) return;
        $val = null;
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $val = $date . ' 23:59:59'; // valable jusqu'à la fin du jour choisi
        }
        Db::pdo()->prepare('UPDATE ' . TBL_ALBUMS . ' SET expires_at = ? WHERE id = ? AND user_id = ?')
                 ->execute([$val, $albumId, $uid]);
    }

    /** Régénère le jeton : l'ancien lien cesse aussitôt de fonctionner. */
    public static function resetToken(int $albumId, int $uid): void
    {
        if (!self::owned($albumId, $uid)) return;
        Db::pdo()->prepare('UPDATE ' . TBL_ALBUMS . ' SET token = ? WHERE id = ? AND user_id = ?')
                 ->execute([self::token(), $albumId, $uid]);
    }

    /**
     * Cette photo est-elle visible via ce jeton de partage ?
     * Sert à autoriser api/media.php pour un visiteur sans compte.
     */
    public static function photoInToken(int $photoId, string $token): bool
    {
        if (!self::validToken($token) || $photoId <= 0) return false;
        $st = Db::pdo()->prepare(
            'SELECT 1 FROM ' . TBL_ALBUM_PHOTOS . ' ap
              JOIN ' . TBL_ALBUMS . ' a ON a.id = ap.album_id
              JOIN ' . TBL_PHOTOS . ' p ON p.id = ap.photo_id
             WHERE a.token = ? AND ap.photo_id = ? AND p.deleted_at IS NULL
               AND (a.expires_at IS NULL OR a.expires_at >= NOW())
             LIMIT 1'
        );
        $st->execute([$token, $photoId]);
        return (bool) $st->fetch();
    }

    /** Photos actives d'un album, de la plus récente à la plus ancienne. */
    public static function photos(int $albumId): array
    {
        $st = Db::pdo()->prepare(
            'SELECT p.id, p.user_id, p.original_name, p.taken_at, p.uploaded_at,
                    p.stored_path, p.deleted_at, p.size_bytes
             FROM ' . TBL_ALBUM_PHOTOS . ' ap
             JOIN ' . TBL_PHOTOS . ' p ON p.id = ap.photo_id
             WHERE ap.album_id = ? AND p.deleted_at IS NULL
             ORDER BY COALESCE(p.taken_at, p.uploaded_at) DESC, p.id DESC'
        );
        $st->execute([$albumId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Identifiants des albums (du compte) contenant déjà cette photo. */
    public static function albumIdsOfPhoto(int $photoId, int $uid): array
    {
        $st = Db::pdo()->prepare(
            'SELECT ap.album_id FROM ' . TBL_ALBUM_PHOTOS . ' ap
              JOIN ' . TBL_ALBUMS . ' a ON a.id = ap.album_id
             WHERE ap.photo_id = ? AND a.user_id = ?'
        );
        $st->execute([$photoId, $uid]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** URL publique complète du lien de partage (à copier/coller pour l'envoyer). */
    public static function shareUrl(string $token): string
    {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'luvumbu.com';
        // …/web/albums.php  ->  …/web/share.php?a=<token>
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/web/albums.php')), '/');
        return $scheme . '://' . $host . $dir . '/share.php?a=' . $token;
    }
}
