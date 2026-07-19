<?php
/**
 * Démarre la connexion avec Google : redirige vers l'écran de consentement.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';      // démarre la session
require __DIR__ . '/includes/google_auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if (!google_enabled()) {
    header('Location: login.php?google=unconfigured');
    exit;
}

header('Location: ' . google_auth_url());
exit;
