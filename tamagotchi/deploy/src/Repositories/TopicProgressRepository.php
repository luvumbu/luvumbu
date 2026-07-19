<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Réussites par thème (pour le déblocage progressif). */
class TopicProgressRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** +1 bonne réponse sur ce thème. */
    public function increment(int $petId, string $topic): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO topic_progress (pet_id, topic, correct) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE correct = correct + 1'
        );
        $stmt->execute([$petId, $topic]);
    }

    /** Retourne [topic => nombre de bonnes réponses] pour une créature. */
    public function forPet(int $petId): array
    {
        $stmt = $this->db->prepare('SELECT topic, correct FROM topic_progress WHERE pet_id = ?');
        $stmt->execute([$petId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['topic']] = (int) $row['correct'];
        }
        return $out;
    }
}
