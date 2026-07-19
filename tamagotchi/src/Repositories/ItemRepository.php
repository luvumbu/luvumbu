<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Accès BDD pour les objets de la boutique (aliments, etc.). */
class ItemRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Tous les aliments en vente. */
    public function foods(): array
    {
        return $this->db->query(
            "SELECT * FROM items WHERE type = 'food' ORDER BY price"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
