<?php

/**
 * Configuration centrale de l'application.
 * Les valeurs proviennent des variables d'environnement (config/.env).
 * Utilise le helper env() défini dans src/Core/helpers.php.
 */

return [

    'app' => [
        'name'       => env('APP_NAME', 'Plateforme Signalements'),
        'env'        => env('APP_ENV', 'production'),
        'debug'      => env('APP_DEBUG', false),
        'url'        => env('APP_URL', 'http://localhost'),
        'timezone'   => env('APP_TIMEZONE', 'Europe/Paris'),
        'locale'     => env('APP_LOCALE', 'fr'),
        'enc_key'    => env('APP_ENCRYPTION_KEY', ''),
    ],

    'database' => [
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => (int) env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'badplace'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => env('DB_CHARSET', 'utf8mb4'),
    ],

    'jwt' => [
        'secret'      => env('JWT_SECRET', ''),
        'access_ttl'  => (int) env('JWT_ACCESS_TTL', 900),
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1209600),
        'issuer'      => env('JWT_ISSUER', 'badplace'),
        'algo'        => 'HS256',
    ],

    'cors' => [
        'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))),
    ],

    'rate_limit' => [
        'max'    => (int) env('RATE_LIMIT_MAX', 120),
        'window' => (int) env('RATE_LIMIT_WINDOW', 60),
    ],

    'upload' => [
        'max_size'        => (int) env('UPLOAD_MAX_SIZE', 20971520),
        'allowed_images'  => array_filter(explode(',', env('UPLOAD_ALLOWED_IMAGES', 'jpg,jpeg,png,webp'))),
        'allowed_videos'  => array_filter(explode(',', env('UPLOAD_ALLOWED_VIDEOS', 'mp4,webm'))),
        'allowed_docs'    => array_filter(explode(',', env('UPLOAD_ALLOWED_DOCS', 'pdf'))),
        'media_path'      => dirname(__DIR__) . '/storage/media',
        'thumb_path'      => dirname(__DIR__) . '/storage/thumbnails',
    ],

    'mail' => [
        'host'         => env('MAIL_HOST', 'localhost'),
        'port'         => (int) env('MAIL_PORT', 25),
        'username'     => env('MAIL_USERNAME', ''),
        'password'     => env('MAIL_PASSWORD', ''),
        'encryption'   => env('MAIL_ENCRYPTION', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@badplace.local'),
        'from_name'    => env('MAIL_FROM_NAME', 'Plateforme Signalements'),
    ],

    'reports' => [
        // En dev : publication immédiate pour voir le résultat sur la carte.
        // En prod : mettre false -> passage par la file de modération.
        'auto_publish' => (bool) env('REPORTS_AUTO_PUBLISH', false),
        'activity_medium_at' => 3,   // seuil 🟠 par lieu
        'activity_high_at'   => 10,  // seuil 🔴 par lieu
        // Vigilance de zone (par ville) : seuils sur le total de témoignages de la ville
        'zone_medium_at' => (int) env('ZONE_MEDIUM_AT', 5),
        'zone_high_at'   => (int) env('ZONE_HIGH_AT', 15),
    ],

    'geocoding' => [
        'endpoint'   => env('GEOCODING_ENDPOINT', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('GEOCODING_USER_AGENT', 'BadPlace/1.0'),
    ],

    'oauth' => [
        'google' => [
            'client_id'     => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        ],
        'apple' => [
            'client_id' => env('APPLE_CLIENT_ID', ''),
            'team_id'   => env('APPLE_TEAM_ID', ''),
            'key_id'    => env('APPLE_KEY_ID', ''),
        ],
    ],

    'paths' => [
        'root'       => dirname(__DIR__),
        'storage'    => dirname(__DIR__) . '/storage',
        'logs'       => dirname(__DIR__) . '/storage/logs',
        'migrations' => dirname(__DIR__) . '/database/migrations',
        'seeds'      => dirname(__DIR__) . '/database/seeds',
    ],
];
