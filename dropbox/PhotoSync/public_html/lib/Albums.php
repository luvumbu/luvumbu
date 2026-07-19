<?php
// === Albums (dossiers virtuels) partageables, éventuellement protégés par mot de passe ===
// Une photo peut appartenir à plusieurs albums (table de liaison). Le partage se fait
// via un jeton aléatoire dans l'URL (web/share.php?a=<token>).

final class Albums
{
    /** Jeton de partage aléatoire (32 hex). */
    public static function token(): string { return bin2hex(random_bytes(16)); }

    /** Crée un album pour un compte et renvoie son id. */
    public static function create(int $uid, string $name): int
    {
        $name = trim($name);
        if ($name === '') $name = 'Album';
        if (mb_strlen($name) > 120) $name = mb_substr($name, 0, 120);
        $db = Db::pdo();
        $db->prepare('INSERT INTO ' . TBL_ALBUMS . ' (user_id, name, token) VALUES (?,?,?)')
           ->execute([$uid, $name, self::token()]);
        return (int) $db->lastInsertId();
    }

    /** Albums d'un compte, avec le nombre de photos. */
    public static function forUser(int $uid): array
    {
        $st = Db::pdo()->prepare(
            'SELECT a.id, a.name, a.token, a.pass_hash, a.created_at,
                    (SELECT COUNT(*) FROM ' . TBL_ALBUM_PHOTOS . ' ap WHERE ap.album_id = a.id) AS n
             FROM ' . TBL_ALBUMS . ' a WHERE a.user_id = ? ORDER BY a.created_at DESC, a.id DESC'
        );
        $st->execute([$uid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vérifie qu'un album appartient au compte ; renvoie la ligne ou null. */
    public static function owned(int $albumId, int $uid): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM ' . TBL_ALBUMS . ' WHERE id = ? AND user_id = ?');
        $st->execute([$albumId, $uid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Album par jeton de partage (public) ; renvoie la ligne ou null. */
    public static function byToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) return null;
        $st = Db::pdo()->prepare('SELECT * FROM ' . TBL_ALBUMS . ' WHERE token = ?');
        $st->execute([$token]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Ajoute des photos (du compte) à un album (du compte). */
    public static function addPhotos(int $albumId, int $uid, array $photoIds): void
    {
        if (!self::owned($albumId, $uid) || !$photoIds) return;
        $db = Db::pdo();
        $ins = $db->prepare('INSERT IGNORE INTO ' . TBL_ALBUM_PHOTOS . ' (album_id, photo_id) VALUES (?,?)');
        $chk = $db->prepare('SELECT 1 FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ?');
        foreach ($photoIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            $chk->execute([$pid, $uid]);
            if ($chk->fetch()) $ins->execute([$albumId, $pid]);
        }
    }

    /** Retire des photos d'un album. */
    public static function removePhotos(int $albumId, int $uid, array $photoIds): void
    {
        if (!self::owned($albumId, $uid) || !$photoIds) return;
        $del = Db::pdo()->prepare('DELETE FROM ' . TBL_ALBUM_PHOTOS . ' WHERE album_id = ? AND photo_id = ?');
        foreach ($photoIds as $pid) { $pid = (int) $pid; if ($pid > 0) $del->execute([$albumId, $pid]); }
    }

    /** Supprime l'album (et ses liaisons), pas les photos. */
    public static function delete(int $albumId, int $uid): void
    {
        if (!self::owned($albumId, $uid)) return;
        Db::pdo()->prepare('DELETE FROM ' . TBL_ALBUM_PHOTOS . ' WHERE album_id = ?')->execute([$albumId]);
        Db::pdo()->prepare('DELETE FROM ' . TBL_ALBUMS . ' WHERE id = ? AND user_id = ?')->execute([$albumId, $uid]);
    }

    /** Définit (ou retire si null/'') le mot de passe d'un album. */
    public static function setPassword(int $albumId, int $uid, ?string $pass): void
    {
        if (!self::owned($albumId, $uid)) return;
        $hash = ($pass !== null && $pass !== '') ? password_hash($pass, PASSWORD_DEFAULT) : null;
        Db::pdo()->prepare('UPDATE ' . TBL_ALBUMS . ' SET pass_hash = ? WHERE id = ? AND user_id = ?')
                 ->execute([$hash, $albumId, $uid]);
    }

    /** La photo appartient-elle à l'album identifié par ce jeton ? (pour servir l'image en public) */
    public static function photoInToken(int $photoId, string $token): bool
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) return false;
        $st = Db::pdo()->prepare(
            'SELECT 1 FROM ' . TBL_ALBUM_PHOTOS . ' ap
             JOIN ' . TBL_ALBUMS . ' a ON a.id = ap.album_id
             WHERE a.token = ? AND ap.photo_id = ? LIMIT 1'
        );
        $st->execute([$token, $photoId]);
        return (bool) $st->fetch();
    }

    /** Photos actives d'un album (id, original_name, dates, stored_path, deleted_at). */
    public static function photos(int $albumId): array
    {
        $st = Db::pdo()->prepare(
            'SELECT p.id, p.original_name, p.taken_at, p.uploaded_at, p.stored_path, p.deleted_at
             FROM ' . TBL_ALBUM_PHOTOS . ' ap
             JOIN ' . TBL_PHOTOS . ' p ON p.id = ap.photo_id
             WHERE ap.album_id = ? AND p.deleted_at IS NULL
             ORDER BY COALESCE(p.taken_at, p.uploaded_at) DESC, p.id DESC'
        );
        $st->execute([$albumId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
