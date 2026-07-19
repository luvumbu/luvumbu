<?php
namespace App\Core;

/**
 * Autoloader PSR-4 maison (évite d'avoir à installer Composer pour démarrer).
 * Mappe le namespace "App\" vers le dossier /src.
 */
class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix  = 'App\\';
            $baseDir = __DIR__ . '/../';

            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}
