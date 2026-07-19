<?php
/**
 * Modèle Message : lecture et écriture des messages d'une conversation.
 */
class Message
{
    /** Messages d'une conversation après un id donné (limite 200). */
    public static function after(int $conversationId, int $afterId): array
    {
        $q = db()->prepare(
            'SELECT id, ip, pseudo, content, created_at
               FROM messages
              WHERE conversation_id = ? AND id > ?
           ORDER BY id ASC
              LIMIT 200'
        );
        $q->execute([$conversationId, $afterId]);
        return $q->fetchAll();
    }

    /** Tous les messages d'une conversation (pour l'admin). */
    public static function allFor(int $conversationId): array
    {
        $q = db()->prepare(
            'SELECT pseudo, content, created_at FROM messages
              WHERE conversation_id = ? ORDER BY id ASC'
        );
        $q->execute([$conversationId]);
        return $q->fetchAll();
    }

    /** Insère un message et renvoie son id. */
    public static function create(int $conversationId, string $ip, string $pseudo, string $content): int
    {
        $ins = db()->prepare(
            'INSERT INTO messages (conversation_id, ip, pseudo, content) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$conversationId, $ip, $pseudo, mb_substr($content, 0, 2000)]);
        return (int) db()->lastInsertId();
    }
}
