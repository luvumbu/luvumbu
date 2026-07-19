<?php
// === Photos : corbeille, vignettes et formatage de dates ===
// Toutes les opérations sont scopées au compte (paramètre $uid).

final class Photos
{
    /** Durée de conservation dans la corbeille avant suppression définitive. */
    const TRASH_DAYS = 30;

    /** Choix possibles du nombre de photos par page (galerie web). */
    const PER_PAGE = [5, 10, 20, 50, 100];

    /** Le fichier physique de cette ligne photo existe-t-il sur le disque ? */
    public static function fileExists(array $row): bool
    {
        if (empty($row['stored_path'])) return false;
        $base = empty($row['deleted_at']) ? UPLOAD_DIR : self::trashDir();
        return is_file($base . '/' . $row['stored_path']);
    }

    // ---- Dossiers de stockage ----
    public static function trashDir(): string { return UPLOAD_DIR . '/.corbeille'; }
    public static function thumbDir(): string { return UPLOAD_DIR . '/.thumbs'; }

    /** Fichier de vignette en cache. $variant='' = 500px ; 'micro' = ~240px. */
    public static function thumbFile(int $id, string $variant = ''): string {
        $suffix = $variant !== '' ? '_' . $variant : '';
        return self::thumbDir() . '/' . $id . $suffix . '.jpg';
    }

    /** Supprime toutes les variantes de vignette en cache d'une photo. */
    public static function clearThumbs(int $id): void {
        self::clearThumbs($id);
        @unlink(self::thumbFile($id, 'micro'));
    }

    /** Met une photo à la corbeille (déplace le fichier + marque deleted_at). */
    public static function trash(int $id, int $uid): void
    {
        $st = Db::pdo()->prepare('SELECT stored_path, deleted_at FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['deleted_at']) return;

        $src = UPLOAD_DIR . '/' . $row['stored_path'];
        $dst = self::trashDir() . '/' . $row['stored_path'];
        @mkdir(dirname($dst), 0775, true);
        if (is_file($src)) @rename($src, $dst);
        self::clearThumbs($id);
        Db::pdo()->prepare('UPDATE ' . TBL_PHOTOS . ' SET deleted_at = NOW() WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    }

    /** Masque ou ré-affiche une photo (scopé au compte). */
    public static function setHidden(int $id, int $uid, bool $hidden): void
    {
        Db::pdo()->prepare('UPDATE ' . TBL_PHOTOS . ' SET hidden = ? WHERE id = ? AND user_id = ?')
                 ->execute([$hidden ? 1 : 0, $id, $uid]);
    }

    /** Restaure une photo depuis la corbeille. */
    public static function restore(int $id, int $uid): void
    {
        $st = Db::pdo()->prepare('SELECT stored_path, deleted_at FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['deleted_at']) return;

        $src = self::trashDir() . '/' . $row['stored_path'];
        $dst = UPLOAD_DIR . '/' . $row['stored_path'];
        @mkdir(dirname($dst), 0775, true);
        if (is_file($src)) @rename($src, $dst);
        self::clearThumbs($id);
        Db::pdo()->prepare('UPDATE ' . TBL_PHOTOS . ' SET deleted_at = NULL WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    }

    /** Suppression définitive (fichier + corbeille + vignette + ligne BD). */
    public static function deleteForever(int $id, int $uid): void
    {
        $st = Db::pdo()->prepare('SELECT stored_path FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;

        foreach ([self::trashDir() . '/' . $row['stored_path'], UPLOAD_DIR . '/' . $row['stored_path']] as $f) {
            if (is_file($f)) @unlink($f);
        }
        self::clearThumbs($id);
        Db::pdo()->prepare('DELETE FROM ' . TBL_PHOTOS . ' WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    }

    /** Vide automatiquement les éléments de corbeille plus vieux que TRASH_DAYS. */
    public static function purgeOldTrash(int $uid): void
    {
        $st = Db::pdo()->prepare(
            "SELECT id FROM " . TBL_PHOTOS . " WHERE user_id = ? AND deleted_at IS NOT NULL
             AND deleted_at < (NOW() - INTERVAL " . self::TRASH_DAYS . " DAY)"
        );
        $st->execute([$uid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            self::deleteForever((int) $r['id'], $uid);
        }
    }

    /** Génère une vignette JPEG (max $max px sur le plus grand côté, qualité $quality). */
    public static function makeThumb(string $src, string $dst, int $max, int $quality = 82): void
    {
        $info = @getimagesize($src);
        if (!$info) return;
        $w = $info[0]; $h = $info[1];
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
            case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
            case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src); break;
            case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null; break;
            default: $img = null;
        }
        if (!$img) return;
        $scale = min(1.0, $max / max($w, $h));
        $nw = max(1, (int) ($w * $scale));
        $nh = max(1, (int) ($h * $scale));
        $thumb = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($thumb, $dst, max(40, min(100, $quality)));
        imagedestroy($img);
        imagedestroy($thumb);
    }

    /** Date SQL → texte français court : « 7 juin 2026 · 14:05 ». */
    public static function frDate(?string $sql): string
    {
        if (!$sql) return 'Date inconnue';
        $ts = strtotime($sql);
        if ($ts === false) return 'Date inconnue';
        $mois = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
                 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        return date('j', $ts) . ' ' . $mois[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ' · ' . date('H:i', $ts);
    }
}
