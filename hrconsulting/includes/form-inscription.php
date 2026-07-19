<?php
require_once __DIR__ . '/../config/bdd.php';

$erreurs = [];
$old = ['user_name' => '', 'user_email' => '', 'jesuis' => 'freelance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submiti'])) {
    csrf_check();
    $user_name      = trim($_POST['user_name'] ?? '');
    $user_email     = trim($_POST['user_email'] ?? '');
    $user_password1 = $_POST['user_password1'] ?? '';
    $user_password2 = $_POST['user_password2'] ?? '';
    $user_jesuis    = $_POST['jesuis'] ?? 'freelance';

    $old['user_name']  = $user_name;
    $old['user_email'] = $user_email;
    $old['jesuis']     = $user_jesuis;

    if ($user_name === '')                                 $erreurs[] = "Le nom est obligatoire.";
    if (!filter_var($user_email, FILTER_VALIDATE_EMAIL))   $erreurs[] = "L'adresse email est invalide.";
    if (strlen($user_password1) < 6)                       $erreurs[] = "Le mot de passe doit faire au moins 6 caractères.";
    if ($user_password1 !== $user_password2)               $erreurs[] = "Les mots de passe ne correspondent pas.";
    if (!in_array($user_jesuis, ['freelance', 'recruteur'], true)) $erreurs[] = "Type de compte invalide.";

    if (empty($erreurs)) {
        $check = $bdd->prepare('SELECT user_id FROM users WHERE user_email = :email');
        $check->execute(['email' => $user_email]);
        if ($check->fetch()) {
            $erreurs[] = "Cette adresse email est déjà utilisée.";
        }
    }

    if (empty($erreurs)) {
        $hash = password_hash($user_password1, PASSWORD_DEFAULT);
        $req = $bdd->prepare(
            'INSERT INTO users(user_name, user_email, user_password, user_jesuis)
             VALUES(:user_name, :user_email, :user_password, :user_jesuis)'
        );
        $req->execute([
            'user_name'     => $user_name,
            'user_email'    => $user_email,
            'user_password' => $hash,
            'user_jesuis'   => $user_jesuis,
        ]);

        connecter_utilisateur([
            'user_id'       => (int)$bdd->lastInsertId(),
            'user_name'     => $user_name,
            'user_email'    => $user_email,
            'user_jesuis'   => $user_jesuis,
            'user_is_admin' => 0,
        ]);

        flash_set('success', 'Bienvenue ' . htmlspecialchars($user_name) . ' ! Votre compte a été créé.');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}
?>
<div class="auth-container">
    <form method="POST" class="auth-form">
        <?= csrf_field() ?>
        <h2>Créer un compte</h2>

        <?php if (!empty($erreurs)): ?>
            <div class="alert alert-error">
                <?php foreach ($erreurs as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <input type="text" name="user_name" placeholder="Nom complet" value="<?= htmlspecialchars($old['user_name']) ?>" required>
        <input type="email" name="user_email" placeholder="Adresse email" value="<?= htmlspecialchars($old['user_email']) ?>" required>
        <input type="password" name="user_password1" placeholder="Mot de passe (6 caractères min)" required>
        <input type="password" name="user_password2" placeholder="Confirmer le mot de passe" required>

        <div class="radio-group">
            <p>Je suis :</p>
            <label>
                <input type="radio" name="jesuis" value="freelance" <?= $old['jesuis']==='freelance' ? 'checked' : '' ?>>
                Freelance (je cherche des missions)
            </label>
            <label>
                <input type="radio" name="jesuis" value="recruteur" <?= $old['jesuis']==='recruteur' ? 'checked' : '' ?>>
                Recruteur (je publie des annonces)
            </label>
        </div>

        <button type="submit" name="submiti" class="btn btn-primary">S'inscrire</button>
        <p class="form-foot">Déjà inscrit ? <a href="<?= BASE_URL ?>pages/connexion.php">Se connecter</a></p>
    </form>
</div>
