<?php
/**
 * API — Compte à rebours anniversaire
 * Gère les "espaces" : chacun peut créer le sien, protégé par mot de passe.
 * La base et la table sont créées automatiquement au premier appel.
 */
header('Content-Type: application/json; charset=utf-8');

// ⬇️ BASE DE DONNÉES.
//    EN LOCAL (XAMPP) : laisser tel quel (root / mot de passe vide).
//    EN LIGNE : remplace par les identifiants MySQL de ton hébergeur
//    (panneau de contrôle → MySQL / Bases de données). Souvent DB_HOST = 'localhost'.
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';                 // XAMPP : root sans mot de passe par défaut
$DB_NAME = 'anniversaire_app';

// Si l'assistant d'installation a écrit un config.php, il remplace les valeurs ci-dessus.
$CFG_FILE = __DIR__ . '/config.php';
if (is_file($CFG_FILE)) {
  $cfg = include $CFG_FILE;
  if (is_array($cfg)) {
    $DB_HOST = $cfg['host'] ?? $DB_HOST;
    $DB_USER = $cfg['user'] ?? $DB_USER;
    $DB_PASS = $cfg['pass'] ?? $DB_PASS;
    $DB_NAME = $cfg['name'] ?? $DB_NAME;
  }
}

// ⬇️ CONNEXION GOOGLE — colle ici ton "Client ID" Google (…apps.googleusercontent.com).
//    Tant que c'est vide, le bouton Google est simplement masqué.
//    Le même Client ID doit aussi être renseigné dans index.html (const GOOGLE_CLIENT_ID).
$GOOGLE_CLIENT_ID = '878381681024-mc6bg84ftpig7eee4h26treosike77b7.apps.googleusercontent.com';

// ⬇️ SÉCURITÉ — connexion admin via les identifiants MySQL.
//    DANGEREUX en ligne (surtout si MySQL = root/mot de passe vide) : mets false en production
//    et utilise l'admin par mot de passe maître (identifiant/mot de passe) à la place.
$ALLOW_DB_ADMIN_LOGIN = true;

function out($a) { echo json_encode($a); exit; }

// Un espace « Google » est identifié par un e-mail (contient « @ ») → accès sans mot de passe.
function is_google_space($name) { return strpos((string)$name, '@') !== false; }

// Décode proprement la liste des e-mails autorisés d'un espace.
function shared_emails($raw) {
  $arr = json_decode($raw ?: '[]', true);
  return is_array($arr) ? array_values(array_filter(array_map('strval', $arr))) : [];
}

// Liste tous les espaces auxquels un e-mail a accès : le sien (name == email) + ceux partagés avec lui.
function accessible_spaces($pdo, $email) {
  $out = [];
  $rows = $pdo->query("SELECT name, shared_with FROM spaces")->fetchAll();
  foreach ($rows as $r) {
    if (strcasecmp($r['name'], $email) === 0) {
      $out[] = ['name' => $r['name'], 'role' => 'owner'];
      continue;
    }
    foreach (shared_emails($r['shared_with']) as $e) {
      if (strcasecmp($e, $email) === 0) { $out[] = ['name' => $r['name'], 'role' => 'shared']; break; }
    }
  }
  return $out;
}

// --- Lecture de la requête (tôt, pour gérer l'assistant d'installation même sans base) ---
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';

// Teste une connexion MySQL et renvoie [ok(bool), message].
function db_can_connect($host, $user, $pass, $name) {
  try {
    new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return [true, ''];
  } catch (Exception $e) { return [false, $e->getMessage()]; }
}

// --- ASSISTANT D'INSTALLATION : enregistrer (ou écraser) la configuration de la base ---
if ($action === 'setup_save') {
  // Verrou anti-sabotage : si la config actuelle se connecte déjà, on refuse la reconfiguration.
  list($ok0) = db_can_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  if ($ok0) out(['ok' => false, 'error' => "Déjà configuré et fonctionnel — reconfiguration bloquée par sécurité."]);

  $h = trim($in['host'] ?? ''); $n = trim($in['name'] ?? '');
  $u = trim($in['user'] ?? ''); $p = (string)($in['pass'] ?? '');
  if ($h === '' || $n === '' || $u === '') out(['ok' => false, 'error' => 'Hôte, base et utilisateur sont requis.']);

  list($ok, $msg) = db_can_connect($h, $u, $p, $n);
  if (!$ok) out(['ok' => false, 'error' => "Connexion refusée : $msg"]);

  // Écrit (ou ÉCRASE l'ancienne) config.php avec les nouveaux identifiants.
  $arr = ['host' => $h, 'user' => $u, 'pass' => $p, 'name' => $n];
  $php = "<?php\n// Configuration générée par l'assistant d'installation. Modifiable à la main.\nreturn " . var_export($arr, true) . ";\n";
  if (@file_put_contents($CFG_FILE, $php) === false) {
    out(['ok' => false, 'error' => "Connexion testée OK, mais impossible d'écrire config.php (dossier non accessible en écriture). Édite api.php à la main."]);
  }
  out(['ok' => true]);
}

