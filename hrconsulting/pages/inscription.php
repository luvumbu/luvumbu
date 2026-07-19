<?php
require_once __DIR__ . '/../config/bdd.php';
csrf_check();   // valide le jeton CSRF avant toute sortie (POST du formulaire d'inscription)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Inscription - HR Consulting</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <?php include INCLUDES . '/form-inscription.php'; ?>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
