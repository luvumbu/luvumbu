<?php
/* =========================================================
   INSTALLATEUR — Configuration de la connexion à la base
   S'affiche automatiquement quand le site n'arrive pas à se
   connecter à la base de données. Saisir les identifiants,
   tester, enregistrer. À sécuriser/supprimer après usage.
   ========================================================= */

define('BDD_INSTALLER', true);           // empêche bdd.php de rediriger en boucle
require_once __DIR__ . '/../config/bdd.php';

// Déjà connecté ? L'installateur ne sert à rien → retour au site.
if (isset($bdd) && $bdd instanceof PDO) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$erreurs = [];
$manuel  = '';
$ok      = false;

$vals = [
    'host' => $_POST['host'] ?? ($bd_host ?? 'localhost'),
    'name' => $_POST['name'] ?? ($bd_name ?? ''),
    'user' => $_POST['user'] ?? ($bd_user ?? ''),
    'pass' => $_POST['pass'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $h = trim($vals['host']);
    $n = trim($vals['name']);
    $u = trim($vals['user']);
    $p = (string)$vals['pass'];

    if ($h === '' || $n === '' || $u === '') {
        $erreurs[] = "L'hôte, le nom de la base et l'utilisateur sont obligatoires.";
    } else {
        try {
            // On teste la connexion avant d'enregistrer quoi que ce soit
            new PDO(
                "mysql:host=$h;dbname=$n;charset=utf8",
                $u, $p,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Clé API : on garde celle déjà en place, sinon on en génère une nouvelle
            $apiKey = !empty($__cfg['api_key']) ? $__cfg['api_key'] : bin2hex(random_bytes(24));

            $content = "<?php\n// Config générée par l'installateur. Ne pas versionner.\nreturn [\n"
                . "    'host' => " . var_export($h, true) . ",\n"
                . "    'name' => " . var_export($n, true) . ",\n"
                . "    'user' => " . var_export($u, true) . ",\n"
                . "    'pass' => " . var_export($p, true) . ",\n"
                . "    'api_key' => " . var_export($apiKey, true) . ",\n"
                . "];\n";

            $cible = dirname(__DIR__) . '/config/db.php';
            if (@file_put_contents($cible, $content) === false) {
                $erreurs[] = "La connexion fonctionne, mais impossible d'écrire config/db.php "
                          . "(droits d'écriture insuffisants). Crée ce fichier à la main avec le contenu ci-dessous :";
                $manuel = $content;
            } else {
                $ok = true;
                $api_key_affiche = $apiKey;
                $__sch = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $endpoint = $__sch . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . 'api/missions.php';
            }
        } catch (Exception $e) {
            $erreurs[] = "Échec de connexion : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Installation - Connexion à la base</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<main class="page-main">
    <div class="auth-container" style="max-width:520px">
        <div class="auth-form">
            <h2>Configuration de la base de données</h2>

            <?php if ($ok): ?>
                <div class="alert alert-success">
                    ✅ Connexion réussie et enregistrée ! Ton site est prêt.
                </div>

                <div style="text-align:left;font-size:13px;margin:10px 0">
                    <strong>Clé API</strong> (pour créer des annonces à distance) :
                    <pre style="background:#0f1720;color:#7ee0a8;padding:12px;border-radius:8px;overflow:auto;font-size:12px;margin:6px 0"><?= htmlspecialchars($api_key_affiche) ?></pre>
                    <strong>URL de l'API</strong> :
                    <pre style="background:#0f1720;color:#d6e2f0;padding:12px;border-radius:8px;overflow:auto;font-size:12px;margin:6px 0"><?= htmlspecialchars($endpoint) ?></pre>
                    <em>Note ces deux valeurs</em> : c'est ce qu'il faut communiquer pour publier des annonces via l'API.
                    Tu les retrouveras aussi dans <code>admin/api.php</code> (connecté en admin).
                </div>

                <p class="form-foot" style="text-align:left">
                    <strong style="color:#c0392b">Important (sécurité) :</strong>
                    supprime maintenant le dossier <code>install/</code> du serveur.
                </p>
                <a href="<?= BASE_URL ?>index.php" class="btn btn-primary">Aller au site</a>
            <?php else: ?>
                <p style="font-size:14px;color:#6b7684;margin-top:-4px">
                    Le site n'arrive pas à se connecter à la base. Entre les identifiants
                    de ta base MySQL (chez Hostinger : <em>hPanel → Bases de données → MySQL</em>).
                </p>

                <?php if (!empty($erreurs)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($erreurs as $e): ?>
                            <div><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($manuel !== ''): ?>
                    <pre style="background:#0f1720;color:#d6e2f0;padding:14px;border-radius:8px;overflow:auto;font-size:12px"><?= htmlspecialchars($manuel) ?></pre>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>
                    <label style="font-size:13px;font-weight:600;color:#33404f">Hôte
                        <input type="text" name="host" value="<?= htmlspecialchars($vals['host']) ?>" placeholder="localhost" required>
                    </label>
                    <label style="font-size:13px;font-weight:600;color:#33404f">Nom de la base
                        <input type="text" name="name" value="<?= htmlspecialchars($vals['name']) ?>" placeholder="u481158665_hr" required>
                    </label>
                    <label style="font-size:13px;font-weight:600;color:#33404f">Utilisateur
                        <input type="text" name="user" value="<?= htmlspecialchars($vals['user']) ?>" placeholder="u481158665_hr" required>
                    </label>
                    <label style="font-size:13px;font-weight:600;color:#33404f">Mot de passe
                        <input type="password" name="pass" value="<?= htmlspecialchars($vals['pass']) ?>" placeholder="mot de passe MySQL">
                    </label>
                    <button type="submit" class="btn btn-primary">Tester et enregistrer</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
