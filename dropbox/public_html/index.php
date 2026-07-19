<?php
// === Accueil de PhotoSync ===
// - Base pas encore configurée  → assistant d'installation (install.php).
// - Base prête                  → galerie web (qui demandera la connexion au compte).
require __DIR__ . '/lib/bootstrap.php';
header('Location: ' . (Db::isReady() ? 'web/gallery.php' : 'install.php'));
exit;
