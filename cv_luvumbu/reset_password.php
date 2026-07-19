<?php
/**
 * Réinitialisation du mot de passe via un lien reçu par e-mail.
 * Le jeton est passé en paramètre ?token=... ; il est validé (non expiré,
 * non utilisé) avant d'autoriser la saisie d'un nouveau mot de passe.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/account.php';
require __DIR__ . '/includes/password_reset.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$reset = find_valid_reset($token);

$error = '';
$done  = false;

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = "Session expirée, merci de réessayer.";
    } else {
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (strlen($new) < 6) {
            $error = "Le mot de passe doit faire au moins 6 caractères.";
        } elseif ($new !== $confirm) {
            $error = "La confirmation ne correspond pas.";
        } else {
            try {
                consume_reset($reset, $new);
                $done = true;
            } catch (Throwable $e) {
                $error = "Ce lien n'est plus valable. Merci d'en demander un nouveau.";
                $reset = null;
            }
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
    <title>Nouveau mot de passe — CV Luvumbu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<div class="brand-logo">CV <span>Luvumbu</span></div>
<div class="card">
    <h1>Nouveau mot de passe</h1>

    <?php if ($done): ?>
        <div class="alert alert-success">
            ✓ Votre mot de passe a été mis à jour. Vous pouvez vous connecter.
        </div>
        <a class="btn" href="login.php">Aller à la connexion →</a>

    <?php elseif (!$reset): ?>
        <div class="alert alert-error">
            Ce lien de réinitialisation est invalide ou a expiré.
        </div>
        <a class="btn" href="forgot_password.php">Demander un nouveau lien</a>

    <?php else: ?>
        <p class="subtitle">Choisissez un nouveau mot de passe pour votre compte.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <label>Nouveau mot de passe <span class="muted">(min. 6 caractères)</span>
                <input name="new_password" type="password" required autofocus>
            </label>
            <label>Confirmer le mot de passe
                <input name="confirm_password" type="password" required>
            </label>
            <button class="btn" type="submit">Enregistrer le mot de passe</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
