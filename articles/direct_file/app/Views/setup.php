<?php /** @var array $values @var string $message @var bool $success */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuration — Direct File</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
</head>
<body class="home">
    <main class="card">
        <h1>⚙️ Configuration</h1>
        <p class="subtitle">Connexion à la base de données MySQL.</p>

        <?php if ($message): ?>
            <div class="alert" style="<?= $success ? 'background:#14532d;color:#bbf7d0' : '' ?>">
                <pre style="white-space:pre-wrap;margin:0;font-family:inherit"><?= e($message) ?></pre>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <a href="<?= e(base_url()) ?>" class="btn" style="display:block;text-align:center;text-decoration:none">
                → Aller à l'application
            </a>
        <?php else: ?>
            <form method="post" action="<?= e(base_url('setup')) ?>" class="panel active">
                <label>Hôte
                    <input type="text" name="host" value="<?= e($values['host']) ?>" required>
                </label>
                <label>Nom de la base
                    <input type="text" name="name" value="<?= e($values['name']) ?>" required>
                </label>
                <label>Utilisateur
                    <input type="text" name="user" value="<?= e($values['user']) ?>" required>
                </label>
                <label>Mot de passe MySQL
                    <input type="text" name="pass" value="<?= e($values['pass']) ?>"
                           placeholder="Laisser vide si aucun" autocomplete="off">
                </label>
                <label class="radio" style="margin-top:4px">
                    <input type="checkbox" name="create_db" value="1" checked>
                    <span>Créer la base et les tables si elles n'existent pas</span>
                </label>
                <button type="submit" class="btn">Tester et enregistrer</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
