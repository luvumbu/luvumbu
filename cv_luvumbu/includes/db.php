<?php
/**
 * Connexion à la base de données via PDO.
 * Lit les paramètres écrits par l'assistant d'installation (config/config.php).
 */

function config_path(): string
{
    return __DIR__ . '/../config/config.php';
}

/** L'application est-elle déjà configurée ? */
function is_installed(): bool
{
    return file_exists(config_path());
}

/**
 * URL de base de l'application (schéma + hôte + dossier), sans slash final.
 * Sert à construire des liens absolus (e-mail de réinitialisation, redirection OAuth).
 */
function app_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $dir;
}

/** Charge le tableau de configuration de la base de données. */
function load_config(): array
{
    if (!is_installed()) {
        throw new RuntimeException("Application non configurée.");
    }
    return require config_path();
}

/**
 * Vérifie que la base configurée est joignable (paramètres corrects, serveur up).
 * Renvoie ['ok' => bool, 'error' => string].
 */
function db_can_connect(): array
{
    if (!is_installed()) {
        return ['ok' => false, 'error' => "Application non configurée."];
    }
    try {
        $cfg = load_config();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'], $cfg['port'], $cfg['dbname']
        );
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 4,
        ]);
        $pdo->query('SELECT 1');
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Garantit que la table users possède la colonne must_change_password
 * (migration pour les installations antérieures).
 */
function ensure_users_schema(): void
{
    try {
        $col = db()->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch();
        if (!$col) {
            db()->exec("ALTER TABLE users
                        ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {
        // La table n'existe pas encore : rien à migrer.
    }
}

/** Retourne une instance PDO connectée à la base configurée. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = load_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['dbname']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
