<?php

/**
 * Définition des routes de l'API REST v1.
 * La variable $router est fournie par App::loadRoutes().
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\HealthController;
use App\Controllers\MetaController;
use App\Controllers\CategoryController;
use App\Controllers\AuthController;
use App\Controllers\ReportController;
use App\Controllers\MediaController;
use App\Controllers\MapController;
use App\Controllers\GeoController;
use App\Controllers\ContestationController;
use App\Controllers\AbuseController;
use App\Core\Response;

// --- Accueil : évite un 404 nu à la racine ---
$welcome = fn() => Response::success([
    'service' => config('app.name'),
    'message' => 'API opérationnelle. Le front web arrive aux prochaines phases.',
    'version' => 'v1',
    'endpoints' => [
        'GET /api/v1/health' => 'État du service',
    ],
]);
$router->get('/', $welcome);
$router->get('/api/v1', $welcome);

$router->group(['prefix' => '/api/v1'], function ($router) {

    // --- Santé / diagnostic ---
    $router->get('/health', [HealthController::class, 'index']);

    // --- Données publiques (alimentent la page d'accueil) ---
    $router->get('/meta/overview',        [MetaController::class, 'overview']);
    $router->get('/categories',           [CategoryController::class, 'index']);
    $router->get('/motifs',               [CategoryController::class, 'motifs']);
    $router->get('/discrimination-types', [CategoryController::class, 'discriminationTypes']);

    // --- Authentification ---
    $router->post('/auth/register', [AuthController::class, 'register']);
    $router->post('/auth/login',    [AuthController::class, 'login']);
    $router->post('/auth/refresh',  [AuthController::class, 'refresh']);
    $router->get('/auth/providers', [AuthController::class, 'providers']);
    $router->post('/auth/google',   [AuthController::class, 'google']);
    $router->get('/auth/me',        [AuthController::class, 'me'])->middleware('auth');
    $router->post('/auth/logout',   [AuthController::class, 'logout'])->middleware('auth');

    // --- Signalements ---
    $router->get('/reports',         [ReportController::class, 'index'])->middleware('auth.optional');
    $router->post('/reports',        [ReportController::class, 'store'])->middleware('auth');
    $router->get('/reports/{uuid}',  [ReportController::class, 'show'])->middleware('auth.optional');

    // --- Carte ---
    $router->get('/map/points',  [MapController::class, 'points']);
    $router->get('/map/zones',   [MapController::class, 'zones']);
    $router->get('/map/heatmap', [MapController::class, 'heatmap']);

    // --- Recherche d'adresses (autocomplétion, réservée aux membres) ---
    $router->get('/geo/search', [GeoController::class, 'search'])->middleware('auth');

    // --- Droit de réponse (LCEN, public) & signalement de contenu illicite ---
    $router->post('/contestations',          [ContestationController::class, 'store']);
    $router->post('/reports/{uuid}/abuse',   [AbuseController::class, 'reportContent'])->middleware('auth.optional');

    // --- Pièces jointes (contrôle d'accès interne) ---
    $router->get('/media/{uuid}',       [MediaController::class, 'show'])->middleware('auth.optional');
    $router->get('/media/{uuid}/thumb', [MediaController::class, 'thumb'])->middleware('auth.optional');

    // --- Auth (Phase 1 : squelette, implémenté en Phase 6/2) ---
    // $router->post('/auth/register', [AuthController::class, 'register']);
    // $router->post('/auth/login',    [AuthController::class, 'login']);
    // $router->post('/auth/refresh',  [AuthController::class, 'refresh']);

    // --- Référentiels publics (implémentés en Phase 2) ---
    // $router->get('/categories',           [CategoryController::class, 'index']);
    // $router->get('/motifs',               [ReferenceController::class, 'motifs']);
    // $router->get('/discrimination-types', [ReferenceController::class, 'discriminationTypes']);

    // --- Signalements (Phase 2) ---
    // $router->get('/reports',       [ReportController::class, 'index'])->middleware('auth.optional');
    // $router->post('/reports',      [ReportController::class, 'store'])->middleware('auth');
    // $router->get('/reports/{id}',  [ReportController::class, 'show'])->middleware('auth.optional');

    // --- Administration (Phase 7) ---
    // $router->group(['prefix' => '/admin', 'middleware' => ['auth', 'role:moderator']], function ($router) {
    //     $router->get('/moderation/queue', [AdminController::class, 'queue']);
    // });
});
