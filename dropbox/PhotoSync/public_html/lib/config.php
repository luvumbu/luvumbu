<?php
// === Configuration PhotoSync (serveur) ===
// À éditer avec tes vraies valeurs avant de mettre en ligne.

// Renvoie un message d'erreur lisible (JSON) au lieu d'une page 500 vide,
// pour pouvoir diagnostiquer depuis l'app ou le navigateur.
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Serveur: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
});

// NB : l'inscription (app + web) et l'admin sont protégés par le MOT DE PASSE DE LA
// BASE DE DONNÉES (DB_PASS, défini par install.php). Il n'y a plus de code séparé à gérer.

// Mot de passe pour consulter la galerie web (gallery.php).
// >>> CHANGE-LE par ton propre mot de passe <<<
const GALLERY_PASSWORD = 'luvumbu2026';

// L'album MASQUÉ s'ouvre avec le mot de passe DU COMPTE, ou un mot de passe spécifique
// que chaque utilisateur définit lui-même (stocké haché par compte). Rien à régler ici.

// Le panneau d'administration (web/admin.php) utilise le mot de passe de la
// base de données (DB_PASS) — aucun mot de passe supplémentaire à définir ici.

// Dossier de stockage des photos reçues (à la racine de photos/, donc un cran au-dessus de lib/).
// Créé automatiquement, doit être inscriptible.
const UPLOAD_DIR = __DIR__ . '/../uploads';

// Taille max acceptée par fichier (octets). 0 = AUCUNE limite (gros volumes / vidéos).
const MAX_BYTES = 0;

// --- Connexion MySQL ---
// Les identifiants sont écrits automatiquement par l'assistant install.php dans le
// fichier db.config.php (généré, non versionné). Tant qu'il n'existe pas, ce sont les
// valeurs par défaut ci-dessous qui s'appliquent (et install.php proposera la configuration).
// La connexion elle-même est gérée par la classe Db (lib/Db.php).
$dbConf = is_file(__DIR__ . '/db.config.php') ? (require __DIR__ . '/db.config.php') : [];
if (!is_array($dbConf)) $dbConf = [];

define('DB_HOST', $dbConf['host'] ?? 'localhost');
define('DB_NAME', $dbConf['name'] ?? '');
define('DB_USER', $dbConf['user'] ?? '');
define('DB_PASS', $dbConf['pass'] ?? '');

// Préfixe ajouté devant les tables de PhotoSync (photosync_users, photosync_photos).
// Permet de cohabiter avec d'autres applications qui partagent la même base de données.
define('DB_PREFIX', $dbConf['prefix'] ?? 'photosync_');
