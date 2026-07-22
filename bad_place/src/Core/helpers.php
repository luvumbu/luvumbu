<?php

use App\Core\Config;

if (!function_exists('env')) {
    /**
     * Lit une variable d'environnement en normalisant les valeurs booléennes/nulles.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Accès à la configuration en notation pointée : config('database.host').
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('logger')) {
    /**
     * Écrit une ligne dans le journal applicatif du jour.
     */
    function logger(string $message, string $level = 'INFO', array $context = []): void
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $date = date('Y-m-d');
        $time = date('c');
        $ctx  = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        @file_put_contents(
            "$dir/app-$date.log",
            "[$time] $level: $message$ctx" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

if (!function_exists('str_uuid')) {
    function str_uuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}

if (!function_exists('str_slug')) {
    /**
     * Transforme une chaîne en slug ASCII (accents retirés, minuscules, tirets).
     */
    function str_slug(string $text, string $separator = '-'): string
    {
        $text = trim($text);
        // Translittération fiable des accents français (indépendante de la locale iconv)
        $map = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
            'ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','œ'=>'oe',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y','ß'=>'ss',
        ];
        $text = strtr(mb_strtolower($text, 'UTF-8'), $map);
        $text = preg_replace('/[^a-z0-9]+/', $separator, $text);
        return trim($text, $separator);
    }
}
