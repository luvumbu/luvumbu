<?php
/**
 * Retour de Google après consentement.
 * Vérifie le state, échange le code, contrôle l'e-mail vérifié, puis ouvre la
 * session si cet e-mail correspond à celui (ou à l'identifiant) du compte.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';      // démarre la session
require __DIR__ . '/includes/account.php';
require __DIR__ . '/includes/google_auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

/** Redirige vers la connexion avec un code d'erreur lisible. */
function google_fail(string $code): void
{
    header('Location: login.php?google=' . urlencode($code));
    exit;
}

if (!google_enabled()) {
    google_fail('unconfigured');
}

// L'utilisateur a refusé, ou Google a renvoyé une erreur.
if (isset($_GET['error'])) {
    google_fail('denied');
}

$code  = (string) ($_GET['code'] ?? '');
$state = $_GET['state'] ?? null;

if ($code === '' || !google_check_state($state)) {
    google_fail('state');
}

try {
    $profile = google_exchange_code($code);
} catch (Throwable $e) {
    google_fail('exchange');
}

if (empty($profile['email']) || !$profile['email_verified']) {
    google_fail('unverified');
}

// Compte mono-administrateur. On connecte si :
//  - l'adresse Google figure dans la LISTE BLANCHE gérée dans Paramètres, ou
//  - elle correspond à l'e-mail / l'identifiant enregistré du compte.
$user = find_user_by_login($profile['email']);

if (!$user && is_google_email_allowed($profile['email'])) {
    // Adresse autorisée : on ouvre la session sur le compte administrateur.
    $user = get_primary_account();
}

if (!$user) {
    // Adoption automatique à la première connexion : si le compte admin n'a pas
    // encore d'e-mail (et qu'aucune liste blanche n'est définie), cette première
    // adresse Google devient celle de l'admin. Les fois suivantes, seule elle
    // correspondra (via find_user_by_login ci-dessus).
    $user = google_adopt_primary_email($profile['email']);
}

if (!$user) {
    google_fail('nomatch');
}

login_user($user);
header('Location: dashboard.php');
exit;
