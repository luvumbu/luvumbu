<?php
// === Réponses JSON normalisées (pour l'app et les pages AJAX) ===

final class Api
{
    /** En-tête JSON UTF-8 (à appeler en début de script d'API). */
    public static function header(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    /** Émet une réponse JSON puis termine le script. */
    public static function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Raccourci d'erreur : {"ok":false,"error":"..."} + code HTTP. */
    public static function fail(string $msg, int $code = 400): void
    {
        self::json(['ok' => false, 'error' => $msg], $code);
    }
}
