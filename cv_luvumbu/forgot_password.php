<?php
/**
 * Mot de passe oublié — demande d'un lien de réinitialisation par e-mail.
 * Affiche toujours le même message générique (ne révèle pas l'existence d'un compte).
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/account.php';
require __DIR__ . '/includes/password_reset.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$sent  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = "Session expirée, merci de réessayer.";
    } else {
        $login = trim($_POST['login'] ?? '');
        if ($login === '') {
            $error = "Indiquez votre identifiant ou votre e-mail.";
        } else {
            try {
                $user = find_user_by_login($login);
                if ($user) {
                    send_password_reset($user); // retour ignoré (anti-énumération)
                }
            } catch (Throwable $e) {
                // On n'expose aucune erreur technique à ce stade.
            }
            $sent = true; // message générique systématique
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
    <title>Mot de passe oublié — CV Luvumbu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<div class="brand-logo">CV <span>Luvumbu</span></div>
<div class="card">
    <h1>Mot de passe oublié</h1>
    <p class="subtitle">Recevez un lien de réinitialisation par e-mail</p>

    <?php if ($sent): ?>
        <div class="alert alert-success">
            Si un compte correspond, un e-mail contenant un lien de réinitialisation
            (valable 1 heure) vient d'être envoyé. Pensez à vérifier vos spams.
        </div>
        <a class="btn" href="login.php">Retour à la connexion</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <label>Identifiant ou e-mail du compte
                <input name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required autofocus>
            </label>
            <button class="btn" type="submit">Envoyer le lien</button>
        </form>
        <p class="subtitle" style="margin-top:18px">
            <a href="login.php">← Retour à la connexion</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
