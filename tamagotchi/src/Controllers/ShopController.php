<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FeedLogRepository;
use App\Repositories\ItemRepository;
use App\Repositories\PetRepository;
use App\Services\ShopService;

/**
 * Boutique : lister les aliments, en acheter (= nourrir la créature).
 */
class ShopController
{
    private ItemRepository $items;
    private PetRepository $pets;
    private FeedLogRepository $feedLog;
    private ShopService $shop;

    public function __construct()
    {
        $this->items   = new ItemRepository();
        $this->pets    = new PetRepository();
        $this->feedLog = new FeedLogRepository();
        $this->shop    = new ShopService();
    }

    /** GET /shop — liste des aliments. */
    public function index(): void
    {
        Response::json($this->items->foods());
    }

    /** POST /shop/buy  { pet_id, item_id } */
    public function buy(): void
    {
        $petId  = (int) Request::input('pet_id', 0);
        $itemId = (int) Request::input('item_id', 0);

        $pet  = $this->pets->find($petId);
        $item = $this->items->find($itemId);

        if ($pet === null)  Response::error('Créature introuvable.', 404);
        if ($item === null) Response::error('Article introuvable.', 404);

        $recent = $this->feedLog->recentCategories($petId, 5);
        $result = $this->shop->buy($pet, $item, $recent);

        if (!$result['ok']) {
            Response::error($result['message'], 402); // 402 Payment Required
        }

        // Sauvegarde + journalise le repas
        $saved = $this->pets->save($result['pet']);
        $this->feedLog->log($petId, $item['category']);

        Response::json(['message' => $result['message'], 'pet' => $saved]);
    }
}
