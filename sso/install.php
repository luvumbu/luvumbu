<?php
/* ═══════════════════════════════════════════════════════════════════════
   LUVUMBU ID — assistant de configuration.

   secret.local.php porte la clé de signature des jetons : il est exclu de Git
   et n'arrive donc jamais par un déploiement. Il fallait jusqu'ici le déposer
   par FTP, ce qui laissait le SSO inactif en ligne. Cette page l'écrit depuis
   le navigateur.

   PROTECTION — cette page peut définir la clé qui authentifie TOUT
   l'écosystème : laissée ouverte, un inconnu pourrait forger des jetons
   valides partout. Elle exige donc d'être déjà connecté à l'espace admin
   (admin.php), qui sait se rabattre sur les identifiants MySQL justement tant
   que le SSO n'est pas actif. Aucune logique d'authentification n'est
   réécrite ici : on réutilise la sienne.

   ATTENTION AU VERROUILLAGE — dès que le secret existe, admin.php et le
   gestionnaire cessent d'accepter leur formulaire de secours et exigent le
   hub. Sans compte dans l'annuaire, plus personne n'entre. L'assistant crée
   donc le premier compte administrateur DANS LE MÊME GESTE.
   ═══════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

session_start();

if (empty($_SESSION['pf_admin'])) {
    header('Location: ../admin.php');
    exit;
}

require __DIR__ . '/lib.php';
require __DIR__ . '/accounts.php';

const SECRET_FILE = __DIR__ . '/secret.local.php';

$cfg      = sso_config();
$dejaPret = sso_ready();

$err = '';
$msg = '';

/* Valeurs du formulaire, pré-remplies avec ce qui existe déjà. */
$f = [
    'secret'    => (string) $cfg['secret'],
    'client_id' => (string) $cfg['google_client_id'],
    'domaine'   => (string) $cfg['cookie_domain'],
    'email'     => (string) (is_array($cfg['local_user'] ?? null) ? ($cfg['local_user']['email'] ?? '') : ''),
    'nom'       => (string) (is_array($cfg['local_user'] ?? null) ? ($cfg['local_user']['name'] ?? '') : ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer'])) {
    $f['secret']    = trim((string) ($_POST['secret'] ?? ''));
    $f['client_id'] = trim((string) ($_POST['client_id'] ?? ''));
    $f['domaine']   = trim((string) ($_POST['domaine'] ?? ''));
    $f['email']     = strtolower(trim((string) ($_POST['email'] ?? '')));
    $f['nom']       = trim((string) ($_POST['nom'] ?? ''));
    $mdp            = (string) ($_POST['mdp'] ?? '');

    if ($f['secret'] === '') {
        $f['secret'] = bin2hex(random_bytes(32));    // case « générer » cochée, ou champ laissé vide
    }

    if (strlen($f['secret']) < 24) {
        $err = 'La clé doit faire au moins 24 caractères. Laisse le champ vide pour en générer une.';
    } elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $err = 'Adresse e-mail du compte administrateur invalide.';
    } elseif (strlen($mdp) < 8) {
        $err = 'Choisis un mot de passe d’au moins 8 caractères : sans lui, personne ne pourra '
             . 'entrer une fois le SSO actif.';
    } else {
        $conf = [
            'secret'           => $f['secret'],
            'google_client_id' => $f['client_id'],
            'cookie_domain'    => $f['domaine'],
            'password'         => $mdp,
            'local_user'       => ['email' => $f['email'], 'name' => $f['nom'] !== '' ? $f['nom'] : $f['email']],
        ];
        $code = "<?php\n"
              . "/* LUVUMBU ID — secret partagé. GÉNÉRÉ par sso/install.php, HORS DÉPÔT.\n"
              . "   Ce même fichier doit être copié à l'identique sur tout autre serveur\n"
              . "   hébergeant une copie de sso/ : le secret doit être RIGOUREUSEMENT le\n"
              . "   même partout, sinon les jetons ne sont pas reconnus. */\n"
              . 'return ' . var_export($conf, true) . ";\n";

        if (@file_put_contents(SECRET_FILE, $code, LOCK_EX) === false) {
            $err = "Impossible d’écrire sso/secret.local.php (droits du dossier). "
                 . "Crée-le toi-même avec ce contenu :\n\n" . $code;
        } else {
            if (function_exists('opcache_invalidate')) @opcache_invalidate(SECRET_FILE, true);

            /* Premier compte administrateur, écrit explicitement : ne pas dépendre
               de l'amorçage implicite pour une opération qui peut verrouiller. */
            luvid_account_put([
                'email'     => $f['email'],
                'name'      => $conf['local_user']['name'],
                'pass_hash' => password_hash($mdp, PASSWORD_DEFAULT),
                'roles'     => ['*' => LUVID_ROLE_ADMIN],
            ]);

            header('Location: install.php?ok=1');
            exit;
        }
    }
}

