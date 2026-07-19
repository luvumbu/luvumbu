<?php
/**
 * Espace administrateur — connexion DÉDIÉE par identifiants.
 * Contrairement à login.php (qui propose aussi l'e-mail et Google), cette page
 * demande UNIQUEMENT l'identifiant et le mot de passe de l'administrateur.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/account.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = "Session expirée, merci de réessayer.";
    } elseif ($username === '') {
        $error = "Identifiant obligatoire.";
    } else {
        try {
            // 1) Vérification du hash stocké pour le compte administrateur.
            $user = find_user_by_login($username);
            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                header('Location: dashboard.php');
                exit;
            }

            // 2) Repli : authentification directe par les identifiants de la base
            //    (resynchronise le compte si le hash a été désynchronisé).
            $user = db_login_fallback($username, $password);
            if ($user) {
                login_user($user);
                header('Location: dashboard.php');
                exit;
            }

            $error = "Identifiant ou mot de passe incorrect.";
        } catch (Throwable $e) {
            $error = "Erreur de connexion à la base de données.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <title>Espace administrateur — CV Luvumbu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<div class="brand-logo">CV <span>Luvumbu</span></div>
<div class="card">
    <h1>🔒 Espace administrateur</h1>
    <p class="subtitle">Connexion par identifiants (réservée à l'administrateur)</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <label>Identifiant
            <input name="username" value="<?= htmlspecialchars($username) ?>" required autofocus>
        </label>
        <label>Mot de passe
            <input name="password" type="password" required>
        </label>
        <button class="btn" type="submit">Se connecter en tant qu'admin</button>
    </form>

    <p class="subtitle" style="margin:16px 0 0;font-size:.8rem;text-align:center">
        <a href="login.php">← Autre méthode de connexion (e-mail / Google)</a>
    </p>
</div>
</body>
</html>
