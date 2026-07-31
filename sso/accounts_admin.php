<?php
/* ═══════════════════════════════════════════════════════════════════════
   LUVUMBU ID — gestion des comptes et des accès.

   Un seul endroit pour dire QUI entre et DANS QUOI. Réservé aux comptes
   ayant le rôle « admin » sur '*' (tout l'écosystème).
   ═══════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

require __DIR__ . '/client.php';     // démarre la session + fournit luvumbu_*
require __DIR__ . '/accounts.php';

$me = luvumbu_require_login('luvumbu-id');
if (!luvumbu_is_admin($me)) {
    http_response_code(403);
    exit('Accès réservé aux administrateurs de Luvumbu ID.');
}

/* Applications connues de l'écosystème : sert à composer les cases à cocher.
   Une app absente de cette liste reste gérable — ses rôles enregistrés sont
   affichés en fin de tableau. */
const LUVID_APPS = [
    '*'          => 'Toutes les applications',
    'admin'      => 'Espace admin du portfolio',
    'gestion'    => 'Gestionnaire de fichiers',
    'blog'       => 'Blog / articles',
    'photosync'  => 'PhotoSync (dropbox)',
    'dualcam'    => 'DualCam',
    'cv_luvumbu' => 'CV Luvumbu',
    'objectifs'  => 'Élan (objectifs)',
    'rpn'        => 'RPN',
    'anniversaire' => 'Anniversaire',
    'tamagotchi' => 'Tamagotchi',
    'marion'     => 'Transfert (marion)',
];

