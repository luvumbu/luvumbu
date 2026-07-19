<?php
require_once __DIR__ . '/../config/bdd.php';

$erreurs = [];
$email_old = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitc'])) {
    csrf_check();
    $user_email    = trim($_POST['user_email'] ?? '');
    $user_password = $_POST['user_password1'] ?? '';
    $email_old     = $user_email;

    if ($user_email === '' || $user_password === '') {
        $erreurs[] = "Email et mot de passe obligatoires.";
    } else {
        $req = $bdd->prepare('SELECT * FROM users WHERE user_email = :email LIMIT 1');
        $req->execute(['email' => $user_email]);
        $user = $req->fetch();

        if ($user && password_verify($user_password, $user['user_password'])) {
            connecter_utilisateur($user);

            flash_set('success', 'Bon retour ' . htmlspecialchars($user['user_name']) . ' !');
            // Redirection selon le rôle : les admins vont directement à l'espace admin
            $destination = est_admin() ? 'admin/admin.php' : 'index.php';
            header('Location: ' . BASE_URL . $destination);
            exit;
        } else {
            $erreurs[] = "Email ou mot de passe incorrect.";
        }
    }
}
?>
<div class="auth-container">
    <form method="POST" class="auth-form">
        <?= csrf_field() ?>
        <h2><?= isset($_GET['admin']) ? 'Connexion administrateur' : 'Connexion' ?></h2>

        <?php if (!empty($erreurs)): ?>
            <div class="alert alert-error">
                <?php foreach ($erreurs as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <input type="text" name="user_email" placeholder="Adresse email" value="<?= htmlspecialchars($email_old) ?>" required>
        <input type="password" name="user_password1" id="login-pwd" placeholder="Mot de passe" required>
        <label style="font-size:12px;font-weight:normal;color:#6b7684;display:flex;align-items:center;gap:6px;margin-top:-4px">
            <input type="checkbox" onclick="var p=document.getElementById('login-pwd');p.type=this.checked?'text':'password';">
            Afficher le mot de passe
        </label>

        <button type="submit" name="submitc" class="btn btn-primary">Se connecter</button>
        <p class="form-foot">Pas encore de compte ? <a href="<?= BASE_URL ?>pages/inscription.php">Créer un compte</a></p>
    </form>
</div>
