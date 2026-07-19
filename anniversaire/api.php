<?php
/**
 * API — Compte à rebours anniversaire
 * Gère les "espaces" : chacun peut créer le sien, protégé par mot de passe.
 * La base et la table sont créées automatiquement au premier appel.
 */
header('Content-Type: application/json; charset=utf-8');

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';                 // XAMPP : root sans mot de passe par défaut
$DB_NAME = 'anniversaire_app';

function out($a) { echo json_encode($a); exit; }

// --- Connexion + création automatique de la base / table ---
try {
  $pdo = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo->exec("USE `$DB_NAME`");
  $pdo->exec("CREATE TABLE IF NOT EXISTS spaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    data LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Config globale de l'accueil (une seule ligne). Admin initial : identifiant "admin" / mot de passe "admin"
  $pdo->exec("CREATE TABLE IF NOT EXISTS app_config (
    id TINYINT PRIMARY KEY,
    master_user VARCHAR(80) NOT NULL DEFAULT 'admin',
    master_hash VARCHAR(255) NOT NULL,
    data LONGTEXT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Migration : ajoute la colonne identifiant si la table existait déjà sans elle (MariaDB)
  $pdo->exec("ALTER TABLE app_config ADD COLUMN IF NOT EXISTS master_user VARCHAR(80) NOT NULL DEFAULT 'admin'");
  $st = $pdo->prepare("INSERT IGNORE INTO app_config (id, master_user, master_hash, data) VALUES (1, 'admin', ?, ?)");
  $st->execute([password_hash('admin', PASSWORD_DEFAULT), json_encode(new stdClass())]);
} catch (Exception $e) {
  out(['ok' => false, 'error' => 'Base de données inaccessible. Démarre MySQL dans XAMPP. (' . $e->getMessage() . ')']);
}

session_start();
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';

// --- Est-on déjà connecté à un espace ? (au chargement de la page) ---
if ($action === 'me') {
  if (!empty($_SESSION['space'])) {
    $st = $pdo->prepare("SELECT data FROM spaces WHERE name = ?");
    $st->execute([$_SESSION['space']]);
    $row = $st->fetch();
    if ($row) out(['ok' => true, 'space' => $_SESSION['space'], 'data' => json_decode($row['data'] ?: '{}', true)]);
  }
  out(['ok' => false, 'error' => 'Aucune session.']);
}

// --- Créer un espace ---
if ($action === 'create') {
  $name = trim($in['name'] ?? '');
  $password = (string)($in['password'] ?? '');
  if (mb_strlen($name) < 1 || mb_strlen($password) < 1) out(['ok' => false, 'error' => 'Nom et mot de passe requis.']);
  if (mb_strlen($name) > 80) out(['ok' => false, 'error' => 'Nom trop long (80 max).']);

  $st = $pdo->prepare("SELECT id FROM spaces WHERE name = ?");
  $st->execute([$name]);
  if ($st->fetch()) out(['ok' => false, 'error' => "Ce nom d'espace existe déjà. Choisis-en un autre."]);

  $data = json_encode(['people' => [], 'settings' => new stdClass()]);
  $st = $pdo->prepare("INSERT INTO spaces (name, pass_hash, data) VALUES (?, ?, ?)");
  $st->execute([$name, password_hash($password, PASSWORD_DEFAULT), $data]);

  $_SESSION['space'] = $name;
  out(['ok' => true, 'space' => $name, 'data' => json_decode($data, true)]);
}

// --- Se connecter à un espace existant ---
if ($action === 'login') {
  $name = trim($in['name'] ?? '');
  $password = (string)($in['password'] ?? '');
  $st = $pdo->prepare("SELECT pass_hash, data FROM spaces WHERE name = ?");
  $st->execute([$name]);
  $row = $st->fetch();
  if (!$row || !password_verify($password, $row['pass_hash'])) {
    out(['ok' => false, 'error' => 'Nom ou mot de passe incorrect.']);
  }
  $_SESSION['space'] = $name;
  out(['ok' => true, 'space' => $name, 'data' => json_decode($row['data'] ?: '{}', true)]);
}

// --- Enregistrer les données (nécessite d'être connecté = mot de passe fourni) ---
if ($action === 'save') {
  if (empty($_SESSION['space'])) out(['ok' => false, 'error' => 'Non connecté — mot de passe requis.']);
  $data = json_encode($in['data'] ?? new stdClass());
  $st = $pdo->prepare("UPDATE spaces SET data = ? WHERE name = ?");
  $st->execute([$data, $_SESSION['space']]);
  out(['ok' => true]);
}

// --- Vérifier le mot de passe de l'espace (déverrouillage du mode admin) ---
if ($action === 'verify') {
  if (empty($_SESSION['space'])) out(['ok' => false, 'error' => 'Non connecté.']);
  $password = (string)($in['password'] ?? '');
  $st = $pdo->prepare("SELECT pass_hash FROM spaces WHERE name = ?");
  $st->execute([$_SESSION['space']]);
  $row = $st->fetch();
  if ($row && password_verify($password, $row['pass_hash'])) out(['ok' => true]);
  out(['ok' => false, 'error' => 'Mot de passe incorrect.']);
}

// --- Changer le mot de passe de l'espace ---
if ($action === 'change_password') {
  if (empty($_SESSION['space'])) out(['ok' => false, 'error' => 'Non connecté.']);
  $new = (string)($in['password'] ?? '');
  if (mb_strlen($new) < 1) out(['ok' => false, 'error' => 'Nouveau mot de passe requis.']);
  $st = $pdo->prepare("UPDATE spaces SET pass_hash = ? WHERE name = ?");
  $st->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['space']]);
  out(['ok' => true]);
}

