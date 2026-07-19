<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Accès BDD pour les créatures. Seule couche qui écrit du SQL pour la table `pets`.
 */
class PetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Toutes les créatures d'un joueur. */
    public function allByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pets WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Une créature par son id. */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pets WHERE id = ?');
        $stmt->execute([$id]);
        $pet = $stmt->fetch();
        return $pet ?: null;
    }

    /** Toutes les créatures d'un ENFANT (profil). */
    public function allByChild(int $childId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pets WHERE child_id = ? ORDER BY id');
        $stmt->execute([$childId]);
        return $stmt->fetchAll();
    }

    /** Crée une créature et retourne la ligne complète. */
    public function create(int $userId, int $speciesId, string $name): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pets (user_id, species_id, name, stage) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $speciesId, $name, 'baby']);
        return $this->find((int) $this->db->lastInsertId());
    }

    /** Crée une créature rattachée à un ENFANT (profil). */
    public function createForChild(int $userId, int $childId, int $speciesId, string $name): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pets (user_id, child_id, species_id, name, stage) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $childId, $speciesId, $name, 'baby']);
        return $this->find((int) $this->db->lastInsertId());
    }

    /** Met à jour les champs dynamiques d'une créature. */
    public function save(array $pet): array
    {
        $stmt = $this->db->prepare(
            'UPDATE pets SET
                hunger = :hunger, happiness = :happiness, energy = :energy,
                health = :health, knowledge = :knowledge, points = :points, level = :level,
                stage = :stage, age_hours = :age_hours,
                is_sleeping = :is_sleeping, is_alive = :is_alive,
                last_update = :last_update
             WHERE id = :id'
        );
        $stmt->execute([
            ':hunger'      => $pet['hunger'],
            ':happiness'   => $pet['happiness'],
            ':energy'      => $pet['energy'],
            ':health'      => $pet['health'],
            ':knowledge'   => $pet['knowledge'],
            ':points'      => $pet['points'],
            ':level'       => $pet['level'],
            ':stage'       => $pet['stage'],
            ':age_hours'   => $pet['age_hours'],
            ':is_sleeping' => $pet['is_sleeping'],
            ':is_alive'    => $pet['is_alive'],
            ':last_update' => $pet['last_update'],
            ':id'          => $pet['id'],
        ]);
        return $this->find((int) $pet['id']);
    }
}
