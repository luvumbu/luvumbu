<?php
// === Inscription web (création de compte depuis le navigateur) ===
//   https://luvumbu.com/web/register.php
// Même logique que l'app : protégée par le code d'inscription (= mot de passe de la
// base de données, DB_PASS), crée le compte en base puis ouvre la session web.

require __DIR__ . '/../lib/bootstrap.php';

Auth::startSession();
Auth::ensureSchema();

// Déjà connecté → on file à la galerie.
if (!empty($_SESSION['uid'])) {
    header('Location: gallery.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $invite   = $_POST['invite'] ?? '';

    if (DB_PASS === '' || !is_string($invite) || !hash_equals(DB_PASS, $invite)) {
        $error = "Code d'inscription invalide (mot de passe du serveur).";
    } elseif (!is_string($password) || $password !== $confirm) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $res = Auth::createAccount($username, is_string($password) ? $password : '');
        if ($res['ok']) {
            // Compte créé : on ouvre la session web et on redirige vers la galerie.
            session_regenerate_id(true);
            $_SESSION['uid']   = $res['uid'];
            $_SESSION['uname'] = $res['username'];
            header('Location: gallery.php');
            exit;
        }
        $error = $res['error'];
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <title>PhotoSync — Créer un compte</title>
    <style>
        body { font-family:system-ui,sans-serif; background:#0b1220; color:#e2e8f0; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
        .card { background:#16213a; padding:28px; border-radius:16px; width:320px; box-shadow:0 10px 30px rgba(0,0,0,.5); }
        h1 { font-size:20px; margin:0 0 4px; } p.sub { color:#8aa0bd; font-size:13px; margin:0 0 20px; }
        input { width:100%; box-sizing:border-box; padding:12px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#fff; font-size:15px; margin-top:10px; }
        button { width:100%; margin-top:14px; padding:12px; border:0; border-radius:10px; background:#16a34a; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
        .err { color:#f87171; font-size:13px; margin-top:10px; }
        .hint { color:#64748b; font-size:12px; margin-top:16px; text-align:center; }
        .hint a { color:#93c5fd; text-decoration:none; }
    </style>
</head>
<body>
<form class="card" method="post">
    <h1>🆕 PhotoSync</h1>
    <p class="sub">Créer ton compte</p>
    <input type="text" name="username" placeholder="Identifiant (3 car. min.)" value="<?= htmlspecialchars($username) ?>" autofocus required>
    <input type="password" name="password" placeholder="Mot de passe (4 car. min.)" required>
    <input type="password" name="confirm" placeholder="Confirmer le mot de passe" required>
    <input type="password" name="invite" placeholder="Code d'inscription (mot de passe du serveur)" required>
    <button type="submit">Créer mon compte</button>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="hint">Déjà un compte ? <a href="gallery.php">Se connecter</a></div>
</form>
</body>
</html>
