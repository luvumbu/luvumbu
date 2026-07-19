<?php
require_once __DIR__ . '/../config/bdd.php';
csrf_check();   // valide le jeton CSRF avant toute sortie (POST du formulaire de connexion)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Connexion - HR Consulting</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <?php if (isset($_GET['admin'])): ?>
        <?php include INCLUDES . '/form-admin.php';       // connexion admin par identifiants MySQL (sans email) ?>
    <?php else: ?>
        <?php include INCLUDES . '/form-connexion.php';   // connexion utilisateur classique (email) ?>
    <?php endif; ?>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
