<?php
/**
 * Layout principal. Variables attendues :
 *   $content    (string) corps de page déjà rendu
 *   $title      (string) titre de l'onglet
 *   $bodyClass  (string) classes du <body>      — optionnel
 *   $bodyAttrs  (string) attributs bruts du <body> — optionnel (déjà échappés)
 */
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Direct File') ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
    <?= Setting::styleBlock() ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>"<?= isset($bodyAttrs) ? ' ' . $bodyAttrs : '' ?>>
<?= $content ?>
</body>
</html>
