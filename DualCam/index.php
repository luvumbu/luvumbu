<?php
// === Accueil de https://luvumbu.com/DualCam/ ===
// - Base DualCam pas encore configurée → assistant d'installation (install.php).
// - Base prête                         → interface web dédiée DualCam.
require __DIR__ . '/lib/bootstrap.php';
header('Location: ' . (Db::isReady() ? 'web/dualcam.php' : 'install.php'));
exit;