// --- Config globale de l'accueil (lecture publique, pour styler l'écran d'accueil) ---
if ($action === 'config_get') {
  $row = $pdo->query("SELECT data FROM app_config WHERE id = 1")->fetch();
  out(['ok' => true, 'data' => json_decode(($row['data'] ?? '') ?: '{}', true)]);
}

// --- Vérifier l'identifiant + le mot de passe admin (déverrouille l'admin de l'accueil) ---
if ($action === 'master_verify') {
  $user     = trim($in['username'] ?? '');
  $password = (string)($in['password'] ?? '');
  $row = $pdo->query("SELECT master_user, master_hash FROM app_config WHERE id = 1")->fetch();
  if ($row && strcasecmp($user, $row['master_user']) === 0 && password_verify($password, $row['master_hash'])) {
    $_SESSION['master'] = true;
    out(['ok' => true]);
  }
  out(['ok' => false, 'error' => 'Identifiant ou mot de passe incorrect.']);
}

// --- Enregistrer la config globale (nécessite le mot de passe maître) ---
if ($action === 'config_save') {
  if (empty($_SESSION['master'])) out(['ok' => false, 'error' => 'Non autorisé — mot de passe maître requis.']);
  $data = json_encode($in['data'] ?? new stdClass());
  $st = $pdo->prepare("UPDATE app_config SET data = ? WHERE id = 1");
  $st->execute([$data]);
  out(['ok' => true]);
}

// --- Changer l'identifiant et/ou le mot de passe admin ---
if ($action === 'master_change') {
  if (empty($_SESSION['master'])) out(['ok' => false, 'error' => 'Non autorisé.']);
  $newUser = trim($in['username'] ?? '');
  $new     = (string)($in['password'] ?? '');
  if ($newUser === '' && mb_strlen($new) < 1) out(['ok' => false, 'error' => 'Rien à changer.']);
  if ($newUser !== '') {
    $st = $pdo->prepare("UPDATE app_config SET master_user = ? WHERE id = 1");
    $st->execute([mb_substr($newUser, 0, 80)]);
  }
  if (mb_strlen($new) >= 1) {
    $st = $pdo->prepare("UPDATE app_config SET master_hash = ? WHERE id = 1");
    $st->execute([password_hash($new, PASSWORD_DEFAULT)]);
  }
  out(['ok' => true]);
}

// --- ADMIN : connexion via les identifiants MySQL (dbname / utilisateur / mot de passe) ---
// On accorde l'accès admin si ces identifiants permettent de se connecter à MySQL.
// La base est CRÉÉE automatiquement si elle n'existe pas encore.
if ($action === 'admin_db_login') {
  $dbname = trim($in['dbname'] ?? '');
  $user   = trim($in['username'] ?? '');
  $pass   = (string)($in['password'] ?? '');
  if ($dbname === '') $dbname = $DB_NAME;
  try {
    // 1) On se connecte au SERVEUR MySQL (sans base précise) → valide utilisateur/mot de passe
    $srv = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    // 2) On crée la base si elle n'existe pas
    $safe = str_replace('`', '', $dbname);
    $srv->exec("CREATE DATABASE IF NOT EXISTS `$safe` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $_SESSION['master'] = true;               // accès admin accordé
    out(['ok' => true]);
  } catch (Exception $e) {
    out(['ok' => false, 'error' => "Connexion refusée. Vérifie l'utilisateur et le mot de passe MySQL. (MySQL doit être démarré dans XAMPP.)"]);
  }
}

// --- ADMIN : lister TOUS les espaces (nécessite le mot de passe maître) ---
if ($action === 'admin_list') {
  if (empty($_SESSION['master'])) out(['ok' => false, 'error' => 'Non autorisé — connecte-toi en admin.']);
  $rows = $pdo->query("SELECT name, data, updated_at FROM spaces ORDER BY name ASC")->fetchAll();
  $spaces = [];
  foreach ($rows as $r) {
    $d = json_decode($r['data'] ?: '{}', true);
    $count = (isset($d['people']) && is_array($d['people'])) ? count($d['people']) : 0;
    $spaces[] = ['name' => $r['name'], 'people' => $count, 'updated_at' => $r['updated_at']];
  }
  out(['ok' => true, 'spaces' => $spaces]);
}

// --- ADMIN : entrer dans un espace SANS son mot de passe ---
if ($action === 'admin_enter') {
  if (empty($_SESSION['master'])) out(['ok' => false, 'error' => 'Non autorisé.']);
  $name = trim($in['name'] ?? '');
  $st = $pdo->prepare("SELECT data FROM spaces WHERE name = ?");
  $st->execute([$name]);
  $row = $st->fetch();
  if (!$row) out(['ok' => false, 'error' => 'Espace introuvable.']);
  $_SESSION['space'] = $name;
  out(['ok' => true, 'space' => $name, 'data' => json_decode($row['data'] ?: '{}', true)]);
}

// --- ADMIN : supprimer un espace ---
if ($action === 'admin_delete') {
  if (empty($_SESSION['master'])) out(['ok' => false, 'error' => 'Non autorisé.']);
  $name = trim($in['name'] ?? '');
  $st = $pdo->prepare("DELETE FROM spaces WHERE name = ?");
  $st->execute([$name]);
  // Si on était connecté à cet espace, on le quitte
  if (($_SESSION['space'] ?? null) === $name) unset($_SESSION['space']);
  out(['ok' => true]);
}

// --- Se déconnecter ---
if ($action === 'logout') {
  $_SESSION = [];
  session_destroy();
  out(['ok' => true]);
}

out(['ok' => false, 'error' => 'Action inconnue.']);
