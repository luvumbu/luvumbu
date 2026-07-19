<?php
/*
 * SCRIPT D'INSTALLATION - À UTILISER UNE SEULE FOIS
 * --------------------------------------------------
 * Usage : http://localhost/hrconsulting/admin/promote_admin.php?email=ton@email.com
 * Promeut l'utilisateur correspondant en administrateur.
 *
 * IMPORTANT : Supprime ce fichier après l'avoir utilisé (sécurité).
 */
require_once __DIR__ . '/../config/bdd.php';

/*
 * Sécurité : ce script de bootstrap est DÉSACTIVÉ par défaut.
 * Il ne s'exécute que si SETUP_KEY (dans config/bdd.php) est renseigné ET
 * fourni dans l'URL via ?key=...  À utiliser une seule fois puis remettre
 * SETUP_KEY à '' (ou supprimer ce fichier).
 */
if (SETUP_KEY === '' || !hash_equals(SETUP_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    die("Accès refusé. Cette fonction d'administration est désactivée.");
}

$email = trim($_GET['email'] ?? '');

if ($email === '') {
    ?>
    <!DOCTYPE html><html><head><title>Promote admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/styles.css"></head><body>
    <div class="page-main"><div class="auth-container">
        <div class="auth-form">
            <h2>Promouvoir un compte en admin</h2>
            <p>Entre l'email du compte (déjà inscrit) à promouvoir en administrateur.</p>
            <form method="GET">
                <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
                <input type="email" name="email" placeholder="email du compte" required>
                <button type="submit" class="btn btn-primary">Promouvoir</button>
            </form>
            <p class="form-foot"><strong style="color:#c0392b">Supprime ce fichier après usage !</strong></p>
        </div>
    </div></div></body></html>
    <?php
    exit;
}

$check = $bdd->prepare('SELECT user_id, user_name, user_is_admin FROM users WHERE user_email = :email');
$check->execute(['email' => $email]);
$user = $check->fetch();

if (!$user) {
    die("Aucun utilisateur trouvé avec l'email : " . htmlspecialchars($email) . ". <br>Inscris-toi d'abord sur <a href='" . BASE_URL . "pages/inscription.php'>inscription.php</a>");
}

if ($user['user_is_admin']) {
    die("L'utilisateur " . htmlspecialchars($user['user_name']) . " est déjà admin. <a href='" . BASE_URL . "admin/admin.php'>Aller à l'admin</a>");
}

$bdd->prepare('UPDATE users SET user_is_admin = 1 WHERE user_id = :uid')
    ->execute(['uid' => $user['user_id']]);

echo "<h2>✅ " . htmlspecialchars($user['user_name']) . " est maintenant administrateur !</h2>";
echo "<p><strong style='color:#c0392b'>IMPORTANT : Supprime ce fichier promote_admin.php maintenant.</strong></p>";
echo "<p>1. Déconnecte-toi et reconnecte-toi pour que les droits admin s'activent.</p>";
echo "<p><a href='" . BASE_URL . "pages/logout.php'>Déconnexion</a> · <a href='" . BASE_URL . "admin/admin.php'>Aller à l'admin</a></p>";