// --- Connexion à la base : compatible XAMPP local ET hébergement mutualisé ---
$pdoOpts = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
  // Cas hébergement : la base existe déjà (créée dans le panneau) → on s'y connecte directement.
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $pdoOpts);
} catch (Exception $e1) {
  // La base n'existe pas encore ? On tente de la créer (cas XAMPP local, droits suffisants).
  try {
    $srv = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, $pdoOpts);
    $srv->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $pdoOpts);
  } catch (Exception $e2) {
    error_log('DB connect error: ' . $e2->getMessage());
    // Base non configurée / injoignable → on demande à l'utilisateur de lancer l'assistant.
    out(['ok' => false, 'setup' => true, 'error' => "Base de données non configurée."]);
  }
}

// --- Création / mise à jour des tables (l'utilisateur a les droits sur SA base) ---
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS spaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    data LONGTEXT,
    shared_with LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Migration : ajoute la colonne de partage si la table existait déjà sans elle (MariaDB)
  $pdo->exec("ALTER TABLE spaces ADD COLUMN IF NOT EXISTS shared_with LONGTEXT");

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
  // On ne renvoie pas le détail de l'erreur au navigateur (fuite d'infos).
  error_log('DB init error: ' . $e->getMessage());
  out(['ok' => false, 'error' => "Initialisation de la base impossible. Vérifie que l'utilisateur MySQL a les droits sur la base."]);
}

// Sécurité des cookies de session : HttpOnly, SameSite, et Secure en HTTPS.
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
       || (($_SERVER['SERVER_PORT'] ?? '') == 443)
       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => $secure,
]);
session_start();
// ($in et $action ont déjà été lus plus haut, avant la connexion à la base.)

// --- Est-on déjà connecté à un espace ? (au chargement de la page) ---
if ($action === 'me') {
  if (!empty($_SESSION['space'])) {
    $st = $pdo->prepare("SELECT data FROM spaces WHERE name = ?");
    $st->execute([$_SESSION['space']]);
    $row = $st->fetch();
    if ($row) {
      $email = $_SESSION['google_email'] ?? '';
      out([
        'ok'     => true,
        'space'  => $_SESSION['space'],
        'data'   => json_decode($row['data'] ?: '{}', true),
        'google' => is_google_space($_SESSION['space']),
        'spaces' => $email ? accessible_spaces($pdo, $email) : [],
      ]);
    }
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
  if (!$ALLOW_DB_ADMIN_LOGIN) {
    out(['ok' => false, 'error' => "Connexion admin par MySQL désactivée. Utilise l'identifiant et le mot de passe maître."]);
  }
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

// --- Se connecter avec Google (Google Identity Services) ---
// Le navigateur envoie le "credential" (jeton JWT signé par Google). On le vérifie
// auprès de Google, puis on ouvre/crée un espace personnel lié à l'e-mail du compte.
if ($action === 'google_login') {
  if ($GOOGLE_CLIENT_ID === '') {
    out(['ok' => false, 'error' => "Connexion Google non configurée (renseigne \$GOOGLE_CLIENT_ID dans api.php)."]);
  }
  $credential = (string)($in['credential'] ?? '');
  if ($credential === '') out(['ok' => false, 'error' => 'Jeton Google manquant.']);

  // Vérification du jeton auprès de Google (validation de la signature côté Google).
  $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
  $payload = null;
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp !== false) $payload = json_decode($resp, true);
  } elseif (ini_get('allow_url_fopen')) {
    $resp = @file_get_contents($url);
    if ($resp !== false) $payload = json_decode($resp, true);
  }
  if (!is_array($payload) || empty($payload['sub'])) {
    out(['ok' => false, 'error' => "Impossible de vérifier le compte Google (le serveur doit pouvoir joindre Google en HTTPS)."]);
  }

  // Contrôles de sécurité : destinataire, émetteur, expiration, e-mail vérifié.
  if (($payload['aud'] ?? '') !== $GOOGLE_CLIENT_ID) out(['ok' => false, 'error' => 'Jeton Google invalide (destinataire).']);
  $iss = $payload['iss'] ?? '';
  if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
    out(['ok' => false, 'error' => 'Jeton Google invalide (émetteur).']);
  }
  if (isset($payload['exp']) && (int)$payload['exp'] < time()) out(['ok' => false, 'error' => 'Jeton Google expiré, réessaie.']);
  $verified = $payload['email_verified'] ?? 'true';
  if ($verified !== 'true' && $verified !== true) out(['ok' => false, 'error' => 'E-mail Google non vérifié.']);

  $email = strtolower(trim($payload['email'] ?? ''));
  if ($email === '') out(['ok' => false, 'error' => 'E-mail Google absent.']);
  $email = mb_substr($email, 0, 80);
  $_SESSION['google_email'] = $email;   // mémorise l'e-mail vérifié (pour changer d'espace / partage)

  // Espaces accessibles : le sien + ceux partagés avec lui.
  $spaces = accessible_spaces($pdo, $email);

  // Aucun espace ? On crée l'espace personnel (mot de passe inutilisable : accès uniquement via Google).
  if (empty($spaces)) {
    $data = json_encode(['people' => [], 'settings' => new stdClass()]);
    $randomHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO spaces (name, pass_hash, data) VALUES (?, ?, ?)");
    $ins->execute([$email, $randomHash, $data]);
    $spaces = [['name' => $email, 'role' => 'owner']];
  }

  // On entre dans le premier espace accessible (le sien en priorité s'il existe).
  usort($spaces, fn($a, $b) => ($a['role'] === 'owner' ? 0 : 1) - ($b['role'] === 'owner' ? 0 : 1));
  $enter = $spaces[0]['name'];
  $st = $pdo->prepare("SELECT data FROM spaces WHERE name = ?");
  $st->execute([$enter]);
  $row = $st->fetch();
  $dataArr = json_decode(($row['data'] ?? '') ?: '{}', true);

  $_SESSION['space'] = $enter;
  out(['ok' => true, 'space' => $enter, 'data' => $dataArr, 'google' => true, 'spaces' => $spaces]);
}

