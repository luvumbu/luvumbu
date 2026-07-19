<?php
// === Photos : corbeille, vignettes et formatage de dates ===
// Toutes les opérations sont scopées au compte (paramètre $uid).

final class Photos
{
    /** Durée de conservation dans la corbeille avant suppression définitive. */
    const TRASH_DAYS = 30;

    /** Choix possibles du nombre de photos par page (galerie web). */
    const PER_PAGE = [5, 10, 20, 50, 100];

    /** Chemin physique du fichier d'une ligne photo (active => uploads ; corbeille sinon). */
    public static function physicalPath(array $row): string
    {
        $base = empty($row['deleted_at']) ? UPLOAD_DIR : self::trashDir();
        return $base . '/' . $row['stored_path'];
    }

    /** Le fichier physique de cette ligne photo existe-t-il sur le disque ? */
    public static function fileExists(array $row): bool
    {
        if (empty($row['stored_path'])) return false;
        return is_file(self::physicalPath($row));
    }

    /** Propriétaire (user_id) d'une photo, ou 0 si elle est introuvable. */
    public static function ownerId(int $id): int
    {
        $st = Db::pdo()->prepare('SELECT user_id FROM ' . TBL_PHOTOS . ' WHERE id = ?');
        $st->execute([$id]);
        return (int) ($st->fetch(PDO::FETCH_ASSOC)['user_id'] ?? 0);
    }

    /**
     * Ne conserve que les lignes dont le fichier existe encore. Les entrées dont le
     * fichier a disparu sont supprimées définitivement (le fichier est déjà absent).
     * $uid : compte de rattachement pour la suppression ; à défaut, le user_id de la ligne.
     */
    public static function filterExisting(array $rows, ?int $uid = null): array
    {
        return array_values(array_filter($rows, function ($r) use ($uid) {
            if (self::fileExists($r)) return true;
            self::deleteForever((int) $r['id'], $uid ?? (int) ($r['user_id'] ?? 0));
            return false;
        }));
    }

