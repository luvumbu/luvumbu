<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;

/**
 * Sert les pièces jointes stockées hors racine web, avec contrôle d'accès :
 * accessibles publiquement si le signalement parent est publié, sinon propriétaire/modération.
 */
final class MediaController
{
    /** GET /api/v1/media/{uuid} */
    public function show(Request $request): void
    {
        $this->stream($request, false);
    }

    /** GET /api/v1/media/{uuid}/thumb */
    public function thumb(Request $request): void
    {
        $this->stream($request, true);
    }

    private function stream(Request $request, bool $thumb): void
    {
        $uuid = (string) $request->param('uuid');

        $m = Database::selectOne(
            'SELECT rm.*, r.status AS report_status, r.user_id AS report_user
             FROM report_media rm JOIN reports r ON r.id = rm.report_id
             WHERE rm.uuid = ? LIMIT 1',
            [$uuid]
        );
        if (!$m) {
            throw HttpException::notFound('Fichier introuvable.');
        }

        if ($m['report_status'] !== 'published') {
            $user = $request->user();
            $isOwner = $user && (int) $user['id'] === (int) $m['report_user'];
            $isStaff = $user && in_array($user['role'], ['moderator', 'admin'], true);
            if (!$isOwner && !$isStaff) {
                throw HttpException::notFound('Fichier introuvable.');
            }
        }

        $base = $thumb && $m['thumb_path'] ? config('upload.thumb_path') : config('upload.media_path');
        $file = rtrim((string) $base, '/\\') . '/' . ($thumb && $m['thumb_path'] ? $m['thumb_path'] : $m['stored_path']);

        if (!is_file($file)) {
            throw HttpException::notFound('Fichier introuvable.');
        }

        $mime = $thumb && $m['thumb_path'] ? 'image/jpeg' : ($m['mime'] ?: 'application/octet-stream');

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: private, max-age=86400');
            header('X-Content-Type-Options: nosniff');
            // Les documents et vidéos ne sont pas exécutés inline sans précaution
            if ($m['type'] === 'document') {
                header('Content-Disposition: inline; filename="' . rawurlencode($m['original_name'] ?: 'document') . '"');
            }
        }
        readfile($file);
        exit;
    }
}
