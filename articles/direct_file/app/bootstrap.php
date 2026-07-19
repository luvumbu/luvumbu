<?php
/**
 * Bootstrap de l'application.
 *
 * Initialise la journalisation, la session, l'autoloader de classes, les
 * fonctions utilitaires et le gestionnaire d'exceptions global. Inclus par le
 * front controller (public index.php) et par les outils CLI (bot/, tools/).
 */

define('APP_ROOT', dirname(__DIR__));   // racine du projet
define('APP_PATH', __DIR__);            // dossier app/

// ---- Journal d'erreurs (dans storage/, hors racine web) ----
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/error.log');
error_reporting(E_ALL);

// ---- Session (uniquement en contexte web) ----
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Autoloader : Core / Models / Controllers ----
spl_autoload_register(function (string $class): void {
    foreach (['Core', 'Models', 'Controllers'] as $dir) {
        $file = APP_PATH . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

// ---- Fonctions utilitaires globales ----
require APP_PATH . '/Core/helpers.php';

// ---- Schéma de base de données (create_schema) ----
require APP_ROOT . '/database/schema.php';

/**
 * Attrape toute exception/erreur fatale non gérée : la journalise puis renvoie
 * un message lisible (JSON pour les API, page d'aide sinon) au lieu d'un 500 muet.
 */
set_exception_handler(function (Throwable $ex): void {
    error_log('[EXCEPTION] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Erreur : ' . $ex->getMessage() . "\n");
        exit(1);
    }

    http_response_code(500);
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Erreur serveur : ' . $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<div style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:24px;'
           . 'background:#fef2f2;border:1px solid #fecaca;border-radius:12px">';
        echo '<h2 style="color:#dc2626;margin-top:0">⚠️ Erreur de connexion</h2>';
        echo '<pre style="white-space:pre-wrap;background:#fff;padding:12px;border-radius:8px">'
           . htmlspecialchars($ex->getMessage(), ENT_QUOTES) . '</pre>';
        echo '<a href="' . htmlspecialchars(base_url('setup')) . '" '
           . 'style="display:inline-block;margin-top:8px;background:#6366f1;color:#fff;'
           . 'padding:10px 18px;border-radius:8px;text-decoration:none">⚙️ Corriger la configuration</a>';
        echo '</div>';
    }
    exit;
});
