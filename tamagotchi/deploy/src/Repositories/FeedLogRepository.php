<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Journal des repas — sert à mesurer l'équilibre alimentaire récent. */
class FeedLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    public function log(int $petId, string $category): void
    {
        $stmt = $this->db->prepare('INSERT INTO feed_log (pet_id, category) VALUES (?, ?)');
        $stmt->execute([$petId, $category]);
    }

    /** Les `n` dernières catégories mangées (la plus récente d'abord). */
    public function recentCategories(int $petId, int $n = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT category FROM feed_log WHERE pet_id = ? ORDER BY id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $petId, PDO::PARAM_INT);
        $stmt->bindValue(2, $n, PDO::PARAM_INT);
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'category');
    }
}
