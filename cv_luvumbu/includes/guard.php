<?php
/**
 * Garde-fou des pages web.
 * Vérifie que l'application est correctement configurée et que la base répond.
 * - Pas configurée            -> page de configuration (install.php).
 * - Configurée mais base KO   -> page de configuration en mode reconfiguration,
 *                                avec le message d'erreur.
 *
 * À inclure en haut des pages web (pas des endpoints API, qui renvoient du JSON).
 */

require_once __DIR__ . '/db.php';

function ensure_ready(): void
{
    // 1) Jamais configurée -> configuration.
    if (!is_installed()) {
        header('Location: install.php');
        exit;
    }

    // 2) Configurée mais la base ne répond pas (paramètres erronés, serveur arrêté…).
    $health = db_can_connect();
    if (!$health['ok']) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['db_error'] = $health['error'];
        header('Location: install.php?reconfigure=1');
        exit;
    }
}
