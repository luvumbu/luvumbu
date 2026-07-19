<?php
/**
 * Modèle Participant : pseudo associé à une IP dans une conversation.
 */
class Participant
{
    /** Pseudo enregistré pour l'IP courante dans une conversation (ou null). */
    public static function pseudoFor(int $conversationId, string $ip): ?string
    {
        $st = db()->prepare(
            'SELECT pseudo FROM participants WHERE conversation_id = ? AND ip = ?'
        );
        $st->execute([$conversationId, $ip]);
        $row = $st->fetch();
        return $row ? $row['pseudo'] : null;
    }

    /** Upsert : un seul pseudo par IP et par conversation. */
    public static function save(int $conversationId, string $ip, string $pseudo): void
    {
        $up = db()->prepare(
            'INSERT INTO participants (conversation_id, ip, pseudo)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE pseudo = VALUES(pseudo)'
        );
        $up->execute([$conversationId, $ip, mb_substr($pseudo, 0, 40)]);
    }
}
