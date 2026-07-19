<?php
/**
 * 🛠️ Espace ADMIN du Tamagotchi.
 *   https://luvumbu.com/tamagotchi/public/admin.php
 *
 * Protégé par un mot de passe (défini au 1er accès). Permet de :
 *   - voir des statistiques
 *   - gérer les comptes (parents / enfants) — suppression
 *   - régler les paramètres du jeu (points, vitesses, seuils…)
 *   - régler la base de données (hôte, nom, utilisateur, mot de passe)
 *   - gérer la boutique (aliments / objets)
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();

$CONFIG_FILE = __DIR__ . '/../config/config.php';
$cfg = @include $CONFIG_FILE;
if (!is_array($cfg)) { $cfg = []; }

$msg = ''; $err = '';

function saveConfig(array $cfg, string $file): bool
{
    $php = "<?php\n// Généré par l'espace admin.\nreturn " . var_export($cfg, true) . ";\n";
    return @file_put_contents($file, $php) !== false;
}

function db(array $cfg): ?PDO
{
    $d = $cfg['db'] ?? null;
    if (!$d) return null;
    try {
        return new PDO(
            "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4",
            $d['user'], $d['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) { return null; }
}

$hasPassword = !empty($cfg['admin']['password_hash']);

// ---- 1er accès : définir le mot de passe admin ----
if (!$hasPassword && ($_POST['action'] ?? '') === 'setpw') {
    $p = (string)($_POST['pw'] ?? '');
    if (strlen($p) < 4) {
        $err = 'Choisis un mot de passe d’au moins 4 caractères.';
    } else {
        $cfg['admin']['password_hash'] = password_hash($p, PASSWORD_DEFAULT);
        if (saveConfig($cfg, $CONFIG_FILE)) {
            $hasPassword = true;
            $msg = 'Mot de passe admin créé. Connecte-toi.';
        } else {
            $err = 'Impossible d’écrire config.php (droits d’écriture ?).';
        }
    }
}

// ---- Connexion ----
if ($hasPassword && ($_POST['action'] ?? '') === 'login') {
    if (password_verify((string)($_POST['pw'] ?? ''), $cfg['admin']['password_hash'])) {
        $_SESSION['admin'] = true;
    } else {
        $err = 'Mot de passe incorrect.';
    }
}
if (($_GET['logout'] ?? '') === '1') { $_SESSION['admin'] = false; }

$authed = !empty($_SESSION['admin']);

// ---- Actions protégées ----
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_game') {
        foreach (($_POST['game'] ?? []) as $k => $v) {
            if (isset($cfg['game'][$k])) $cfg['game'][$k] = is_numeric($v) ? (float)$v : $v;
        }
        foreach (($_POST['learning'] ?? []) as $k => $v) {
            if (isset($cfg['learning'][$k]) && is_scalar($cfg['learning'][$k])) {
                $cfg['learning'][$k] = is_numeric($v) ? (float)$v : $v;
            }
        }
        $msg = saveConfig($cfg, $CONFIG_FILE) ? 'Paramètres du jeu enregistrés ✅' : 'Écriture impossible ❌';
    }

    if ($action === 'save_db') {
        $cfg['db']['host']     = trim((string)$_POST['host']);
        $cfg['db']['name']     = trim((string)$_POST['name']);
        $cfg['db']['user']     = trim((string)$_POST['user']);
        if (($_POST['password'] ?? '') !== '') $cfg['db']['password'] = (string)$_POST['password'];
        $test = db($cfg);
        if (!$test) { $err = 'Connexion à la base impossible avec ces infos — non enregistré.'; }
        else { $msg = saveConfig($cfg, $CONFIG_FILE) ? 'Base de données enregistrée ✅' : 'Écriture impossible ❌'; }
    }

    $pdo = db($cfg);
    if ($pdo) {
        if ($action === 'del_user') {
            $pdo->prepare('DELETE FROM pets WHERE user_id=?')->execute([(int)$_POST['id']]);
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([(int)$_POST['id']]);
            $msg = 'Compte supprimé ✅';
        }
        if ($action === 'del_child') {
            $pdo->prepare('DELETE FROM pets WHERE child_id=?')->execute([(int)$_POST['id']]);
            $pdo->prepare('DELETE FROM children WHERE id=?')->execute([(int)$_POST['id']]);
            $msg = 'Profil enfant supprimé ✅';
        }
        if ($action === 'save_item') {
            $id = (int)$_POST['id'];
            $p = [trim((string)$_POST['name']), (string)$_POST['emoji'], (int)$_POST['price'],
                  (int)$_POST['d_hunger'], (int)$_POST['d_energy'], (int)$_POST['d_health'], (int)$_POST['d_happy']];
            if ($id > 0) {
                $st = $pdo->prepare('UPDATE items SET name=?,emoji=?,price=?,d_hunger=?,d_energy=?,d_health=?,d_happy=? WHERE id=?');
                $st->execute([...$p, $id]);
                $msg = 'Article modifié ✅';
            } else {
                $st = $pdo->prepare('INSERT INTO items (name,emoji,type,category,effect,price,d_hunger,d_energy,d_health,d_happy) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$p[0], $p[1], 'food', 'autre', '', $p[2], $p[3], $p[4], $p[5], $p[6]]);
                $msg = 'Article ajouté ✅';
            }
        }
        if ($action === 'del_item') {
            $pdo->prepare('DELETE FROM items WHERE id=?')->execute([(int)$_POST['id']]);
            $msg = 'Article supprimé ✅';
        }
    }
}

$pdo = $authed ? db($cfg) : null;
$tab = $_GET['tab'] ?? 'stats';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🛠️ Admin — Tamagotchi</title>
<style>
  *{box-sizing:border-box;font-family:system-ui,'Segoe UI',sans-serif}
  body{background:#f4f6fb;color:#243;margin:0;padding:0}
  header{background:#243b6b;color:#fff;padding:1rem 1.2rem;display:flex;justify-content:space-between;align-items:center}
  header a{color:#cde;font-size:.9rem}
  .wrap{max-width:900px;margin:1.2rem auto;padding:0 1rem}
  .card{background:#fff;border-radius:14px;padding:1.2rem;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:1.2rem}
  .login{max-width:360px;margin:4rem auto}
  input,select{width:100%;padding:.55rem;border:1px solid #cbd;border-radius:8px;font-size:1rem;margin-top:.2rem}
  label{font-weight:700;font-size:.82rem;display:block;margin-top:.6rem}
  button{background:#2e63e6;color:#fff;border:none;border-radius:8px;padding:.6rem 1rem;font-weight:700;cursor:pointer;font-size:.95rem}
  button.red{background:#e23b3b}
  .tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1rem}
  .tabs a{padding:.5rem .9rem;border-radius:20px;background:#e6ebf6;color:#243;text-decoration:none;font-weight:700;font-size:.9rem}
  .tabs a.on{background:#2e63e6;color:#fff}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:.5rem;border-bottom:1px solid #eef;font-size:.9rem}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.6rem}
  .stat-b{background:#eef3ff;border-radius:12px;padding:1rem;text-align:center}
  .stat-b b{display:block;font-size:2rem;color:#2e63e6}
  .ok{background:#e7f8dc;color:#2e7d32;padding:.6rem;border-radius:8px;margin-bottom:1rem}
  .ko{background:#ffe0e0;color:#b71c1c;padding:.6rem;border-radius:8px;margin-bottom:1rem}
  .row{display:flex;gap:.5rem;flex-wrap:wrap;align-items:end}
  .row>div{flex:1;min-width:90px}
  small{color:#889}
</style></head><body>

<?php if (!$authed): ?>
  <div class="wrap"><div class="card login">
    <h2>🛠️ Espace Admin</h2>
    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="ko"><?= h($err) ?></div><?php endif; ?>
    <?php if (!$hasPassword): ?>
      <p><small>Premier accès : choisis un mot de passe administrateur.</small></p>
      <form method="post">
        <input type="hidden" name="action" value="setpw">
        <label>Nouveau mot de passe admin</label>
        <input type="password" name="pw" autofocus>
        <p></p><button type="submit">Créer le mot de passe</button>
      </form>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <label>Mot de passe admin</label>
        <input type="password" name="pw" autofocus>
        <p></p><button type="submit">Entrer</button>
      </form>
    <?php endif; ?>
    <p style="margin-top:1rem"><a href="index.html">← Retour au jeu</a></p>
  </div></div>

<?php else: ?>
  <header>
    <b>🛠️ Admin — Tamagotchi</b>
    <a href="?logout=1">Se déconnecter</a>
  </header>
  <div class="wrap">
    <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="ko"><?= h($err) ?></div><?php endif; ?>
    <?php if (!$pdo): ?><div class="ko">⚠️ Base non connectée — vérifie l’onglet « Base de données ».</div><?php endif; ?>

    <div class="tabs">
      <?php foreach (['stats'=>'📊 Stats','comptes'=>'👥 Comptes','jeu'=>'⚙️ Jeu','base'=>'🗄️ Base','boutique'=>'🏪 Boutique'] as $k=>$lbl): ?>
        <a href="?tab=<?= $k ?>" class="<?= $tab===$k?'on':'' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'stats'): ?>
      <div class="card"><h3>📊 Statistiques</h3>
        <?php if ($pdo):
          $nu=$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
          $nc=$pdo->query('SELECT COUNT(*) FROM children')->fetchColumn();
          $np=$pdo->query('SELECT COUNT(*) FROM pets')->fetchColumn();
          $nr=(int)$pdo->query('SELECT COALESCE(SUM(correct),0) FROM topic_progress')->fetchColumn();
        ?>
        <div class="grid">
          <div class="stat-b"><b><?= $nu ?></b>Parents</div>
          <div class="stat-b"><b><?= $nc ?></b>Enfants</div>
          <div class="stat-b"><b><?= $np ?></b>Créatures</div>
          <div class="stat-b"><b><?= $nr ?></b>Bonnes réponses</div>
        </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'comptes' && $pdo): ?>
      <div class="card"><h3>👥 Comptes</h3>
        <?php $users=$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(); ?>
        <?php if (!$users): ?><p>Aucun compte.</p><?php endif; ?>
        <?php foreach ($users as $u): ?>
          <div style="border-bottom:2px solid #eef;padding:.6rem 0">
            <div class="row">
              <div><b><?= h($u['email']) ?></b> <small>#<?= $u['id'] ?></small></div>
              <form method="post" onsubmit="return confirm('Supprimer ce compte et TOUTES ses données ?')">
                <input type="hidden" name="action" value="del_user"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="red" type="submit">🗑️ Compte</button>
              </form>
            </div>
            <?php $kids=$pdo->prepare('SELECT * FROM children WHERE user_id=?'); $kids->execute([$u['id']]); foreach ($kids->fetchAll() as $c): ?>
              <div class="row" style="margin:.25rem 0 .25rem 1rem">
                <div><?= h($c['avatar']) ?> <?= h($c['name']) ?> <small>enfant #<?= $c['id'] ?></small></div>
                <form method="post" onsubmit="return confirm('Supprimer ce profil enfant ?')">
                  <input type="hidden" name="action" value="del_child"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="red" type="submit">🗑️</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($tab === 'jeu'): ?>
      <div class="card"><h3>⚙️ Paramètres du jeu</h3>
        <form method="post"><input type="hidden" name="action" value="save_game">
          <h4>Créature (par heure / par action)</h4>
          <div class="grid">
          <?php foreach ($cfg['game'] as $k=>$v): if (!is_scalar($v)) continue; ?>
            <div><label><?= h($k) ?></label><input name="game[<?= h($k) ?>]" value="<?= h($v) ?>"></div>
          <?php endforeach; ?>
          </div>
          <h4>Apprentissage</h4>
          <div class="grid">
          <?php foreach ($cfg['learning'] as $k=>$v): if (!is_scalar($v) || $k==='secret') continue; ?>
            <div><label><?= h($k) ?></label><input name="learning[<?= h($k) ?>]" value="<?= h($v) ?>"></div>
          <?php endforeach; ?>
          </div>
          <p></p><button type="submit">💾 Enregistrer</button>
        </form>
      </div>

    <?php elseif ($tab === 'base'): ?>
      <div class="card"><h3>🗄️ Base de données</h3>
        <p><small>⚠️ Si tu changes ça, le jeu utilisera cette base. Laisse le mot de passe vide pour ne pas le changer.</small></p>
        <form method="post"><input type="hidden" name="action" value="save_db">
          <label>Hôte</label><input name="host" value="<?= h($cfg['db']['host'] ?? 'localhost') ?>">
          <label>Nom de la base</label><input name="name" value="<?= h($cfg['db']['name'] ?? '') ?>">
          <label>Utilisateur</label><input name="user" value="<?= h($cfg['db']['user'] ?? '') ?>">
          <label>Mot de passe (vide = inchangé)</label><input type="password" name="password" placeholder="••••••">
          <p></p><button type="submit">💾 Enregistrer &amp; tester</button>
        </form>
      </div>

    <?php elseif ($tab === 'boutique' && $pdo): ?>
      <div class="card"><h3>🏪 Boutique</h3>
        <?php foreach ($pdo->query('SELECT * FROM items ORDER BY id')->fetchAll() as $it): ?>
          <form method="post" class="row" style="border-bottom:1px solid #eef;padding:.5rem 0">
            <input type="hidden" name="action" value="save_item"><input type="hidden" name="id" value="<?= $it['id'] ?>">
            <div style="max-width:60px"><label>Emoji</label><input name="emoji" value="<?= h($it['emoji']) ?>"></div>
            <div><label>Nom</label><input name="name" value="<?= h($it['name']) ?>"></div>
            <div style="max-width:80px"><label>Prix</label><input name="price" value="<?= h($it['price']) ?>"></div>
            <div style="max-width:70px"><label>Faim</label><input name="d_hunger" value="<?= h($it['d_hunger']) ?>"></div>
            <div style="max-width:70px"><label>Énergie</label><input name="d_energy" value="<?= h($it['d_energy']) ?>"></div>
            <div style="max-width:70px"><label>Santé</label><input name="d_health" value="<?= h($it['d_health']) ?>"></div>
            <div style="max-width:70px"><label>Bonheur</label><input name="d_happy" value="<?= h($it['d_happy']) ?>"></div>
            <div style="display:flex;gap:.3rem"><button type="submit">💾</button></div>
        </form>
        <form method="post" onsubmit="return confirm('Supprimer cet article ?')" style="margin:-.9rem 0 .5rem">
            <input type="hidden" name="action" value="del_item"><input type="hidden" name="id" value="<?= $it['id'] ?>">
            <button class="red" type="submit">🗑️ Supprimer</button>
        </form>
        <?php endforeach; ?>
        <h4>➕ Ajouter un article</h4>
        <form method="post" class="row">
          <input type="hidden" name="action" value="save_item"><input type="hidden" name="id" value="0">
          <div style="max-width:60px"><label>Emoji</label><input name="emoji" value="🍏"></div>
          <div><label>Nom</label><input name="name" value=""></div>
          <div style="max-width:80px"><label>Prix</label><input name="price" value="10"></div>
          <div style="max-width:70px"><label>Faim</label><input name="d_hunger" value="-20"></div>
          <div style="max-width:70px"><label>Énergie</label><input name="d_energy" value="10"></div>
          <div style="max-width:70px"><label>Santé</label><input name="d_health" value="5"></div>
          <div style="max-width:70px"><label>Bonheur</label><input name="d_happy" value="2"></div>
          <div><button type="submit">➕ Ajouter</button></div>
        </form>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</body></html>
