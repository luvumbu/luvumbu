<?php
/**
 * 🧩 Assistant d'installation du Tamagotchi éducatif.
 * Ouvre cette page dans le navigateur : elle DEMANDE les infos de la base MySQL,
 * teste la connexion, crée les tables, remplit config.php — tout automatiquement.
 *
 *   https://luvumbu.com/tamagotchi/public/install.php
 *
 * ⚠️ Supprime ce fichier une fois l'installation terminée (bouton proposé à la fin).
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$CONFIG_FILE = __DIR__ . '/../config/config.php';
$DB_DIR      = __DIR__ . '/../database/';

$done = false;
$errors = [];
$logs = [];

/** L'application est-elle DÉJÀ installée (config valide + base joignable + tables présentes) ? */
function alreadyInstalled(string $configFile): bool
{
    if (!is_file($configFile)) return false;
    $cfg = @include $configFile;
    if (!is_array($cfg) || empty($cfg['db']['name'])) return false;
    try {
        $db = $cfg['db'];
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
            $db['user'], $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 4]
        );
        $pdo->query('SELECT 1 FROM pets LIMIT 1');   // une table du jeu doit exister
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// 🔒 SÉCURITÉ : si c'est déjà installé, on bloque toute réinstallation
// (sauf si on force explicitement avec ?force=1).
$installed = alreadyInstalled($CONFIG_FILE);
$force     = isset($_GET['force']);

// Valeurs par défaut (pré-remplies)
$host = $_POST['host'] ?? 'localhost';
$name = $_POST['name'] ?? 'tamagotchi';
$user = $_POST['user'] ?? '';
$pass = $_POST['pass'] ?? '';

