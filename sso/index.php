<?php
/* ═══════════════════════════════════════════════════════════════════════
   LUVUMBU ID — HUB de connexion (fournisseur d'identité).
   Flux : une app redirige ici (?app=NOM&return=URL) → l'utilisateur se
   connecte avec Google → on émet un JWT signé + cookie partagé → on
   renvoie vers l'app avec ?sso=<jwt>. L'app vérifie le jeton (secret partagé).
   ═══════════════════════════════════════════════════════════════════════ */
require __DIR__ . '/lib.php';

$cfg = sso_config();
$CID = (string)$cfg['google_client_id'];

/* Hôtes de retour autorisés (anti open-redirect) */
$ALLOWED_HOSTS = ['luvumbu.com', 'www.luvumbu.com', 'bokonzi.com', 'www.bokonzi.com', 'localhost', '127.0.0.1'];

function sso_valid_return(string $url, array $hosts): string {
    if ($url === '') return '';
    $h = parse_url($url, PHP_URL_HOST);
    if ($h === null) {                       // URL relative → OK (même hôte)
        return (strpos($url, '/') === 0) ? $url : '';
    }
    return in_array(strtolower($h), $hosts, true) ? $url : '';
}

$app    = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_REQUEST['app'] ?? ''));
$return = sso_valid_return((string)($_REQUEST['return'] ?? ''), $ALLOWED_HOSTS);

function sso_go_back(string $return, string $jwt): void {
    if ($return === '') return;
    $sep = (strpos($return, '?') !== false) ? '&' : '?';
    header('Location: ' . $return . $sep . 'sso=' . urlencode($jwt));
    exit;
}

$err = '';

/* 1) Retour du bouton Google (POST credential) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    if (!sso_ready()) {
        $err = "SSO non configuré (secret manquant).";
    } else {
        $u = sso_google_verify((string)$_POST['credential']);
        if ($u) {
            $jwt = sso_jwt_issue([
                'email'   => $u['email'], 'name' => $u['name'],
                'sub'     => $u['sub'],   'picture' => $u['picture'],
            ]);
            sso_cookie_set($jwt);
            sso_go_back($return, $jwt);
            header('Location: index.php'); exit;      // pas de return : on reste sur le hub
        }
        $err = "Connexion Google refusée.";
    }
}

/* 1b) Connexion par MOT DE PASSE (sans Google) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pw'])) {
    $cfgPw = (string)($cfg['password'] ?? '');
    if ($cfgPw !== '' && hash_equals($cfgPw, trim((string)$_POST['login_pw']))) {
        $lu  = $cfg['local_user'] ?? ['email' => 'user@luvumbu', 'name' => 'Utilisateur'];
        $jwt = sso_jwt_issue(['email' => strtolower((string)$lu['email']), 'name' => (string)$lu['name'], 'sub' => 'local', 'picture' => '']);
        sso_cookie_set($jwt);
        sso_go_back($return, $jwt);
        header('Location: index.php'); exit;
    }
    $err = 'Mot de passe incorrect.';
}

/* 2) Déjà connecté (cookie partagé) → on renvoie directement à l'app */
$cur = sso_current();
if ($cur && $return !== '') {
    // ré-émet un jeton frais pour l'app (même identité)
    $jwt = sso_jwt_issue(['email' => $cur['email'] ?? '', 'name' => $cur['name'] ?? '',
                          'sub' => $cur['sub'] ?? '', 'picture' => $cur['picture'] ?? '']);
    sso_go_back($return, $jwt);
}

function he($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Luvumbu ID — Connexion</title>
<?php if ($CID !== ''): ?><script src="https://accounts.google.com/gsi/client" async defer></script><?php endif; ?>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0e1526;color:#eaf0ff;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:20px}
  .card{background:#151d31;border:1px solid #2a3450;border-radius:18px;padding:30px;max-width:400px;width:100%;
        text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  h1{margin:0 0 4px;font-size:1.4rem}.brand{color:#5b8cff}
  .sub{color:#9fb0d0;font-size:.9rem;margin:0 0 22px}
  .err{background:rgba(255,93,108,.12);border:1px solid #ff5d6c;color:#ffb0b8;padding:10px;border-radius:9px;font-size:.85rem;margin-bottom:14px}
  .who{background:#1b2540;border:1px solid #2a3450;border-radius:12px;padding:14px;margin-bottom:16px}
  .who b{display:block}.who small{color:#9fb0d0}
  .gwrap{display:flex;justify-content:center;margin:10px 0}
  a.logout{color:#9fb0d0;font-size:.85rem}
  .warn{color:#ffb84d;font-size:.82rem;margin-top:14px}
  .orsep{color:#9fb0d0;font-size:.78rem;margin:14px 0}
  .pwform input{width:100%;padding:11px;border-radius:9px;border:1px solid #2a3450;background:#1b2540;color:#eaf0ff;margin-bottom:8px;font-size:14px}
  .pwform button{width:100%;padding:11px;border-radius:9px;border:1px solid #5b8cff;background:#5b8cff;color:#fff;font-weight:600;cursor:pointer;font-size:14px}
  .pwform button:hover{background:#3a6bff}
</style>
</head>
<body>
  <div class="card">
    <h1>Luvumbu <span class="brand">ID</span></h1>
    <p class="sub">Connexion unique à toutes les applications<?= $app ? ' · <b>'.he($app).'</b>' : '' ?></p>

    <?php if ($err): ?><div class="err"><?= he($err) ?></div><?php endif; ?>

    <?php if ($cur): ?>
      <div class="who">
        <b><?= he($cur['name'] ?? '') ?></b>
        <small><?= he($cur['email'] ?? '') ?></small>
      </div>
      <?php if ($return !== ''): ?>
        <a href="<?= he($return) ?>">Continuer →</a>
      <?php else: ?>
        <p class="sub">Tu es connecté partout. </p>
      <?php endif; ?>
      <p><a class="logout" href="logout.php<?= $return ? '?return='.urlencode($return) : '' ?>">Se déconnecter</a></p>
    <?php else: ?>
      <?php if ($CID !== ''): ?>
        <div id="g_id_onload" data-client_id="<?= he($CID) ?>" data-callback="onGoogle" data-auto_prompt="false"></div>
        <div class="gwrap"><div class="g_id_signin" data-type="standard" data-shape="pill" data-text="signin_with" data-size="large"></div></div>
        <form id="ssoForm" method="post" style="display:none">
          <input type="hidden" name="credential" id="cred">
          <input type="hidden" name="app" value="<?= he($app) ?>">
          <input type="hidden" name="return" value="<?= he($return) ?>">
        </form>
        <script>function onGoogle(resp){ document.getElementById('cred').value=resp.credential; document.getElementById('ssoForm').submit(); }</script>
        <div class="orsep">— ou avec un mot de passe —</div>
      <?php endif; ?>
      <form method="post" class="pwform">
        <input type="password" name="login_pw" placeholder="Mot de passe" autofocus autocomplete="current-password">
        <input type="hidden" name="app" value="<?= he($app) ?>">
        <input type="hidden" name="return" value="<?= he($return) ?>">
        <button type="submit">Se connecter</button>
      </form>
    <?php endif; ?>

    <?php if (!sso_ready()): ?><div class="warn">⚠️ Secret SSO non défini — voir <code>sso/README.md</code>.</div><?php endif; ?>
  </div>
</body>
</html>
