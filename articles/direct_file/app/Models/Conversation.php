<?php
/**
 * Modèle Conversation : accès aux discussions.
 */
class Conversation
{
    /** Cherche une conversation par son code (ou null). */
    public static function findByCode(string $code): ?array
    {
        $st = db()->prepare('SELECT * FROM conversations WHERE code = ?');
        $st->execute([$code]);
        return $st->fetch() ?: null;
    }

    /** Cherche une conversation par son id (ou null). */
    public static function findById(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM conversations WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Crée une conversation et renvoie son code.
     * $passwordHash = null pour une discussion libre.
     */
    public static function create(string $title, ?string $passwordHash, bool $isOpen, string $creatorIp): string
    {
        $code = self::generateCode();
        $st = db()->prepare(
            'INSERT INTO conversations (code, title, password_hash, is_open, creator_ip)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$code, mb_substr($title, 0, 120), $passwordHash, $isOpen ? 1 : 0, $creatorIp]);
        return $code;
    }

    /** Toutes les conversations avec compteurs de messages et participants. */
    public static function allWithCounts(): array
    {
        return db()->query(
            'SELECT c.*,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id)    AS msg_count,
                (SELECT COUNT(*) FROM participants p WHERE p.conversation_id = c.id) AS part_count
             FROM conversations c
             ORDER BY c.created_at DESC'
        )->fetchAll();
    }

    /** Génère un code unique : 2 lettres + 3 chiffres (ex: AB123). */
    public static function generateCode(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $code = $letters[random_int(0, 25)]
                  . $letters[random_int(0, 25)]
                  . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $st = db()->prepare('SELECT 1 FROM conversations WHERE code = ?');
            $st->execute([$code]);
        } while ($st->fetch());
        return $code;
    }

    /** Titre affichable (titre saisi, ou « Discussion <CODE> »). */
    public static function displayTitle(array $conv): string
    {
        return $conv['title'] !== '' ? $conv['title'] : 'Discussion ' . $conv['code'];
    }

    /**
     * Modifie l'accès d'une conversation.
     *  - privée : $isOpen = false + $passwordHash (haché)
     *  - publique : $isOpen = true + $passwordHash = null
     */
    public static function setAccess(int $id, bool $isOpen, ?string $passwordHash): void
    {
        $st = db()->prepare(
            'UPDATE conversations SET is_open = ?, password_hash = ? WHERE id = ?'
        );
        $st->execute([$isOpen ? 1 : 0, $passwordHash, $id]);
    }

    /**
     * Supprime une conversation. Les messages et participants liés sont
     * supprimés automatiquement (clés étrangères ON DELETE CASCADE).
     */
    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM conversations WHERE id = ?')->execute([$id]);
    }
}
