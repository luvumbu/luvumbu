<?php
/**
 * Front controller de l'API.
 * TOUTES les requêtes /api/* passent par ici (voir .htaccess).
 */

declare(strict_types=1);

require __DIR__ . '/../src/Core/Autoloader.php';
\App\Core\Autoloader::register();

// Horloge cohérente pour tout le back (sinon le calcul du temps écoulé déraille).
$cfg = require __DIR__ . '/../config/config.php';
date_default_timezone_set($cfg['app']['timezone']);

use App\Core\Router;
use App\Core\Response;

// --- CORS (utile si le front tourne sur un autre port en dev) ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

// ---------------------------------------------------------------
//  ROUTES  (à brancher sur les vrais controllers au fur et à mesure)
// ---------------------------------------------------------------

$router->get('/ping', function () {
    Response::json(['message' => 'pong', 'time' => date('c')]);
});

// --- Créatures ---
$pets = new App\Controllers\PetController();
$router->get('/pets',            [$pets, 'index']);
$router->post('/pets',           [$pets, 'create']);
$router->get('/pets/{id}',       [$pets, 'show']);
$router->post('/pets/{id}/feed', [$pets, 'feed']);
$router->post('/pets/{id}/play', [$pets, 'play']);
$router->post('/pets/{id}/sleep',[$pets, 'sleep']);

// --- Apprendre ---
$learn = new App\Controllers\LearningController();
$router->get('/learn/question',  [$learn, 'question']);
$router->post('/learn/answer',   [$learn, 'answer']);
$router->post('/learn/bonus',    [$learn, 'bonus']);
$router->get('/learn/progress',  [$learn, 'progress']);

// --- Boutique ---
$shop = new App\Controllers\ShopController();
$router->get('/shop',      [$shop, 'index']);
$router->post('/shop/buy', [$shop, 'buy']);

// --- Connexion parent (Google) ---
$auth = new App\Controllers\AuthController();
$router->post('/auth/google', [$auth, 'google']);
$router->get('/auth/me',      [$auth, 'me']);
$router->post('/auth/delete', [$auth, 'deleteAccount']);

// --- Profils enfants ---
$children = new App\Controllers\ChildController();
$router->get('/children',         [$children, 'index']);
$router->post('/children',        [$children, 'create']);
$router->post('/children/delete', [$children, 'delete']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