if (isset($_GET['ok'])) {
    $msg = 'Configuration enregistrée. Le SSO est actif et ton compte administrateur est créé.';
    $cfg = sso_config();
    $dejaPret = sso_ready();
}

$comptes = $dejaPret ? luvid_accounts_load() : [];
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Luvumbu ID — Configuration</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:#0e1526;color:#eaf0ff;padding:28px 20px;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;display:flex;justify-content:center}
  .wrap{width:100%;max-width:620px}
  h1{font-size:1.3rem;margin:0 0 6px}.brand{color:#5b8cff}
  .sub{color:#9fb0d0;font-size:.9rem;margin:0 0 22px;line-height:1.55}
  .panel{background:#151d31;border:1px solid #2a3450;border-radius:14px;padding:22px;margin-bottom:18px}
  h2{font-size:1rem;margin:0 0 14px;color:#cfe0ff}
  .msg{padding:11px 13px;border-radius:9px;font-size:.87rem;margin-bottom:16px;white-space:pre-wrap;line-height:1.5}
  .msg.ok{background:rgba(62,201,138,.13);border:1px solid #3ec98a;color:#a8f0cd}
  .msg.ko{background:rgba(255,93,108,.12);border:1px solid #ff5d6c;color:#ffb0b8}
  .msg.warn{background:rgba(255,184,77,.12);border:1px solid #ffb84d;color:#ffca7a}
  label{display:block;font-size:.8rem;color:#9fb0d0;margin:15px 0 5px}
  input{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #2a3450;background:#0e1526;
        color:#eaf0ff;font-size:14px;font-family:inherit}
  input:focus{outline:none;border-color:#5b8cff}
  .hint{color:#6e7a99;font-size:.79rem;margin-top:5px;line-height:1.5}
  button{margin-top:22px;width:100%;padding:11px;border-radius:8px;border:1px solid #3ec98a;
         background:#3ec98a;color:#062b1a;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit}
  button:hover{background:#5adba0}
  table{width:100%;border-collapse:collapse;font-size:.86rem}
  td{padding:7px 4px;border-bottom:1px solid #21294a}
  td:first-child{color:#8b98bd;width:45%}
  .ok{color:#7ee2a8}.ko{color:#ffb0b8}
  a{color:#8fb0ff}
  .go{display:block;text-align:center;margin-top:14px;padding:12px;border-radius:8px;
      background:#5b8cff;color:#fff;text-decoration:none;font-weight:600}
  code{background:#0e1526;border:1px solid #2a3450;border-radius:5px;padding:1px 5px;font-size:.85em}
</style>
</head>
<body>
<div class="wrap">
  <h1>Luvumbu <span class="brand">ID</span> — Configuration</h1>
  <p class="sub">Écrit <code>sso/secret.local.php</code> sur le serveur — le fichier que Git ne déploie jamais.</p>

  <?php if ($msg): ?><div class="msg ok"><?= $h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg ko"><?= $h($err) ?></div><?php endif; ?>

  <div class="panel">
    <h2>État</h2>
    <table>
      <tr><td>Clé de signature</td>
          <td class="<?= $dejaPret ? 'ok' : 'ko' ?>"><?= $dejaPret ? 'définie' : 'absente — SSO inactif' ?></td></tr>
      <tr><td>Connexion Google</td>
          <td class="<?= $cfg['google_client_id'] !== '' ? 'ok' : '' ?>">
            <?= $cfg['google_client_id'] !== '' ? 'ID client renseigné' : 'non configurée (e-mail + mot de passe seulement)' ?></td></tr>
      <tr><td>Comptes dans l'annuaire</td>
          <td class="<?= $comptes ? 'ok' : '' ?>"><?= $comptes ? count($comptes) . ' compte(s)' : '—' ?></td></tr>
    </table>
  </div>

  <?php if ($dejaPret): ?>
    <div class="panel">
      <h2>Le SSO est actif</h2>
      <a class="go" href="index.php">Ouvrir le hub →</a>
      <a class="go" href="accounts_admin.php" style="background:#2a3450">Gérer les comptes</a>
    </div>
    <div class="msg warn">Remplacer la clé ci-dessous déconnecterait tout le monde immédiatement : tous les jetons déjà émis deviendraient invalides.</div>
  <?php else: ?>
    <div class="msg warn">Dès que la clé sera écrite, <code>admin.php</code> et le gestionnaire cesseront d'accepter leur formulaire de secours et passeront par le hub. Le compte ci-dessous est donc <b>obligatoire</b> : sans lui, plus personne n'entre.</div>
  <?php endif; ?>

  <div class="panel">
    <h2><?= $dejaPret ? 'Modifier la configuration' : 'Configurer' ?></h2>
    <form method="post" autocomplete="off">

      <label for="secret">Clé de signature</label>
      <input type="text" id="secret" name="secret" value="<?= $h($f['secret']) ?>"
             placeholder="laisse vide pour en générer une automatiquement">
      <p class="hint">64 caractères hexadécimaux. À laisser vide la première fois. Ne la remplis à la
        main que pour reprendre <b>à l'identique</b> la clé d'un autre serveur (bokonzi.com) : sans
        clé commune, les jetons d'un hub ne sont pas reconnus par l'autre.</p>

      <label for="email">E-mail du compte administrateur</label>
      <input type="email" id="email" name="email" value="<?= $h($f['email']) ?>" required>

      <label for="nom">Nom affiché</label>
      <input type="text" id="nom" name="nom" value="<?= $h($f['nom']) ?>" placeholder="Luvumbu">

      <label for="mdp">Mot de passe de ce compte</label>
      <input type="password" id="mdp" name="mdp" required minlength="8">
      <p class="hint">8 caractères minimum. C'est avec lui que tu te connecteras au hub, et c'est aussi
        la porte de secours si la connexion Google tombe en panne.</p>

      <label for="client_id">ID client Google <span style="color:#6e7a99">(facultatif)</span></label>
      <input type="text" id="client_id" name="client_id" value="<?= $h($f['client_id']) ?>"
             placeholder="…apps.googleusercontent.com">
      <p class="hint">Sans lui, le bouton Google ne s'affiche pas et seule la connexion par mot de passe
        fonctionne. La console Google doit autoriser <code><?= $h((sso_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '')) ?></code>
        comme origine JavaScript.</p>

      <label for="domaine">Domaine du cookie <span style="color:#6e7a99">(facultatif)</span></label>
      <input type="text" id="domaine" name="domaine" value="<?= $h($f['domaine']) ?>" placeholder="vide = hôte courant">
      <p class="hint">Vide convient dans presque tous les cas. Mets <code>.luvumbu.com</code> uniquement
        pour partager la session entre sous-domaines.</p>

      <button type="submit" name="enregistrer" value="1">
        <?= $dejaPret ? 'Enregistrer les modifications' : 'Activer la connexion unique' ?>
      </button>
    </form>
  </div>

  <p class="sub"><a href="../etat.php">← État du déploiement</a></p>
</div>
</body>
</html>
