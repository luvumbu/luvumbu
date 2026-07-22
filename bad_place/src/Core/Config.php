<?php

namespace App\Core;

/**
 * Registre de configuration en mémoire, chargé une fois au démarrage.
 * Supporte l'accès en notation pointée : Config::get('database.host').
 */
final class Config
{
    private static array $items = [];

    public static function load(array $items): void
    {
        self::$items = $items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$items)) {
            return self::$items[$key];
        }

        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
