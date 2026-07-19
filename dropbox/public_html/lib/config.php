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

// Le panneau d'administration (web/admin.php) utilise le mot de passe de la
// base de données (DB_PASS) — aucun mot de passe supplémentaire à définir ici.

// === Connexion « Se connecter avec Google » ===
// Identifiant client OAuth de type « Application Web » obtenu sur
// https://console.cloud.google.com  →  APIs et services  →  Identifiants
// →  Créer des identifiants  →  ID client OAuth  →  Application Web.
// LE MÊME identifiant sert au site web ET à l'application Android.
// Tant qu'il est vide, la connexion Google affiche un message de configuration.
// Exemple : '1234567890-abcdefg.apps.googleusercontent.com'
const GOOGLE_CLIENT_ID = '878381681024-d7hb2ih3f92jkrlhp4agvb9brpdqv61l.apps.googleusercontent.com';

// === Administrateurs par e-mail Google ===
// Tout compte dont l'e-mail Google figure ici devient (et reste) administrateur
// automatiquement à chaque connexion. Sépare plusieurs e-mails par des virgules.
// Exemple : 'luvumbu.n@gmail.com, autre.admin@gmail.com'
// >>> METS ICI TON e-mail Google d'administrateur <<<
const ADMIN_EMAILS = 'luvumbu.n@gmail.com';

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