// --- Changer d'espace (parmi ceux accessibles au compte Google connecté) ---
if ($action === 'switch_space') {
  $email = $_SESSION['google_email'] ?? '';
  if ($email === '') out(['ok' => false, 'error' => 'Non connecté avec Google.']);
  $target = trim($in['name'] ?? '');
  $allowed = false;
  foreach (accessible_spaces($pdo, $email) as $s) {
    if (strcasecmp($s['name'], $target) === 0) { $allowed = true; $target = $s['name']; break; }
  }
  if (!$allowed) out(['ok' => false, 'error' => "Accès non autorisé à cet espace."]);
  $st = $pdo->prepare("SELECT data FROM spaces WHERE name = ?");
  $st->execute([$target]);
  $row = $st->fetch();
  if (!$row) out(['ok' => false, 'error' => 'Espace introuvable.']);
  $_SESSION['space'] = $target;
  out([
    'ok' => true, 'space' => $target,
    'data' => json_decode($row['data'] ?: '{}', true),
    'google' => is_google_space($target),
    'spaces' => accessible_spaces($pdo, $email),
  ]);
}

// --- Partage : lister les e-mails autorisés sur l'espace courant ---
if ($action === 'share_list') {
  if (empty($_SESSION['space'])) out(['ok' => false, 'error' => 'Non connecté.']);
  $st = $pdo->prepare("SELECT shared_with FROM spaces WHERE name = ?");
  $st->execute([$_SESSION['space']]);
  $row = $st->fetch();
  out(['ok' => true, 'emails' => shared_emails($row['shared_with'] ?? '')]);
}

// --- Partage : ajouter un e-mail autorisé ---
if ($action === 'share_add') {
  if (empty($_SESSION['space'])) out(['ok' => false, 'error' => 'Non connecté.']);
  $email = strtolower(trim($in['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['ok' => false, 'error' => 'Adresse e-mail invalide.']);
  if (strcasecmp($email, $_SESSION['space']) === 0) out(['ok' => false, 'error' => "C'est déjà le propriétaire de l'espace."]);
  $st = $pdo->prepare("SELECT shared_with FROM spaces WHERE name = ?");
  $st->execute([$_SESSION['space']]);
  $row = $st->fetch();
  $emails = shared_emails($row['shared_with'] ?? '');
  foreach ($emails as $e) if (strcasecmp($e, $email) === 0) out(['ok' => true, 'emails' => $emails]); // déjà présent
  $emails[] = $email;
  $st = $pdo->prepare("UPDATE spaces SET shared_with = ? WHERE name = ?");
  $st->execute([json_encode(array_values($emails)), $_SESSION['space']]);
  out(['ok' => true, 'emails' => $emails]);
}

// --- Partage : retirer un e-mail autorisé ---
if ($action === 'share_remove') {
  if (empty($_SESSION['space'])) out(['ok' => false, 'error' => 'Non connecté.']);
  $email = strtolower(trim($in['email'] ?? ''));
  $st = $pdo->prepare("SELECT shared_with FROM spaces WHERE name = ?");
  $st->execute([$_SESSION['space']]);
  $row = $st->fetch();
  $emails = array_values(array_filter(shared_emails($row['shared_with'] ?? ''), fn($e) => strcasecmp($e, $email) !== 0));
  $st = $pdo->prepare("UPDATE spaces SET shared_with = ? WHERE name = ?");
  $st->execute([json_encode($emails), $_SESSION['space']]);
  out(['ok' => true, 'emails' => $emails]);
}

// --- Se déconnecter ---
if ($action === 'logout') {
  $_SESSION = [];
  session_destroy();
  out(['ok' => true]);
}

out(['ok' => false, 'error' => 'Action inconnue.']);
