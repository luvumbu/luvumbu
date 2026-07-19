<?php
// === Point d'entrée commun ===
// Charge la configuration puis toutes les classes utilitaires.
// Endpoints dans api/ ou web/ : require __DIR__ . '/../lib/bootstrap.php';
// install.php (racine de photos/)   : require __DIR__ . '/lib/bootstrap.php';

require_once __DIR__ . '/config.php';

// Noms de tables préfixés (compatibles si un ancien config.php n'a pas DB_PREFIX).
if (!defined('DB_PREFIX')) define('DB_PREFIX', 'photosync_');
if (!defined('TBL_USERS'))    define('TBL_USERS',    DB_PREFIX . 'users');
if (!defined('TBL_PHOTOS'))   define('TBL_PHOTOS',   DB_PREFIX . 'photos');
if (!defined('TBL_ATTEMPTS')) define('TBL_ATTEMPTS', DB_PREFIX . 'login_attempts');

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Api.php';
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Photos.php';
