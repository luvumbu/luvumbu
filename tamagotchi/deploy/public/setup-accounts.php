<?php
/**
 * 🔧 Mise à jour de la base pour les COMPTES PARENTS + PROFILS ENFANTS.
 * À visiter UNE SEULE FOIS après avoir envoyé les nouveaux fichiers :
 *   https://luvumbu.com/tamagotchi/public/setup-accounts.php
 *
 * Sans danger : n'efface aucune donnée, ne fait qu'AJOUTER ce qui manque.
 * Supprime ce fichier une fois que ça affiche « Terminé ».
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
header('Content-Type: text/html; charset=utf-8');

$cfg = @include __DIR__ . '/../config/config.php';
if (!is_array($cfg) || empty($cfg['db']['name'])) {
    exit('❌ config.php introuvable ou incomplet. Fais d\'abord l\'installation.');
}

$db = $cfg['db'];
try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
        $db['user'], $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    exit('❌ Connexion à la base impossible : ' . htmlspecialchars($e->getMessage()));
}

// Chaque instruction est jouée séparément ; on ignore « déjà existant ».
$statements = [
    "ALTER TABLE users ADD COLUMN google_sub VARCHAR(64) NULL UNIQUE",
    "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL",
    "ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL",
    "CREATE TABLE IF NOT EXISTS children (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED NOT NULL,
        name       VARCHAR(50)  NOT NULL,
        avatar     VARCHAR(16)  NOT NULL DEFAULT '🐣',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "ALTER TABLE pets ADD COLUMN child_id INT UNSIGNED NULL",
];

$ok = []; $skip = []; $err = [];
foreach ($statements as $sql) {
    try {
        $pdo->exec($sql);
        $ok[] = strtok(trim($sql), "\n");
    } catch (Throwable $e) {
        if (preg_match('/exists|Duplicate|duplicate/i', $e->getMessage())) {
            $skip[] = strtok(trim($sql), "\n") . '  (déjà en place)';
        } else {
            $err[] = htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🔧 Mise à jour — Comptes</title>
<style>
  body{font-family:system-ui,sans-serif;background:#fff4e0;color:#3a2e2e;padding:1.5rem;max-width:600px;margin:auto}
  .card{background:#fff;border:3px solid #3a2e2e;border-radius:16px;padding:1.2rem}
  .ok{color:#2e7d32}.skip{color:#b58900}.ko{color:#b71c1c}
  code{background:#eef;padding:.1rem .3rem;border-radius:4px;font-size:.85rem}
  .big{font-size:2.5rem;text-align:center}
</style></head><body><div class="card">
<?php if (empty($err)): ?>
  <div class="big">✅</div>
  <h2 style="text-align:center">Base à jour — comptes activés !</h2>
  <p>Les profils parents + enfants sont prêts. Tu peux ouvrir le jeu.</p>
<?php else: ?>
  <div class="big">⚠️</div>
  <h2 style="text-align:center">Presque — quelques erreurs</h2>
<?php endif; ?>
  <?php foreach ($ok as $s): ?><p class="ok">✅ Ajouté : <code><?= htmlspecialchars($s) ?></code></p><?php endforeach; ?>
  <?php foreach ($skip as $s): ?><p class="skip">↷ <code><?= htmlspecialchars($s) ?></code></p><?php endforeach; ?>
  <?php foreach ($err as $s): ?><p class="ko">❌ <?= $s ?></p><?php endforeach; ?>
  <p style="margin-top:1rem">➡️ <a href="index.html">Ouvrir le jeu</a></p>
  <p style="color:#999;font-size:.85rem">Par sécurité, supprime ce fichier <code>setup-accounts.php</code> ensuite.</p>
</div></body></html>
