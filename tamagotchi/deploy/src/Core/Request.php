<?php
namespace App\Core;

/**
 * Lit le corps d'une requête entrante (JSON envoyé par le front).
 */
class Request
{
    /** Retourne le corps JSON décodé en tableau associatif. */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /** Récupère une valeur du corps avec valeur par défaut. */
    public static function input(string $key, $default = null)
    {
        return self::body()[$key] ?? $default;
    }
}
