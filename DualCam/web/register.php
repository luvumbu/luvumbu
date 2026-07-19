<?php
// === Inscription web — désormais automatique via Google ===
// Il n'y a plus de création de compte manuelle : le compte est créé tout seul
// lors de la première connexion « Se connecter avec Google » sur gallery.php.
// On garde ce fichier uniquement pour rediriger les anciens liens.

require __DIR__ . '/../lib/bootstrap.php';
header('Location: gallery.php');
exit;
