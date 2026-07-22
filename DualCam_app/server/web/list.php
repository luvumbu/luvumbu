<?php
// L'ancienne galerie (jeton dans l'URL) est remplacée par gallery.php,
// protégée par mot de passe. On redirige.
header('Location: gallery.php');
exit;
