<?php
/**
 * Assistant d'installation.
 * On demande UNIQUEMENT les paramètres de la base de données.
 * On teste la connexion, on crée la base et les tables, on écrit la configuration,
 * puis on crée automatiquement le compte de connexion en réutilisant
 * l'identifiant + le mot de passe de la base de données (aucun compte séparé à saisir).
 * Une fois installée, l'application redirige vers la connexion.
 */

require __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Accès à la configuration :
 *  - jamais installé           -> installation normale ;
 *  - installé mais base KO     -> reconfiguration (avec l'erreur) ;
 *  - installé + base OK         -> on ne reconfigure que si ?reconfigure=1,
 *                                  sinon on renvoie vers l'application.
 */
$reconfigure = false;
$dbError     = '';
$existing    = null;
if (is_installed()) {
    try { $existing = load_config(); } catch (Throwable $e) {}
    $health = db_can_connect();
    if ($health['ok'] && !isset($_GET['reconfigure'])) {
        // Config valide : on entre dans l'application.
        header('Location: index.php');
        exit;
    }
    // Sinon on (re)montre l'assistant. IMPORTANT : on NE supprime PAS la config
    // existante — une panne passagère de la base ne doit jamais détruire des
    // paramètres valides. On affiche l'erreur pour correction éventuelle.
    $reconfigure = true;
    if (!$health['ok']) {
        $dbError = $health['error'] ?: ($_SESSION['db_error'] ?? '');
    }
}

$errors  = [];
$success = false;

// Valeurs par défaut (XAMPP). Le port n'est pas demandé : MySQL/XAMPP utilise 3306.
const DB_PORT = 3306;
// Pré-remplissage : valeurs soumises > config existante > valeurs par défaut.
// (le mot de passe n'est jamais pré-rempli, pour la sécurité)
$values = [
    'host'   => $_POST['host']   ?? ($existing['host']   ?? '127.0.0.1'),
    'dbname' => $_POST['dbname'] ?? ($existing['dbname'] ?? 'cv_luvumbu'),
    'user'   => $_POST['user']   ?? ($existing['user']   ?? 'root'),
    'pass'   => $_POST['pass']   ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host      = trim($values['host']);
    $port      = DB_PORT;
    $dbname    = trim($values['dbname']);
    $user      = trim($values['user']);
    // On retire les espaces accidentels autour du mot de passe (cause fréquente
    // d'« Access denied » lors d'un copier-coller depuis hPanel).
    $pass      = trim((string) $values['pass']);

    // --- Validation des paramètres de base ---
    if ($host === '')                                  $errors[] = "L'hôte est obligatoire.";
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbname))     $errors[] = "Nom de base invalide (lettres, chiffres et _ uniquement).";
    if ($user === '')                                  $errors[] = "L'utilisateur de la base est obligatoire.";
    // Le mot de passe peut être vide (XAMPP en local) : il devient aussi le
    // mot de passe de connexion au site.

    if (!$errors) {
        try {
            // 1) Connexion au serveur MySQL (sans base précise).
            //    On essaie l'hôte saisi, puis l'autre variante (localhost <-> 127.0.0.1) :
            //    le compte MySQL peut n'être autorisé que pour l'un des deux.
            $hostsToTry = [$host];
            if ($host === 'localhost')     { $hostsToTry[] = '127.0.0.1'; }
            elseif ($host === '127.0.0.1') { $hostsToTry[] = 'localhost'; }

            $pdo = null;
            $connErr = null;
            foreach ($hostsToTry as $h) {
                try {
                    $pdo = new PDO(
                        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $h, $port),
                        $user, $pass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6]
                    );
                    $host = $h; // on retient l'hôte qui fonctionne
                    break;
                } catch (PDOException $e) {
                    $connErr = $e;
                }
            }
            if ($pdo === null) {
                throw $connErr; // message d'erreur géré plus bas
            }

            // 2) Création de la base si nécessaire.
            //    En local (XAMPP) l'utilisateur peut créer la base.
            //    Sur un hébergement mutualisé, la base est déjà créée et
            //    l'utilisateur n'a pas le droit CREATE DATABASE : on ignore
            //    alors l'échec et on se contente d'utiliser la base existante.
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`
                            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                // Pas de privilège de création : la base doit déjà exister.
            }
            // Vrai test : la base est-elle réellement utilisable ?
            $pdo->exec("USE `$dbname`");

            // 3) Table des utilisateurs — on NETTOIE d'abord, on crée ensuite.
            //    Si « users » est absente OU incompatible (sans colonne
            //    username), la base contient des restes de tentatives
            //    précédentes : parfois des tables qui gardent une clé étrangère
            //    ORPHELINE vers « users », ce qui fait échouer sa création avec
            //    l'erreur errno 150. On supprime donc TOUTES les tables
            //    résiduelles AVANT de créer le schéma propre.
            //    Une table « users » déjà correcte est conservée (réinstallation).
            $usersSql = "CREATE TABLE IF NOT EXISTS users (
                id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username      VARCHAR(50)  NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $usersExists = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
            $usersOk     = $usersExists
                && (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'username'")->fetch();
            if (!$usersOk) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
                    $pdo->exec("DROP TABLE IF EXISTS `$t`");
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            }
            $pdo->exec($usersSql);

            // 4) Table des clés API.
            $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id      INT UNSIGNED NOT NULL,
                label        VARCHAR(100) NOT NULL,
                scopes       VARCHAR(255) NOT NULL DEFAULT '',
                key_prefix   VARCHAR(12)  NOT NULL,
                key_hash     CHAR(64)     NOT NULL,
                created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME     NULL,
                revoked_at   DATETIME     NULL,
                UNIQUE KEY uniq_hash (key_hash),
                KEY idx_user (user_id),
                CONSTRAINT fk_api_keys_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // 5) Compte de connexion — AUCUNE saisie séparée.
            //    On réutilise les identifiants de la base : le login = utilisateur
            //    de la base, le mot de passe = mot de passe de la base.
            //    On (re)définit ce compte à chaque enregistrement pour que la
            //    connexion au site reste toujours alignée sur la config saisie.
            //    (username est UNIQUE -> insertion ou mise à jour du mot de passe.)
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, password_hash) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)"
            );
            $stmt->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);

            // 6) Écriture du fichier de configuration (si aucune erreur).
            if (!$errors) {
                $cfg = [
                    'host'   => $host,
                    'port'   => $port,
                    'dbname' => $dbname,
                    'user'   => $user,
                    'pass'   => $pass,
                ];
                $php = "<?php\n// Fichier généré par l'assistant d'installation. Ne pas committer en clair.\nreturn "
                     . var_export($cfg, true) . ";\n";

                if (file_put_contents(config_path(), $php) === false) {
                    throw new RuntimeException("Impossible d'écrire le fichier de configuration (droits du dossier config/).");
                }

                unset($_SESSION['db_error']);
                $success = true;
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '1045')) {
                $errors[] = "Identifiants refusés par MySQL : l'utilisateur ou le mot de passe de la base "
                          . "est incorrect. Vérifiez le mot de passe de la BASE DE DONNÉES (pas celui de hPanel) "
                          . "dans hPanel → Bases de données MySQL, ou réinitialisez-le, puis ressaisissez-le sans espace.";
            } elseif (str_contains($msg, '1049') || str_contains($msg, 'Unknown database')) {
                $errors[] = "La base de données « " . htmlspecialchars($dbname) . " » n'existe pas. "
                          . "Vérifiez son nom exact dans hPanel → Bases de données MySQL.";
            } elseif (str_contains($msg, '2002') || str_contains($msg, 'refused') || str_contains($msg, 'getaddrinfo')) {
                $errors[] = "Serveur de base injoignable : vérifiez l'hôte (essayez « localhost »).";
            } else {
                $errors[] = "Connexion à la base impossible : " . $msg;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <?php if ($success): ?><meta http-equiv="refresh" content="2;url=login.php"><?php endif; ?>
    <title>Installation — CV Luvumbu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<div class="brand-logo">CV <span>Luvumbu</span></div>
<div class="card">
    <h1><?= $reconfigure ? 'Reconfiguration' : 'Configuration' ?></h1>
    <p class="subtitle">Paramètres de la base de données</p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✓ Configuration enregistrée. La base est opérationnelle.<br>
            Connectez-vous avec votre identifiant et mot de passe de base de données.<br>
            Redirection vers la connexion…
        </div>
        <a class="btn" href="login.php">Aller à la connexion →</a>
    <?php else: ?>

        <?php if ($dbError): ?>
            <div class="alert alert-error">
                ⚠ Connexion à la base impossible avec la configuration actuelle.<br>
                Corrigez les paramètres ci-dessous.
                <br><small><?= htmlspecialchars($dbError) ?></small>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="off">
            <fieldset>
                <legend>Base de données</legend>
                <label>Hôte
                    <input name="host" value="<?= htmlspecialchars($values['host']) ?>" required>
                </label>
                <label>Nom de la base
                    <input name="dbname" value="<?= htmlspecialchars($values['dbname']) ?>" required>
                </label>
                <label>Utilisateur
                    <input name="user" value="<?= htmlspecialchars($values['user']) ?>" required>
                </label>
                <label>Mot de passe <span class="muted">(laisser vide si XAMPP local sans mot de passe)</span>
                    <input name="pass" type="password" value="<?= htmlspecialchars($values['pass']) ?>">
                </label>
            </fieldset>

            <p class="muted">
                Vous vous connecterez ensuite au site avec ce même
                <strong>utilisateur</strong> et ce <strong>mot de passe</strong> de base de données.
            </p>

            <button class="btn" type="submit"><?= $reconfigure ? 'Enregistrer la configuration' : 'Installer et continuer' ?></button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