/* ─── Jeton anti-CSRF ─── */
if (empty($_SESSION['luvid_csrf'])) $_SESSION['luvid_csrf'] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION['luvid_csrf'];
function csrf_ok(): bool {
    return hash_equals((string)($_SESSION['luvid_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''));
}

$msg = ''; $err = '';

/* ─── Lecture des rôles envoyés par le formulaire ───────────────────────── */
function posted_roles(): array {
    $roles = [];
    foreach ((array)($_POST['role'] ?? []) as $app => $level) {
        $app   = trim((string)$app);
        $level = (string)$level;
        if ($app === '' || !in_array($level, [LUVID_ROLE_USER, LUVID_ROLE_ADMIN], true)) continue;
        $roles[$app] = $level;
    }
    return $roles;
}

/* ─── Actions ───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = 'Session expirée, recommence.';
    } elseif (isset($_POST['save'])) {
        $email = luvid_norm_email((string)($_POST['email'] ?? ''));
        $pw    = (string)($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Adresse e-mail invalide.';
        } else {
            $acc = luvid_account_get($email) ?? ['email' => $email, 'created_at' => time()];
            $acc['name']     = trim((string)($_POST['name'] ?? '')) ?: $email;
            $acc['roles']    = posted_roles();
            $acc['disabled'] = !empty($_POST['disabled']);

            // Champ mot de passe laissé vide = on ne touche pas à l'existant.
            if ($pw !== '') $acc['pass_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            elseif (!empty($_POST['clear_password'])) $acc['pass_hash'] = null;

            // Garde-fou : ne pas supprimer le dernier administrateur du hub.
            $sim = luvid_accounts_load();
            $sim[$email] = luvid_account_normalize($acc, $email);
            if (!luvid_has_any_admin($sim)) {
                $err = 'Impossible : il ne resterait plus aucun administrateur actif.';
            } elseif (luvid_account_put($acc)) {
                $msg = 'Compte « ' . $email . ' » enregistré.';
            } else {
                $err = "Écriture impossible — vérifie les droits sur le dossier sso/.";
            }
        }
    } elseif (isset($_POST['delete'])) {
        $email = luvid_norm_email((string)$_POST['delete']);
        if ($email === luvid_norm_email((string)$me['email'])) {
            $err = 'On ne supprime pas son propre compte.';
        } elseif (luvid_account_delete($email)) {
            $msg = 'Compte « ' . $email . ' » supprimé.';
        } else {
            $err = 'Suppression refusée (compte introuvable, ou dernier administrateur).';
        }
    }
}

$accounts = luvid_accounts_load();

/* Applications inconnues de LUVID_APPS mais présentes dans les comptes. */
$extraApps = [];
foreach ($accounts as $a) {
    foreach (array_keys((array)$a['roles']) as $k) {
        if (!isset(LUVID_APPS[$k])) $extraApps[$k] = $k;
    }
}
$APPS = LUVID_APPS + $extraApps;

/* Compte en cours d'édition (?edit=email), sinon formulaire de création. */
$editEmail = luvid_norm_email((string)($_GET['edit'] ?? ''));
$edit      = $editEmail !== '' ? ($accounts[$editEmail] ?? null) : null;

function he($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function when(int $t): string { return $t ? date('d/m/Y H:i', $t) : '—'; }
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Luvumbu ID — Comptes</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:#0e1526;color:#eaf0ff;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px}
  .wrap{max-width:1000px;margin:0 auto}
  h1{font-size:1.35rem;margin:0 0 4px}.brand{color:#5b8cff}
  .sub{color:#9fb0d0;font-size:.9rem;margin:0 0 22px}
  .panel{background:#151d31;border:1px solid #2a3450;border-radius:16px;padding:20px;margin-bottom:18px}
  h2{font-size:1rem;margin:0 0 14px;color:#cfe0ff}
  .msg{background:rgba(80,220,140,.12);border:1px solid #3ec98a;color:#a8f0cd;padding:10px;border-radius:9px;font-size:.87rem;margin-bottom:14px}
  .err{background:rgba(255,93,108,.12);border:1px solid #ff5d6c;color:#ffb0b8;padding:10px;border-radius:9px;font-size:.87rem;margin-bottom:14px}
  table{width:100%;border-collapse:collapse;font-size:.88rem}
  th,td{text-align:left;padding:9px 8px;border-bottom:1px solid #222c47;vertical-align:top}
  th{color:#9fb0d0;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em}
  .tag{display:inline-block;background:#1b2540;border:1px solid #2a3450;border-radius:6px;padding:1px 7px;margin:1px 3px 1px 0;font-size:.76rem}
  .tag.admin{border-color:#5b8cff;color:#a9c4ff}
  .off{color:#ff9aa4}
  label{display:block;font-size:.8rem;color:#9fb0d0;margin:0 0 4px}
  input[type=text],input[type=email],input[type=password]{width:100%;padding:10px;border-radius:9px;border:1px solid #2a3450;background:#1b2540;color:#eaf0ff;font-size:14px}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
  .apps{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px;margin-bottom:14px}
  .appbox{background:#1b2540;border:1px solid #2a3450;border-radius:10px;padding:9px 11px;font-size:.84rem}
  .appbox .nm{display:block;margin-bottom:5px;color:#cfe0ff}
  .appbox select{width:100%;padding:6px;border-radius:7px;border:1px solid #2a3450;background:#151d31;color:#eaf0ff;font-size:13px}
  button{padding:10px 16px;border-radius:9px;border:1px solid #5b8cff;background:#5b8cff;color:#fff;font-weight:600;cursor:pointer;font-size:14px}
  button:hover{background:#3a6bff}
  button.ghost{background:transparent;color:#9fb0d0;border-color:#2a3450}
  button.ghost:hover{background:#1b2540}
  button.danger{background:transparent;border-color:#ff5d6c;color:#ff9aa4;padding:5px 10px;font-size:.8rem}
  button.danger:hover{background:rgba(255,93,108,.12)}
  a{color:#8fb0ff}
  .chk{font-size:.85rem;color:#cfe0ff;display:flex;align-items:center;gap:7px;margin-bottom:12px}
  .chk input{width:auto}
  .hint{color:#7d8db0;font-size:.78rem;margin:-8px 0 14px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Luvumbu <span class="brand">ID</span> — Comptes</h1>
  <p class="sub">Qui peut entrer, et dans quelle application. Connecté en tant que <b><?= he($me['email']) ?></b> · <a href="index.php">retour au hub</a></p>

  <?php if ($msg): ?><div class="msg"><?= he($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= he($err) ?></div><?php endif; ?>

  <div class="panel">
    <h2><?= $edit ? 'Modifier « ' . he($edit['email']) . ' »' : 'Nouveau compte' ?></h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= he($CSRF) ?>">
      <div class="row">
        <div>
          <label for="email">Adresse e-mail<?= $edit ? '' : ' (identifiant, et compte Google éventuel)' ?></label>
          <input type="email" id="email" name="email" required
                 value="<?= he($edit['email'] ?? '') ?>" <?= $edit ? 'readonly' : '' ?>>
        </div>
        <div>
          <label for="name">Nom affiché</label>
          <input type="text" id="name" name="name" value="<?= he($edit['name'] ?? '') ?>">
        </div>
      </div>

      <div class="row">
        <div>
          <label for="password">Mot de passe<?= $edit ? ' (vide = inchangé)' : ' (facultatif si connexion Google)' ?></label>
          <input type="password" id="password" name="password" autocomplete="new-password">
        </div>
        <?php if ($edit && $edit['pass_hash'] !== null): ?>
        <div>
          <label>&nbsp;</label>
          <div class="chk"><input type="checkbox" id="clr" name="clear_password" value="1">
            <label for="clr" style="margin:0">Retirer le mot de passe (Google seulement)</label></div>
        </div>
        <?php endif; ?>
      </div>

      <label>Accès aux applications</label>
      <p class="hint">« Aucun » = l'application refuse l'entrée. « Toutes les applications » sert de valeur par défaut pour celles qui ne sont pas réglées ici.</p>
      <div class="apps">
        <?php foreach ($APPS as $key => $lbl):
              $cur = (string)(($edit['roles'] ?? [])[$key] ?? ''); ?>
          <div class="appbox">
            <span class="nm"><?= he($lbl) ?><?= $key === '*' ? '' : ' <code style="color:#7d8db0">'.he($key).'</code>' ?></span>
            <select name="role[<?= he($key) ?>]">
              <option value="">Aucun</option>
              <option value="user"  <?= $cur === 'user'  ? 'selected' : '' ?>>Utilisateur</option>
              <option value="admin" <?= $cur === 'admin' ? 'selected' : '' ?>>Administrateur</option>
            </select>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="chk">
        <input type="checkbox" id="dis" name="disabled" value="1" <?= !empty($edit['disabled']) ? 'checked' : '' ?>>
        <label for="dis" style="margin:0">Compte désactivé (aucune connexion possible)</label>
      </div>

      <button type="submit" name="save" value="1"><?= $edit ? 'Enregistrer' : 'Créer le compte' ?></button>
      <?php if ($edit): ?> <a href="accounts_admin.php"><button type="button" class="ghost">Annuler</button></a><?php endif; ?>
    </form>
  </div>

  <div class="panel">
    <h2><?= count($accounts) ?> compte<?= count($accounts) > 1 ? 's' : '' ?></h2>
    <table>
      <tr><th>Compte</th><th>Accès</th><th>Connexion</th><th>Dernier accès</th><th></th></tr>
      <?php foreach ($accounts as $a): ?>
      <tr>
        <td>
          <b><?= he($a['name']) ?></b><br>
          <small style="color:#9fb0d0"><?= he($a['email']) ?></small>
          <?= $a['disabled'] ? '<br><small class="off">désactivé</small>' : '' ?>
        </td>
        <td>
          <?php if (!$a['roles']): ?><small style="color:#7d8db0">aucun</small><?php endif; ?>
          <?php foreach ($a['roles'] as $k => $v): ?>
            <span class="tag <?= $v === 'admin' ? 'admin' : '' ?>"><?= he($k === '*' ? 'tout' : $k) ?> : <?= he($v) ?></span>
          <?php endforeach; ?>
        </td>
        <td><small><?= $a['pass_hash'] !== null ? 'mot de passe + Google' : 'Google seulement' ?></small></td>
        <td><small><?= when($a['last_login']) ?></small></td>
        <td style="white-space:nowrap">
          <a href="?edit=<?= urlencode($a['email']) ?>">Modifier</a>
          <?php if (luvid_norm_email((string)$me['email']) !== $a['email']): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Supprimer <?= he($a['email']) ?> ?')">
              <input type="hidden" name="csrf" value="<?= he($CSRF) ?>">
              <button class="danger" name="delete" value="<?= he($a['email']) ?>">Supprimer</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
