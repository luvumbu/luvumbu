<?php

namespace App\Controllers;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Droit de réponse / contestation (LCEN) : un établissement, une marque ou son
 * représentant peut contester un signalement ou demander un droit de réponse.
 * Accessible sans compte (les organisations concernées n'en ont pas forcément).
 * L'adresse email du requérant est chiffrée au repos.
 */
final class ContestationController
{
    /** POST /api/v1/contestations */
    public function store(Request $request): Response
    {
        $data = Validator::make($request->all(), [
            'claimant_name'  => 'required|string|min:2|max:150',
            'claimant_email' => 'required|email|max:255',
            'claimant_role'  => 'nullable|string|max:120',
            'message'        => 'required|string|min:20|max:5000',
            'report_uuid'    => 'nullable|string|max:36',
        ])->validate();

        // Rattachement optionnel à un signalement / une organisation
        $reportId = null;
        $organizationId = null;
        if (!empty($data['report_uuid'])) {
            $report = Database::selectOne(
                'SELECT id, organization_id FROM reports WHERE uuid = ? LIMIT 1',
                [$data['report_uuid']]
            );
            if (!$report) {
                throw HttpException::validation(['report_uuid' => ['Signalement introuvable.']]);
            }
            $reportId = (int) $report['id'];
            $organizationId = (int) $report['organization_id'];
        }

        $uuid = str_uuid();
        Database::insert(
            'INSERT INTO contestations
                (uuid, organization_id, report_id, claimant_name, claimant_email, claimant_role, message, status)
             VALUES (?,?,?,?,?,?,?, ?)',
            [
                $uuid, $organizationId, $reportId,
                trim($data['claimant_name']),
                Crypto::encrypt(mb_strtolower(trim($data['claimant_email']))), // chiffré au repos
                self::nz($data['claimant_role'] ?? null),
                trim($data['message']),
                'pending',
            ]
        );

        // Journalise l'action (audit) sans acteur authentifié
        Database::execute(
            'INSERT INTO moderation_actions (actor_id, action, target_type, target_id, reason, ip_hash)
             VALUES (NULL, ?, ?, ?, ?, ?)',
            ['contestation.received', 'contestation', 0, 'Droit de réponse reçu', Crypto::pseudonymize($request->ip())]
        );

        return Response::success([
            'reference' => strtoupper(substr($uuid, 0, 8)),
            'message'   => 'Votre demande de droit de réponse a été enregistrée. Notre équipe de modération l\'examinera.',
        ], 201);
    }

    private static function nz(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);
        return ($v === null || $v === '') ? null : $v;
    }
}
