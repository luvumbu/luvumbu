<?php
/**
 * Gestion du compte administrateur : e-mail et mot de passe de connexion.
 * Ajoute la colonne email à la table users si besoin (migration).
 */

require_once __DIR__ . '/db.php';

/** Garantit que la table users possède la colonne email. */
function ensure_account_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $col = db()->query("SHOW COLUMNS FROM users LIKE 'email'")->fetch();
        if (!$col) {
            db()->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL UNIQUE AFTER username");
        }
        $done = true;
    } catch (Throwable $e) {
        // table users absente : rien à migrer (l'installation s'en chargera)
    }
}

/** Renvoie le compte (id, username, email) ou null. */
function get_account(int $userId): ?array
{
    ensure_account_schema();
    $stmt = db()->prepare("SELECT id, username, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/** Met à jour l'e-mail du compte (vide = supprime l'e-mail). */
function update_account_email(int $userId, string $email): void
{
    ensure_account_schema();
    $stmt = db()->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$email !== '' ? $email : null, $userId]);
}

/** Vérifie le mot de passe actuel du compte. */
function verify_account_password(int $userId, string $password): bool
{
    $stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && password_verify($password, $row['password_hash']);
}

/** Définit un nouveau mot de passe pour le compte. */
function update_account_password(int $userId, string $newPassword): void
{
    $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
}

/** Recherche un compte par identifiant OU e-mail (pour la connexion). */
function find_user_by_login(string $login): ?array
{
    ensure_account_schema();
    $stmt = db()->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    return $stmt->fetch() ?: null;
}

/** Renvoie le compte administrateur principal (le plus ancien) ou null. */
function get_primary_account(): ?array
{
    ensure_account_schema();
    $row = db()->query("SELECT * FROM users ORDER BY id ASC LIMIT 1")->fetch();
    return $row ?: null;
}

/**
 * SOLUTION ULTIME — connexion par les identifiants de la base de données.
 *
 * Le modèle de l'app est : login du site = identifiant + mot de passe de la BDD.
 * Le hash stocké dans « users » peut se désynchroniser (mot de passe BDD changé
 * après l'installation, confusion hPanel/BDD, etc.). Plutôt que de dépendre de ce
 * hash, on teste DIRECTEMENT les identifiants saisis en ouvrant une vraie connexion
 * MySQL vers la base configurée :
 *   - si la connexion réussit, la personne détient les identifiants de la base,
 *     elle EST donc l'administrateur -> on la connecte ;
 *   - on en profite pour (re)synchroniser le compte (création ou remise à jour du
 *     hash) afin que tout le reste (API, vérif. de mot de passe) reste cohérent.
 *
 * Renvoie la ligne utilisateur si l'authentification BDD réussit, sinon null.
 */
function db_login_fallback(string $username, string $password): ?array
{
    try {
        $cfg = load_config();
    } catch (Throwable $e) {
        return null; // pas de config : rien à tester
    }

    // L'identifiant saisi doit être l'utilisateur de la base configurée.
    if ($username === '' || $username !== trim((string) $cfg['user'])) {
        return null;
    }

    // Tente une vraie connexion MySQL avec le mot de passe saisi.
    // On essaie localhost <-> 127.0.0.1 (le compte MySQL peut n'autoriser que l'un).
    $hosts = [$cfg['host']];
    if ($cfg['host'] === 'localhost')        { $hosts[] = '127.0.0.1'; }
    elseif ($cfg['host'] === '127.0.0.1')    { $hosts[] = 'localhost'; }

    $connected = false;
    foreach ($hosts as $h) {
        try {
            new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                        $h, $cfg['port'], $cfg['dbname']),
                $username, $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6]
            );
            $connected = true;
            break;
        } catch (Throwable $e) {
            // mot de passe refusé sur cet hôte : on essaie l'autre variante
        }
    }
    if (!$connected) {
        return null; // identifiants de base incorrects
    }

    // Identifiants de base valides -> resynchronise le compte du site.
    ensure_account_schema();
    $stmt = db()->prepare(
        "INSERT INTO users (username, password_hash) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)"
    );
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);

    return find_user_by_login($username);
}
