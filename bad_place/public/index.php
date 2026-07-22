<?php

/**
 * Front controller unique de l'application.
 * Toutes les requêtes API sont réécrites vers ce fichier (voir .htaccess).
 */

declare(strict_types=1);

// Autoloader Composer
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;

$app = new App();
$app->loadRoutes(dirname(__DIR__) . '/api/routes.php');
$app->run();
