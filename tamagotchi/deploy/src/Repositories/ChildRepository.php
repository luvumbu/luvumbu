<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Accès BDD pour les PROFILS ENFANTS (table `children`).
 * Chaque enfant appartient à un parent (user_id).
 */
class ChildRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** Tous les enfants d'un parent. */
    public function allByUser(int $userId): array
    {
        $s = $this->db->prepare('SELECT * FROM children WHERE user_id = ? ORDER BY id');
        $s->execute([$userId]);
        return $s->fetchAll();
    }

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM children WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /** Vérifie qu'un enfant appartient bien à ce parent (sécurité). */
    public function belongsTo(int $childId, int $userId): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM children WHERE id = ? AND user_id = ?');
        $s->execute([$childId, $userId]);
        return (bool) $s->fetch();
    }

    public function create(int $userId, string $name, string $avatar): array
    {
        $s = $this->db->prepare('INSERT INTO children (user_id, name, avatar) VALUES (?, ?, ?)');
        $s->execute([$userId, $name, $avatar]);
        return $this->find((int) $this->db->lastInsertId());
    }

    public function delete(int $id, int $userId): void
    {
        // Sécurité : on ne supprime que si l'enfant appartient bien à ce parent.
        if (!$this->belongsTo($id, $userId)) {
            return;
        }
        // Supprime d'abord les créatures de l'enfant
        // (leur progression / journaux suivent via les clés étrangères).
        $this->db->prepare('DELETE FROM pets WHERE child_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM children WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    public function countByUser(int $userId): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM children WHERE user_id = ?');
        $s->execute([$userId]);
        return (int) $s->fetchColumn();
    }
}
