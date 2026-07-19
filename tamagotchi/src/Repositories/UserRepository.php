<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Accès BDD pour les comptes PARENTS (table `users`).
 * Un parent peut se connecter via Google (google_sub).
 */
class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function findByGoogleSub(string $sub): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE google_sub = ?');
        $s->execute([$sub]);
        return $s->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $s->execute([$email]);
        return $s->fetch() ?: null;
    }

    /** Crée un parent connecté via Google. */
    public function createGoogle(string $sub, string $email, string $username, ?string $avatar): array
    {
        $s = $this->db->prepare(
            'INSERT INTO users (username, email, google_sub, avatar_url, password_hash)
             VALUES (?, ?, ?, ?, NULL)'
        );
        $s->execute([$username, $email, $sub, $avatar]);
        return $this->find((int) $this->db->lastInsertId());
    }

    /** Rattache un compte Google à un parent existant (même email). */
    public function linkGoogle(int $id, string $sub, ?string $avatar): void
    {
        $s = $this->db->prepare('UPDATE users SET google_sub = ?, avatar_url = ? WHERE id = ?');
        $s->execute([$sub, $avatar, $id]);
    }

    /** Supprime le parent → efface EN CASCADE ses enfants et leurs créatures. */
    public function delete(int $id): void
    {
        // Les créatures sont liées par user_id (ON DELETE CASCADE) et par child_id ;
        // on nettoie explicitement pour ne rien laisser d'orphelin.
        $this->db->prepare('DELETE FROM pets WHERE user_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
