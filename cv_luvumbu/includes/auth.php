<?php
/**
 * Gestion de la session et de l'authentification.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** L'utilisateur est-il connecté ? */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Force la connexion : redirige vers login.php si non connecté.
 * Si l'utilisateur doit changer son mot de passe, le redirige vers la page dédiée.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if (!empty($_SESSION['must_change_pw'])) {
        header('Location: change_password.php');
        exit;
    }
}

/** Ouvre la session pour un utilisateur donné. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['must_change_pw'] = !empty($user['must_change_password']);
}

/** Ferme la session. */
function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

/** Jeton CSRF pour protéger les formulaires. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Vérifie le jeton CSRF soumis. */
function csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}
