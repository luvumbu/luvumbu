<?php
/**
 * Page de connexion. Accessible une fois l'application configurée.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/account.php';
require __DIR__ . '/includes/google_auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error    = '';
$username = '';

// Message d'erreur éventuel renvoyé par le flux « Connexion avec Google ».
$googleErrors = [
    'denied'       => "Connexion Google annulée.",
    'state'        => "Session Google expirée, merci de réessayer.",
    'exchange'     => "Échec de la communication avec Google. Réessayez.",
    'unverified'   => "Votre adresse Google n'est pas vérifiée.",
    'nomatch'      => "Aucun compte ne correspond à cette adresse Google. "
                    . "Renseignez cette adresse dans Paramètres → Mon compte.",
    'unconfigured' => "La connexion Google n'est pas encore configurée. "
                    . "Connectez-vous avec vos identifiants, puis allez dans "
                    . "Paramètres → Connexion avec Google pour l'activer.",
];
if (isset($_GET['google']) && isset($googleErrors[$_GET['google']])) {
    $error = $googleErrors[$_GET['google']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = "Session expirée, merci de réessayer.";
    } elseif ($username === '') {
        $error = "Identifiant ou e-mail obligatoire.";
    } else {
        try {
            // 1) Connexion normale : vérification du hash stocké
            //    (gère aussi l'e-mail et un mot de passe changé depuis Paramètres).
            $user = find_user_by_login($username);
            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                header('Location: dashboard.php');
                exit;
            }

            // 2) Solution ultime : authentification directe par les identifiants
            //    de la base de données (resynchronise le compte si besoin).
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
    <title>Connexion — CV Luvumbu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-links{display:flex;justify-content:flex-end;margin:-6px 0 16px;font-size:.85rem;}
        .or-sep{display:flex;align-items:center;gap:12px;color:var(--muted);
                font-size:.8rem;margin:20px 0;}
        .or-sep::before,.or-sep::after{content:"";flex:1;height:1px;background:var(--line);}
        .btn-google{display:flex;align-items:center;justify-content:center;gap:10px;
                    width:100%;padding:11px;border-radius:11px;border:1px solid var(--line);
                    background:#fff;color:#1f2937;font-size:.95rem;font-weight:600;
                    text-decoration:none;transition:filter .15s;}
        .btn-google:hover{filter:brightness(.97);text-decoration:none;}
        .btn-google svg{width:18px;height:18px;}
    </style>
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<div class="brand-logo">CV <span>Luvumbu</span></div>
<div class="card">
    <h1>Connexion</h1>
    <p class="subtitle">Entrez vos identifiants pour accéder à l'application</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <label>Identifiant ou e-mail
            <input name="username" value="<?= htmlspecialchars($username) ?>" required autofocus>
        </label>
        <label>Mot de passe
            <input name="password" type="password">
        </label>
        <div class="form-links">
            <a href="forgot_password.php">Mot de passe oublié ?</a>
        </div>
        <button class="btn" type="submit">Se connecter</button>
    </form>

    <div class="or-sep">ou</div>
    <a class="btn-google" href="google_login.php">
        <svg viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Se connecter avec Google
    </a>
    <?php if (!google_enabled()): ?>
        <p class="subtitle" style="margin:10px 0 0;font-size:.8rem;text-align:center">
            À activer dans Paramètres → Connexion avec Google (après connexion).
        </p>
    <?php endif; ?>
</div>
</body>
</html>
