<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ChildRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

/**
 * Connexion des parents (via Google) et infos du compte.
 */
class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $cfg = require __DIR__ . '/../../config/config.php';
        $this->auth = new AuthService($cfg);
    }

    /** POST /auth/google — { id_token }  →  { token, user, children } */
    public function google(): void
    {
        $idToken = (string) Request::input('id_token', '');
        if ($idToken === '') {
            Response::error('Jeton Google manquant.', 400);
        }
        $user = $this->auth->loginWithGoogle($idToken);
        if (!$user) {
            Response::error('Connexion Google refusée.', 401);
        }
        $children = (new ChildRepository())->allByUser((int) $user['id']);

        Response::json([
            'token'    => $this->auth->token((int) $user['id']),
            'user'     => [
                'id'     => (int) $user['id'],
                'email'  => $user['email'],
                'avatar' => $user['avatar_url'] ?? null,
            ],
            'children' => $children,
        ]);
    }

    /** GET /auth/me — vérifie le jeton et renvoie le compte + enfants. */
    public function me(): void
    {
        $uid = $this->auth->currentUserId();
        if (!$uid) {
            Response::error('Non connecté.', 401);
        }
        Response::json([
            'user'     => ['id' => $uid],
            'children' => (new ChildRepository())->allByUser($uid),
        ]);
    }

    /** POST /auth/delete — EFFACE le compte parent et TOUTES ses données. */
    public function deleteAccount(): void
    {
        $uid = $this->auth->currentUserId();
        if (!$uid) {
            Response::error('Non connecté.', 401);
        }
        (new UserRepository())->delete($uid);
        Response::json(['ok' => true]);
    }
}
