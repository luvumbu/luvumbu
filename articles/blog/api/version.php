<?php
require_once __DIR__ . '/_bootstrap.php';

// Version actuelle de l'app mobile.
// Bumper ce nombre a chaque deploiement de mobile-app/ ou api/
// pour que les telephones detectent qu'une mise a jour est dispo.
const MOBILE_APP_VERSION = 'v17';

// Pas de cache : on veut TOUJOURS la derniere valeur
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

json_response([
    'version' => MOBILE_APP_VERSION,
    'released_at' => '2026-05-28',
]);
