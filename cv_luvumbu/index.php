<?php
/**
 * Point d'entrée de l'application.
 * - Pas encore configurée  -> assistant d'installation (paramètres de la base).
 * - Configurée mais non connecté -> page de connexion.
 * - Connecté -> tableau de bord.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

// Vérifie configuration + connexion base ; redirige vers la config si KO.
ensure_ready();

require __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

header('Location: dashboard.php');
exit;
