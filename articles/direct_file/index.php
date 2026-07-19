<?php
/**
 * Front controller : point d'entrée unique de l'application.
 *
 * Toutes les requêtes web passent ici (via .htaccess) ; ce fichier normalise
 * le chemin demandé puis le dispatche vers le bon contrôleur.
 */
require __DIR__ . '/app/bootstrap.php';

// ---- Normalisation du chemin demandé ----
// Retire le sous-dossier d'installation (ex: /direct_file) et tolère les
// anciennes URLs en .php (/chat.php → /chat) pour rester rétro-compatible.
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$uri       = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$path      = '/' . ltrim(substr($uri, strlen($scriptDir)), '/');
$path      = preg_replace('#\.php$#', '', $path);
$path      = rtrim($path, '/') ?: '/';
if ($path === '/index') {
    $path = '/';
}

// ---- Table de routage ----
$router = new Router();
$router->get('/',               [HomeController::class, 'index']);
$router->post('/',              [HomeController::class, 'store']);
$router->get('/chat',           [ChatController::class, 'show']);
$router->get('/view',           [ViewerController::class, 'show']);
$router->get('/api/messages',   [ApiController::class, 'listMessages']);
$router->post('/api/messages',  [ApiController::class, 'sendMessage']);
$router->post('/api/pseudo',    [ApiController::class, 'savePseudo']);
$router->get('/admin',          [AdminController::class, 'index']);
$router->post('/admin',         [AdminController::class, 'handle']);
$router->get('/setup',          [SetupController::class, 'index']);
$router->post('/setup',         [SetupController::class, 'store']);
$router->get('/install',        [SetupController::class, 'install']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
