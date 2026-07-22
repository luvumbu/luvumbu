<?php

namespace App\Controllers;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Signalement de contenu illicite (procédure LCEN de notification) :
 * tout utilisateur peut signaler un signalement ou un commentaire jugé
 * diffamatoire, faux, haineux, etc. Alimente la file de modération.
 */
final class AbuseController
{
    /** POST /api/v1/reports/{uuid}/abuse */
    public function reportContent(Request $request): Response
    {
        $uuid = (string) $request->param('uuid');
        $report = Database::selectOne('SELECT id FROM reports WHERE uuid = ? LIMIT 1', [$uuid]);
        if (!$report) {
            throw HttpException::notFound('Signalement introuvable.');
        }

        $data = Validator::make($request->all(), [
            'reason'  => 'required|in:defamation,false_info,hate,spam,privacy,other',
            'details' => 'nullable|string|max:2000',
        ])->validate();

        Database::insert(
            'INSERT INTO abuse_reports
                (reportable_type, reportable_id, reporter_user_id, reason, details, status, ip_hash)
             VALUES (?,?,?,?,?, ?, ?)',
            [
                'report', (int) $report['id'], $request->userId(),
                $data['reason'], $data['details'] ?? null, 'open',
                Crypto::pseudonymize($request->ip()),
            ]
        );

        return Response::success([
            'message' => 'Merci. Votre signalement a été transmis à l\'équipe de modération.',
        ], 201);
    }
}
