<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ChildRepository;
use App\Services\AuthService;

/**
 * Gestion des profils enfants (rattachés au parent connecté).
 */
class ChildController
{
    private const MAX_CHILDREN = 8;   // limite raisonnable par parent

    private AuthService $auth;
    private ChildRepository $repo;

    public function __construct()
    {
        $cfg = require __DIR__ . '/../../config/config.php';
        $this->auth = new AuthService($cfg);
        $this->repo = new ChildRepository();
    }

    /** GET /children — liste des enfants du parent connecté. */
    public function index(): void
    {
        $uid = $this->requireUser();
        Response::json(['children' => $this->repo->allByUser($uid)]);
    }

    /** POST /children — { name, avatar } */
    public function create(): void
    {
        $uid = $this->requireUser();

        if ($this->repo->countByUser($uid) >= self::MAX_CHILDREN) {
            Response::error('Nombre maximum d\'enfants atteint.', 409);
        }
        $name   = trim((string) Request::input('name', ''));
        $avatar = (string) Request::input('avatar', '🐣');
        if ($name === '') {
            Response::error('Le prénom est obligatoire.', 422);
        }
        $child = $this->repo->create($uid, mb_substr($name, 0, 50), mb_substr($avatar, 0, 8));
        Response::json(['child' => $child], 201);
    }

    /** POST /children/delete — { child_id } */
    public function delete(): void
    {
        $uid = $this->requireUser();
        $id  = (int) Request::input('child_id', 0);
        $this->repo->delete($id, $uid);
        Response::json(['ok' => true]);
    }

    /** Renvoie l'ID du parent connecté, ou coupe la requête en 401. */
    private function requireUser(): int
    {
        $uid = $this->auth->currentUserId();
        if (!$uid) {
            Response::error('Non connecté.', 401);
        }
        return $uid;
    }
}
