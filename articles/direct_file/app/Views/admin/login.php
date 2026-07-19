<?php /** @var string $error */ ?>
<main class="card">
    <h1>🔐 Admin</h1>
    <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

    <p class="subtitle">Connecte-toi avec les identifiants <strong>MySQL</strong> de
        <code>config/database.php</code> : l'<strong>utilisateur</strong> (ex : <code>root</code>)
        et son <strong>mot de passe</strong>. Si le mot de passe est vide, laisse ce champ vide.</p>

    <form method="post" action="<?= e(base_url('admin')) ?>">
        <input type="hidden" name="action" value="login">
        <label>Utilisateur MySQL <span class="muted">(ou nom de la base)</span>
            <input type="text" name="dbname" placeholder="ex : root" autocomplete="off" autofocus></label>
        <label>Mot de passe <span class="muted">(mot de passe MySQL, vide si aucun)</span>
            <input type="password" name="password" autocomplete="new-password"></label>
        <br>
        <button class="btn" type="submit">Se connecter</button>
    </form>

    <p style="margin-top:18px"><a class="back" href="<?= e(base_url()) ?>">← Retour à l'accueil</a></p>
</main>
