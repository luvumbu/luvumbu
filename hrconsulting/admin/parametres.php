<?php
require_once __DIR__ . '/../config/bdd.php';

if (!est_admin()) {
    flash_set('error', 'Accès réservé aux administrateurs.');
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $description = trim($_POST['description'] ?? '');
    if (app_params_save(['description' => $description])) {
        flash_set('success', 'Description de l\'application enregistrée.');
    } else {
        flash_set('error', "Impossible d'écrire config/app.php (droits d'écriture insuffisants).");
    }
    header('Location: ' . BASE_URL . 'admin/parametres.php');
    exit;
}

$description = app_param('description', APP_DESCRIPTION_DEFAUT);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Paramètres - Administration</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <div class="form-page">
        <h2>Paramètres de l'application</h2>
        <p class="user-role">Ce texte s'affiche dans la section « Qui sommes-nous ? » de la page d'accueil.</p>

        <form method="POST" class="big-form">
            <?= csrf_field() ?>
            <label>Description de l'application
                <textarea name="description" rows="7" placeholder="Décris ton application / ton activité..."><?= htmlspecialchars($description) ?></textarea>
            </label>
            <button type="submit" class="btn btn-success">Enregistrer</button>
        </form>
    </div>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
