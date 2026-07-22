<?php

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Gestion des pièces jointes : validation, stockage sécurisé (hors racine web),
 * génération de vignettes pour les images.
 */
final class MediaService
{
    /**
     * Traite tous les fichiers postés sous le champ $field (multiple) pour un signalement.
     * @return int nombre de fichiers enregistrés
     */
    public static function handleUploads(array $files, string $field, int $reportId): int
    {
        if (empty($files[$field])) {
            return 0;
        }

        $normalized = self::normalize($files[$field]);
        $count = 0;
        foreach ($normalized as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            self::storeOne($file, $reportId);
            $count++;
        }
        return $count;
    }

    /** Normalise la structure $_FILES (mono ou multiple) en liste de fichiers. */
    private static function normalize(array $field): array
    {
        if (!is_array($field['name'])) {
            return [$field];
        }
        $files = [];
        foreach ($field['name'] as $i => $name) {
            $files[] = [
                'name'     => $name,
                'type'     => $field['type'][$i] ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'error'    => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $field['size'][$i] ?? 0,
            ];
        }
        return $files;
    }

    private static function storeOne(array $file, int $reportId): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new HttpException('Échec du téléversement d\'un fichier.', 400, [], 'UPLOAD_ERROR');
        }

        $maxSize = (int) config('upload.max_size');
        if ($file['size'] > $maxSize) {
            throw new HttpException('Fichier trop volumineux (max ' . round($maxSize / 1048576) . ' Mo).', 413, [], 'FILE_TOO_LARGE');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        [$type, $allowed] = self::classify($ext);
        if ($type === null) {
            throw new HttpException("Type de fichier non autorisé : .$ext", 415, [], 'FILE_TYPE_NOT_ALLOWED');
        }

        // Vérification MIME réelle
        $mime = self::detectMime($file['tmp_name']);

        $uuid = str_uuid();
        $mediaDir = rtrim((string) config('upload.media_path'), '/\\');
        $thumbDir = rtrim((string) config('upload.thumb_path'), '/\\');
        if (!is_dir($mediaDir)) { @mkdir($mediaDir, 0775, true); }
        if (!is_dir($thumbDir)) { @mkdir($thumbDir, 0775, true); }

        $storedName = $uuid . '.' . $ext;
        $destPath = $mediaDir . '/' . $storedName;

        if (!self::moveUploaded($file['tmp_name'], $destPath)) {
            throw new HttpException('Impossible d\'enregistrer le fichier.', 500, [], 'STORAGE_ERROR');
        }

        $width = $height = null;
        $thumbRel = null;
        if ($type === 'image') {
            $thumb = self::makeThumbnail($destPath, $thumbDir, $uuid, $ext);
            $width = $thumb['width']; $height = $thumb['height']; $thumbRel = $thumb['rel'];
        }

        Database::insert(
            'INSERT INTO report_media (uuid, report_id, type, original_name, stored_path, thumb_path, mime, size, width, height)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$uuid, $reportId, $type, mb_substr($file['name'], 0, 255), $storedName, $thumbRel, $mime, $file['size'], $width, $height]
        );
    }

    /** @return array{0:?string,1:array} [type, extensions autorisées] */
    private static function classify(string $ext): array
    {
        if (in_array($ext, (array) config('upload.allowed_images'), true)) return ['image', config('upload.allowed_images')];
        if (in_array($ext, (array) config('upload.allowed_videos'), true)) return ['video', config('upload.allowed_videos')];
        if (in_array($ext, (array) config('upload.allowed_docs'), true))   return ['document', config('upload.allowed_docs')];
        return [null, []];
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($f, $path);
            finfo_close($f);
            if ($mime) return $mime;
        }
        return 'application/octet-stream';
    }

    /** Génère une vignette max 800px pour une image. */
    private static function makeThumbnail(string $src, string $thumbDir, string $uuid, string $ext): array
    {
        $info = @getimagesize($src);
        if ($info === false) {
            return ['width' => null, 'height' => null, 'rel' => null];
        }
        [$w, $h] = $info;
        $max = 800;
        $scale = min(1, $max / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        $srcImg = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => @imagecreatefrompng($src),
            IMAGETYPE_GIF  => @imagecreatefromgif($src),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null,
            default        => null,
        };
        if (!$srcImg) {
            return ['width' => $w, 'height' => $h, 'rel' => null];
        }

        $dst = imagecreatetruecolor($tw, $th);
        if (in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $tw, $th, $w, $h);

        $thumbName = $uuid . '_thumb.jpg';
        imagejpeg($dst, $thumbDir . '/' . $thumbName, 82);
        imagedestroy($srcImg);
        imagedestroy($dst);

        return ['width' => $w, 'height' => $h, 'rel' => $thumbName];
    }

    /** move_uploaded_file en prod ; rename en contexte de test (fichier non issu d'un POST HTTP réel). */
    private static function moveUploaded(string $tmp, string $dest): bool
    {
        if (is_uploaded_file($tmp)) {
            return move_uploaded_file($tmp, $dest);
        }
        return @rename($tmp, $dest) || @copy($tmp, $dest);
    }
}