function runSqlFile(PDO $pdo, string $file, array &$logs, array &$errors): void
{
    if (!is_file($file)) { $errors[] = "Fichier introuvable : " . basename($file); return; }
    $sql = file_get_contents($file);
    // Retire les commentaires de ligne (-- ...) puis découpe sur ;
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        // ⚠️ Hébergement mutualisé : on saute CREATE DATABASE et USE
        // (la base est imposée par l'hébergeur, on est déjà dedans).
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE)\b/i', $stmt)) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            // On ignore les erreurs "déjà existant" (ré-installation)
            if (!preg_match('/already exists|Duplicate|exists/i', $e->getMessage())) {
                $errors[] = basename($file) . " : " . $e->getMessage();
            }
        }
    }
    $logs[] = "✅ Importé : " . basename($file);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $installed && !$force) {
    // 🔒 Déjà installé : on refuse de ré-écraser la base sans confirmation explicite.
    $errors[] = "L'application est déjà installée. Réinstaller effacerait les données existantes.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1) On essaie d'abord de se connecter DIRECTEMENT à la base (cas hébergeur :
        //    la base est déjà créée dans le panneau).
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $logs[] = "✅ Connexion à la base « $name » réussie.";
        } catch (Throwable $e1) {
            // 2) Sinon on se connecte au serveur et on tente de CRÉER la base.
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");
            $logs[] = "✅ Base « $name » créée.";
        }

        // 3) Importer schéma, migrations, données
        runSqlFile($pdo, $DB_DIR . 'schema.sql', $logs, $errors);
        foreach (['001_educational', '002_shop', '003_progress', '004_accounts'] as $m) {
            runSqlFile($pdo, $DB_DIR . "migrations/$m.sql", $logs, $errors);
        }
        runSqlFile($pdo, $DB_DIR . 'seeds/seed.sql', $logs, $errors);
        runSqlFile($pdo, $DB_DIR . 'seeds/foods.sql', $logs, $errors);

        // 4) Écrire config.php (on garde les autres réglages, on remplace la base)
        $cfg = is_file($CONFIG_FILE) ? require $CONFIG_FILE : [];
        $cfg['db'] = [
            'host' => $host, 'port' => 3306, 'name' => $name,
            'user' => $user, 'password' => $pass, 'charset' => 'utf8mb4',
        ];
        if (empty($cfg['learning']['secret']) || $cfg['learning']['secret'] === 'change-moi-en-prod-svp') {
            $cfg['learning']['secret'] = bin2hex(random_bytes(16));
        }
        $php = "<?php\n// Généré par l'assistant d'installation.\nreturn " . var_export($cfg, true) . ";\n";
        if (@file_put_contents($CONFIG_FILE, $php) === false) {
            $errors[] = "Impossible d'écrire config.php (droits d'écriture ?). Édite-le à la main avec ces infos.";
        } else {
            $logs[] = "✅ config.php enregistré.";
        }

        if (empty($errors)) { $done = true; }
    } catch (Throwable $e) {
        $errors[] = "Connexion impossible : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>🧩 Installation — Tamagotchi</title>
<style>
  *{box-sizing:border-box;font-family:system-ui,'Segoe UI',sans-serif}
  body{background:#fff4e0;color:#3a2e2e;margin:0;padding:1.2rem;display:grid;place-items:start center}
  .box{background:#fff;max-width:460px;width:100%;border:3px solid #3a2e2e;border-radius:18px;padding:1.4rem;box-shadow:0 8px 0 rgba(0,0,0,.1)}
  h1{font-size:1.4rem;text-align:center;margin:.2rem 0 .4rem}
  p.sub{text-align:center;color:#777;font-size:.9rem;margin-bottom:1rem}
  label{font-weight:700;font-size:.85rem;display:block;margin:.7rem 0 .2rem}
  input{width:100%;padding:.6rem;border:2px solid #ddd;border-radius:10px;font-size:1rem}
  button{width:100%;margin-top:1.1rem;border:none;background:#58cc02;color:#fff;font-weight:800;
         font-size:1.1rem;padding:.8rem;border-radius:14px;box-shadow:0 4px 0 #3f9000;cursor:pointer}
  .msg{border-radius:10px;padding:.6rem .8rem;margin:.4rem 0;font-size:.9rem}
  .ok{background:#e7f8dc;color:#2e7d32}.ko{background:#ffe0e0;color:#b71c1c}
  .big{text-align:center;font-size:3rem}
  a.play{display:block;text-align:center;margin-top:1rem;background:#1cb0f6;color:#fff;text-decoration:none;
         font-weight:800;padding:.8rem;border-radius:14px}
  code{background:#eef;padding:.1rem .3rem;border-radius:5px}
</style></head>
<body><div class="box">

<?php if ($installed && !$force && !$done): ?>
  <div class="big">🔒</div>
  <h1>Déjà installé</h1>
  <p class="sub">La base de données est déjà configurée et fonctionne.
     Pas besoin de réinstaller — le jeu est prêt !</p>
  <?php foreach ($errors as $e): ?><div class="msg ko">❌ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <a class="play" href="index.html">▶ Ouvrir le jeu</a>
  <p class="sub" style="margin-top:1rem">
    ⚠️ Par sécurité, <b>supprime le fichier <code>install.php</code></b>.<br>
    Besoin de tout réinstaller (⚠️ efface les données) ?
    <a href="?force=1">Forcer la réinstallation</a>.
  </p>

<?php elseif ($done): ?>
  <div class="big">🎉</div>
  <h1>Installation terminée !</h1>
  <p class="sub">La base est créée et branchée. Ton Tamagotchi est prêt.</p>
  <?php foreach ($logs as $l): ?><div class="msg ok"><?= htmlspecialchars($l) ?></div><?php endforeach; ?>
  <a class="play" href="index.html">▶ Ouvrir le jeu</a>
  <p class="sub" style="margin-top:1rem">⚠️ Par sécurité, <b>supprime le fichier <code>install.php</code></b> maintenant.</p>
<?php else: ?>
  <div class="big">🧩</div>
  <h1>Installation du Tamagotchi</h1>
  <p class="sub">Entre les infos MySQL de ton hébergeur. L'assistant crée la base et configure tout.</p>

  <?php if ($installed && $force): ?>
    <div class="msg ko">⚠️ Réinstallation forcée : les données existantes peuvent être écrasées.</div>
  <?php endif; ?>

  <?php foreach ($errors as $e): ?><div class="msg ko">❌ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <?php foreach ($logs as $l): ?><div class="msg ok"><?= htmlspecialchars($l) ?></div><?php endforeach; ?>

  <form method="post">
    <label>Hôte MySQL</label>
    <input name="host" value="<?= htmlspecialchars($host) ?>" placeholder="localhost">
    <label>Nom de la base</label>
    <input name="name" value="<?= htmlspecialchars($name) ?>" placeholder="tamagotchi">
    <label>Utilisateur</label>
    <input name="user" value="<?= htmlspecialchars($user) ?>" placeholder="ton_utilisateur" required>
    <label>Mot de passe</label>
    <input name="pass" type="password" value="<?= htmlspecialchars($pass) ?>" placeholder="ton_mot_de_passe">
    <button type="submit">🚀 Installer</button>
  </form>
<?php endif; ?>

</div></body></html>
