<?php
// Affiche les erreurs PHP pendant l'installation pour aider au diagnostic.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

$configFile = __DIR__ . '/config/config.php';
$schemaFile = __DIR__ . '/sql/schema.sql';

if (file_exists($configFile)) {
    header('Location: index.php');
    exit;
}

$errors = [];
$values = [
    'host'            => $_POST['host']            ?? 'localhost',
    'dbname'          => $_POST['dbname']          ?? 'blog',
    'user'            => $_POST['user']            ?? 'root',
    'password'        => $_POST['password']        ?? '',
    'admin_nom'       => $_POST['admin_nom']       ?? '',
    'admin_prenom'    => $_POST['admin_prenom']    ?? '',
    'admin_email'     => $_POST['admin_email']     ?? '',
    'admin_password'  => $_POST['admin_password']  ?? '',
    'site_name'       => $_POST['site_name']       ?? 'Mon Blog',
    'tagline'         => $_POST['tagline']         ?? 'Le blog',
    'header_baseline' => $_POST['header_baseline'] ?? 'Bienvenue sur le blog',
    'about_text'      => $_POST['about_text']      ?? 'Un blog ouvert où chaque membre peut publier ses articles et échanger en commentaires.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['host', 'dbname', 'user'] as $field) {
        if (trim($values[$field]) === '') {
            $errors[] = "Le champ '$field' est obligatoire.";
        }
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $values['dbname'])) {
        $errors[] = "Le nom de la base doit contenir uniquement lettres, chiffres ou underscore.";
    }
    if (trim($values['admin_nom']) === '' || trim($values['admin_prenom']) === '') {
        $errors[] = "Nom et prénom de l'admin obligatoires.";
    }
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email admin invalide.";
    }
    if (strlen($values['admin_password']) < 6) {
        $errors[] = "Le mot de passe admin doit faire au moins 6 caractères.";
    }
    if (trim($values['site_name']) === '') {
        $errors[] = "Le nom du site est obligatoire.";
    }

    if (empty($errors)) {
        try {
            // Connexion sans dbname pour lister les bases accessibles, puis on basculera dessus.
            // (Compatible Hostinger / shared hosting où l'utilisateur n'a accès qu'à ses propres bases.)
            try {
                $pdo = new PDO(
                    'mysql:host=' . $values['host'] . ';charset=utf8mb4',
                    $values['user'],
                    $values['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                throw new Exception(
                    "Connexion MySQL impossible : " . $e->getMessage() .
                    " — Vérifie l'hôte, l'utilisateur et le mot de passe."
                );
            }

            // Liste des bases accessibles par cet utilisateur
            $accessibleDbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
            $accessibleDbs = array_filter($accessibleDbs, function ($d) {
                return !in_array(strtolower($d), ['information_schema', 'performance_schema', 'mysql', 'sys'], true);
            });

            // Match exact ou tentative auto (si une seule base accessible OU si on trouve un suffixe correspondant)
            $finalDb = null;
            if (in_array($values['dbname'], $accessibleDbs, true)) {
                $finalDb = $values['dbname'];
            } elseif (count($accessibleDbs) === 1) {
                // Une seule base accessible : on la prend automatiquement
                $finalDb = reset($accessibleDbs);
            } else {
                // On cherche une base dont le NOM SE TERMINE par ce que l'utilisateur a tapé
                // (ex: l'utilisateur tape 'blog' et il existe 'u123456789_blog')
                foreach ($accessibleDbs as $d) {
                    if (preg_match('/(^|_)' . preg_quote($values['dbname'], '/') . '$/i', $d)) {
                        $finalDb = $d;
                        break;
                    }
                }
            }

            if ($finalDb === null) {
                $list = implode(', ', $accessibleDbs);
                throw new Exception(
                    "La base '{$values['dbname']}' n'existe pas. " .
                    "Bases accessibles avec cet utilisateur : " . ($list ?: '(aucune)') .
                    ". Copie-colle le nom EXACT d'une base dans le champ 'Nom de la base'."
                );
            }

            // On bascule sur la bonne base et on met à jour la valeur pour l'écrire dans config.php ensuite
            $pdo->exec("USE `$finalDb`");
            $values['dbname'] = $finalDb;

            $sql = file_get_contents($schemaFile);
            if ($sql === false) {
                throw new Exception('Impossible de lire sql/schema.sql');
            }
            $pdo->exec($sql);

            // Compte admin (créé OU mis à jour si l'email existe déjà)
            $hash = password_hash($values['admin_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (nom, prenom, email, password_hash, is_admin) VALUES (?, ?, ?, ?, 1)
                                    ON DUPLICATE KEY UPDATE
                                        nom = VALUES(nom),
                                        prenom = VALUES(prenom),
                                        password_hash = VALUES(password_hash),
                                        is_admin = 1');
            $stmt->execute([
                trim($values['admin_nom']),
                trim($values['admin_prenom']),
                trim($values['admin_email']),
                $hash,
            ]);

            // Paramètres du site (upsert)
            $set = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?)
                                   ON DUPLICATE KEY UPDATE value = VALUES(value)');
            foreach (['site_name', 'tagline', 'header_baseline', 'about_text'] as $key) {
                $set->execute([$key, trim($values[$key])]);
            }

            if (!is_dir(__DIR__ . '/config')) {
                mkdir(__DIR__ . '/config', 0775, true);
            }

            $cfg  = "<?php\n";
            $cfg .= "// Généré automatiquement par install.php — ne pas committer en clair.\n";
            $cfg .= "define('DB_HOST', " . var_export($values['host'], true) . ");\n";
            $cfg .= "define('DB_NAME', " . var_export($values['dbname'], true) . ");\n";
            $cfg .= "define('DB_USER', " . var_export($values['user'], true) . ");\n";
            $cfg .= "define('DB_PASS', " . var_export($values['password'], true) . ");\n";

            if (file_put_contents($configFile, $cfg) === false) {
                throw new Exception('Impossible d\'écrire config/config.php (permissions ?)');
            }

            header('Location: index.php?installed=1');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Erreur : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="install-body">
<div class="install-card">
    <h1>Installation du blog</h1>
    <p class="muted">Configure ton site, la base de données et le compte administrateur. Tous les textes seront modifiables ensuite depuis l'admin.</p>

    <?php if (!empty($_SESSION['flash']['error'])): ?>
        <div class="flash flash-error"><?= htmlspecialchars($_SESSION['flash']['error']) ?></div>
        <?php unset($_SESSION['flash']['error']); ?>
    <?php endif; ?>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <h2>Identité du site</h2>
        <label>Nom du site
            <input type="text" name="site_name" value="<?= htmlspecialchars($values['site_name']) ?>" required maxlength="100">
        </label>
        <label>Slogan (sous le nom)
            <input type="text" name="tagline" value="<?= htmlspecialchars($values['tagline']) ?>" maxlength="100">
        </label>
        <label>Phrase d'accueil (barre du haut)
            <input type="text" name="header_baseline" value="<?= htmlspecialchars($values['header_baseline']) ?>" maxlength="200">
        </label>
        <label>Texte "À propos" (encadré de la page d'accueil)
            <textarea name="about_text" rows="3" maxlength="500"><?= htmlspecialchars($values['about_text']) ?></textarea>
        </label>

        <h2>Base de données</h2>
        <label>Hôte
            <input type="text" name="host" value="<?= htmlspecialchars($values['host']) ?>" required>
        </label>
        <label>Nom de la base
            <input type="text" name="dbname" value="<?= htmlspecialchars($values['dbname']) ?>" required>
        </label>
        <label>Utilisateur
            <input type="text" name="user" value="<?= htmlspecialchars($values['user']) ?>" required>
        </label>
        <label>Mot de passe
            <input type="password" name="password" value="<?= htmlspecialchars($values['password']) ?>">
        </label>

        <h2>Compte administrateur</h2>
        <label>Prénom
            <input type="text" name="admin_prenom" value="<?= htmlspecialchars($values['admin_prenom']) ?>" required>
        </label>
        <label>Nom
            <input type="text" name="admin_nom" value="<?= htmlspecialchars($values['admin_nom']) ?>" required>
        </label>
        <label>Email
            <input type="email" name="admin_email" value="<?= htmlspecialchars($values['admin_email']) ?>" required>
        </label>
        <label>Mot de passe (min. 6 caractères)
            <input type="password" name="admin_password" required minlength="6">
        </label>

        <button type="submit" class="btn-primary">Installer</button>
    </form>
</div>
</body>
</html>
