<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ChildRepository;
use App\Repositories\PetRepository;
use App\Services\AuthService;
use App\Services\PetService;

/**
 * Reçoit les requêtes HTTP liées aux créatures, orchestre Service + Repository,
 * renvoie du JSON. Aucune règle de jeu ici (elles sont dans PetService).
 *
 * Les créatures appartiennent à un ENFANT (profil), lui-même rattaché au
 * PARENT connecté (via Google). On vérifie donc les deux.
 */
class PetController
{
    private PetRepository $repo;
    private PetService $service;
    private AuthService $auth;
    private ChildRepository $children;

    public function __construct()
    {
        $this->repo     = new PetRepository();
        $this->service  = new PetService();
        $cfg            = require __DIR__ . '/../../config/config.php';
        $this->auth     = new AuthService($cfg);
        $this->children = new ChildRepository();
    }

    /** GET /pets?child_id=X — créatures de cet enfant (temps écoulé appliqué). */
    public function index(): void
    {
        $childId = $this->requireChild();
        $pets = $this->repo->allByChild($childId);
        $pets = array_map(fn ($p) => $this->refresh($p), $pets);
        Response::json($pets);
    }

    /** GET /pets/{id} */
    public function show(array $params): void
    {
        $pet = $this->load((int) $params['id']);
        Response::json($this->refresh($pet));
    }

    /** POST /pets — { name, species_id, child_id } */
    public function create(): void
    {
        $childId = $this->requireChild();
        $userId  = $this->auth->currentUserId();
        $name    = trim((string) Request::input('name', ''));
        $species = (int) Request::input('species_id', 1);

        if ($name === '') {
            Response::error('Le nom est obligatoire.', 422);
        }
        $pet = $this->repo->createForChild((int) $userId, $childId, $species, $name);
        Response::json($pet, 201);
    }

    /**
     * Vérifie que le parent est connecté ET que l'enfant demandé lui appartient.
     * Renvoie l'ID de l'enfant, ou coupe la requête (401/403).
     */
    private function requireChild(): int
    {
        $uid = $this->auth->currentUserId();
        if (!$uid) {
            Response::error('Non connecté.', 401);
        }
        $childId = (int) (Request::input('child_id') ?? $_GET['child_id'] ?? 0);
        if ($childId <= 0 || !$this->children->belongsTo($childId, $uid)) {
            Response::error('Profil enfant invalide.', 403);
        }
        return $childId;
    }

    /** POST /pets/{id}/feed */
    public function feed(array $params): void
    {
        $this->act((int) $params['id'], 'feed');
    }

    /** POST /pets/{id}/play */
    public function play(array $params): void
    {
        $this->act((int) $params['id'], 'play');
    }

    /** POST /pets/{id}/sleep */
    public function sleep(array $params): void
    {
        $this->act((int) $params['id'], 'sleep');
    }

    // ----- interne -----

    /** Charge une créature ou renvoie une 404. */
    private function load(int $id): array
    {
        $pet = $this->repo->find($id);
        if ($pet === null) {
            Response::error('Créature introuvable.', 404);
        }
        return $pet;
    }

    /** Applique le temps écoulé puis sauvegarde. */
    private function refresh(array $pet): array
    {
        $pet = $this->service->applyTimePassed($pet);
        return $this->repo->save($pet);
    }

    /** Applique le temps, puis l'action, puis sauvegarde et renvoie l'état. */
    private function act(int $id, string $action): void
    {
        $pet = $this->load($id);

        if (!$pet['is_alive']) {
            Response::error('Cette créature n\'est plus en vie. 🪦', 409);
        }

        $pet = $this->service->applyTimePassed($pet);
        $pet = $this->service->{$action}($pet);   // feed / play / sleep
        $pet = $this->repo->save($pet);

        Response::json($pet);
    }
}
