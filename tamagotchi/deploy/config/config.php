<?php
/**
 * Configuration centrale du jeu.
 * ⚠️ En production, sortir les identifiants BDD dans un fichier .env non versionné.
 */

return [

    // --- Base de données (XAMPP par défaut) ---
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'tamagotchi',
        'user'     => 'root',
        'password' => '',          // XAMPP : root sans mot de passe par défaut
        'charset'  => 'utf8mb4',
    ],

    // --- Constantes de gameplay (faciles à équilibrer ici) ---
    'game' => [
        // Vitesse de dégradation des jauges, en points PAR HEURE
        'hunger_rate'      => 8,   // faim qui monte
        'health_rate'      => 3,   // santé qui baisse par heure
        'happiness_rate'   => 5,   // bonheur qui baisse
        'energy_rate'      => 6,   // énergie qui baisse

        // Effet des actions
        'feed_hunger'      => -30,
        'play_happiness'   => 20,
        'play_energy'      => -15,
        'sleep_energy'     => 40,

        // Seuils
        'max_stat'         => 100,
        'death_threshold'  => 0,   // stat vitale à 0 trop longtemps = mort

        // Évolution : âge (en heures de jeu) pour changer de stade
        'evolve_stages'    => [24, 72, 168], // bébé → enfant → ado → adulte
    ],

    // --- Apprentissage ---
    'learning' => [
        'points_per_correct' => 5,   // points gagnés par bonne réponse
        'knowledge_per_correct' => 2,
        'happiness_per_correct' => 3,

        // Quiz par paliers : bonus de fin selon le nombre de questions choisi.
        'bonus' => [
            5  => 10,
            10 => 25,
            25 => 75,
        ],
        'perfect_multiplier' => 1.5, // sans faute → bonus ×1.5

        // Clé secrète pour signer les questions (empêche de tricher sur la réponse).
        // ⚠️ À changer et à sortir du code en production.
        'secret' => 'change-moi-en-prod-svp',
    ],

    // --- Connexion Google (comptes parents) ---
    'google' => [
        // ID client OAuth (public) — utilisé côté navigateur ET pour vérifier
        // les jetons Google côté serveur.
        'client_id' => '878381681024-6qnsarrvcrj935f56vln5uugc091gg7c.apps.googleusercontent.com',
    ],

    'app' => [
        'debug'    => true,
        'timezone' => 'Europe/Paris',
    ],
];