    /**
     * Bornes de pagination : borne la page demandée et calcule l'offset.
     * Renvoie ['pages' => int, 'page' => int, 'offset' => int].
     */
    public static function paginate(int $total, int $page, int $perPage): array
    {
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, $page), $pages);
        return ['pages' => $pages, 'page' => $page, 'offset' => ($page - 1) * $perPage];
    }

    // ---- Dossiers de stockage ----
    public static function trashDir(): string { return UPLOAD_DIR . '/.corbeille'; }
    public static function thumbDir(): string { return UPLOAD_DIR . '/.thumbs'; }
    public static function thumbFile(int $id): string { return self::thumbDir() . '/' . $id . '.jpg'; }

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
        @unlink(self::thumbFile($id));
        Db::pdo()->prepare('UPDATE ' . TBL_PHOTOS . ' SET deleted_at = NOW() WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
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
        @unlink(self::thumbFile($id));
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
        @unlink(self::thumbFile($id));
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

    /** Génère une vignette JPEG (max $max px sur le plus grand côté). */
    public static function makeThumb(string $src, string $dst, int $max): void
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
        imagejpeg($thumb, $dst, 82);
        imagedestroy($img);
        imagedestroy($thumb);
    }

    // ---- Classification par type de fichier (photo / vidéo / audio / document / autre) ----

    /** Extensions connues par catégorie (sans le point, en minuscules). */
    public static function categoryExtensions(): array
    {
        return [
            'photo'    => ['jpg','jpeg','png','gif','webp','heic','heif','bmp','tif','tiff','dng'],
            'video'    => ['mp4','mov','mkv','avi','3gp','3gpp','webm','m4v','wmv','flv','ts','mts','m2ts','mpg','mpeg','ogv'],
            'audio'    => ['mp3','m4a','aac','wav','flac','ogg','oga','opus','wma','amr','mid','midi'],
            'document' => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','rtf','odt','ods','odp','csv','md','epub','zip','rar','7z','gz'],
        ];
    }

    /**
     * Catégorie d'un fichier : 'photo' | 'video' | 'audio' | 'document' | 'other'.
     * Priorité : dossier dédié videos/ > extension > MIME réel (fichiers sans extension).
     */
    public static function categoryOf(string $name, string $storedPath = '', ?string $mime = null): string
    {
        if ($storedPath !== '' && strpos($storedPath, '/videos/') !== false) return 'video';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        foreach (self::categoryExtensions() as $cat => $exts) {
            if (in_array($ext, $exts, true)) return $cat;
        }
        if ($mime) {
            if (strpos($mime, 'image/') === 0) return 'photo';
            if (strpos($mime, 'video/') === 0) return 'video';
            if (strpos($mime, 'audio/') === 0) return 'audio';
        }
        return 'other';
    }

    /** Liste d'extensions → fragment SQL « 'a','b','c' » (valeurs constantes : sûres à inliner). */
    private static function quoteList(array $exts): string
    {
        $out = [];
        foreach ($exts as $e) $out[] = "'" . $e . "'";
        return implode(',', $out);
    }

    /**
     * Condition SQL (sans paramètre lié) pour filtrer une catégorie donnée.
     * Permet de filtrer ET paginer correctement côté base.
     * $cat : 'photo'|'video'|'audio'|'document'|'other' (sinon '1=1').
     */
    public static function categoryCondition(string $cat): string
    {
        $exts = self::categoryExtensions();
        $extExpr = "LOWER(SUBSTRING_INDEX(original_name, '.', -1))";
        switch ($cat) {
            case 'video':
                return "(stored_path LIKE '%/videos/%' OR $extExpr IN (" . self::quoteList($exts['video']) . "))";
            case 'photo':
                return "(stored_path NOT LIKE '%/videos/%' AND $extExpr IN (" . self::quoteList($exts['photo']) . "))";
            case 'audio':
                return "$extExpr IN (" . self::quoteList($exts['audio']) . ")";
            case 'document':
                return "$extExpr IN (" . self::quoteList($exts['document']) . ")";
            case 'other':
                $all = array_merge($exts['photo'], $exts['video'], $exts['audio'], $exts['document']);
                return "(stored_path NOT LIKE '%/videos/%' AND $extExpr NOT IN (" . self::quoteList($all) . "))";
            default:
                return '1=1';
        }
    }

    /** Traduit un code de tri en clause ORDER BY sûre (liste blanche). */
    public static function sortClause(string $sort): string
    {
        switch ($sort) {
            case 'date_asc':  return 'COALESCE(taken_at, uploaded_at) ASC';
            case 'name_asc':  return 'original_name ASC';
            case 'name_desc': return 'original_name DESC';
            case 'size_desc': return 'size_bytes DESC';
            case 'size_asc':  return 'size_bytes ASC';
            // Regroupe par extension (approxime « par type »), puis par date récente.
            case 'type':      return "LOWER(SUBSTRING_INDEX(original_name, '.', -1)) ASC, COALESCE(taken_at, uploaded_at) DESC";
            case 'date_desc':
            default:          return 'COALESCE(taken_at, uploaded_at) DESC';
        }
    }

    /** Icône SVG légère pour une catégorie (vignette des fichiers non-image). */
    public static function iconSvg(string $cat): string
    {
        $bg = '#16213a'; $accent = '#E8772E'; $muted = '#8aa0bd';
        $glyph = '';
        $label = '';
        switch ($cat) {
            case 'video':
                $glyph = '<polygon points="40,32 72,50 40,68" fill="' . $accent . '"/>';
                $label = 'vidéo';
                break;
            case 'audio':
                $glyph = '<g fill="' . $accent . '"><circle cx="40" cy="64" r="8"/><circle cx="66" cy="58" r="8"/>'
                       . '<rect x="46" y="30" width="4" height="34"/><rect x="72" y="24" width="4" height="34"/>'
                       . '<polygon points="46,30 76,24 76,32 46,38"/></g>';
                $label = 'audio';
                break;
            case 'document':
                $glyph = '<g fill="' . $accent . '"><path d="M38 28 h18 l10 10 v34 h-28 z"/>'
                       . '<rect x="44" y="46" width="16" height="3" fill="' . $bg . '"/>'
                       . '<rect x="44" y="54" width="16" height="3" fill="' . $bg . '"/>'
                       . '<rect x="44" y="62" width="10" height="3" fill="' . $bg . '"/></g>';
                $label = 'doc';
                break;
            default:
                $glyph = '<path d="M38 28 h18 l10 10 v34 h-28 z" fill="' . $accent . '"/>';
                $label = 'fichier';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
             . '<rect width="100" height="100" rx="10" fill="' . $bg . '"/>'
             . $glyph
             . '<text x="50" y="92" font-size="12" fill="' . $muted . '" text-anchor="middle" font-family="sans-serif">' . $label . '</text>'
             . '</svg>';
    }

    /** Taille en octets → texte lisible : « 1,4 Mo », « 820 Ko »… */
    public static function humanSize(int $bytes): string
    {
        if ($bytes <= 0) return '';
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $i = 0;
        $val = (float) $bytes;
        while ($val >= 1024 && $i < count($units) - 1) { $val /= 1024; $i++; }
        $dec = ($i === 0 || $val >= 100) ? 0 : 1;
        return number_format($val, $dec, ',', ' ') . ' ' . $units[$i];
    }

    /** Libellé court d'une catégorie pour l'affichage. */
    public static function categoryLabel(string $cat): string
    {
        switch ($cat) {
            case 'photo':    return '🖼️ Photo';
            case 'video':    return '🎬 Vidéo';
            case 'audio':    return '🎵 Audio';
            case 'document': return '📄 Document';
            default:         return '🗂️ Fichier';
        }
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
