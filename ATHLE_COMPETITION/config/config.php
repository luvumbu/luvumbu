<?php
/**
 * Configuration de l'application.
 *
 * Pour surcharger sans toucher au dépôt, créez config/config.local.php
 * qui renvoie un tableau fusionné par-dessus celui-ci.
 */

$config = [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'athle_competition',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // Contact obligatoire pour la politique d'usage de Nominatim (OpenStreetMap).
    // Remplacez par votre adresse e-mail réelle : sans User-Agent identifiable,
    // Nominatim bloque les requêtes.
    'geocoder' => [
        'provider'         => 'nominatim',
        'endpoint'         => 'https://nominatim.openstreetmap.org/search',
        'user_agent'       => 'ATHLE_COMPETITION/1.0 (fjs.troubadourstory@gmail.com)',
        'delay_seconds'    => 1.1,   // Nominatim : 1 requête/seconde maximum
        'max_attempts'     => 3,     // au-delà, la ville est marquée "failed"
        'default_country'  => 'BE',
        'accept_language'  => 'fr,nl,en',
    ],

    'paths' => [
        'data' => __DIR__ . '/../data',
    ],

    // Pays affichés dans l'interface (code ISO => libellé)
    'countries' => [
        'BE' => 'Belgique',
        'FR' => 'France',
        'NL' => 'Pays-Bas',
        'LU' => 'Luxembourg',
        'DE' => 'Allemagne',
    ],
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        foreach ($local as $section => $values) {
            if (is_array($values) && isset($config[$section]) && is_array($config[$section])) {
                $config[$section] = array_replace($config[$section], $values);
            } else {
                $config[$section] = $values;
            }
        }
    }
}

return $config;
