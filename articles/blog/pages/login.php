<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Destination apres connexion. Liste blanche : jamais d'URL arbitraire venant de
// l'utilisateur, sinon la page devient un tremplin de redirection (open redirect).
const LOGIN_NEXT = [
    'admin'    => 'pages/admin.php',
    'tokens'   => 'pages/api_tokens.php',
    'settings' => 'pages/settings.php',
    'quiz'     => 'pages/quiz.php',   // complété par ?id=<qid>, voir plus bas
];

// Connexion "base de donnees" : on accepte DB_USER (ou DB_NAME, identiques chez
// Hostinger) + DB_PASS. Ces identifiants donnent deja un acces total aux donnees,
// les accepter ici n'elargit pas la surface.
//
// Aucune limitation du nombre d'essais : DB_PASS est donc devinable par force brute
// depuis Internet. Le mot de passe doit rester long et aleatoire.

function dblogin_credentials_ok(string $user, string $pass): bool {
    // Le secret, c'est le mot de passe. Le nom d'utilisateur n'est pas verifie :
    // n'importe quelle variante (u..._m, u..._luvumbu, ...) est acceptee du moment
    // que le mot de passe correspond a DB_PASS. Comparaison a temps constant.
    if ($pass === '') return false;
    return hash_equals(DB_PASS, $pass);
}

$next   = (string)($_GET['next'] ?? $_POST['next'] ?? '');
$target = LOGIN_NEXT[$next] ?? 'index.php';
$isAdminMode = ($next === 'admin');

// Retour sur un questionnaire : seul un identifiant numérique est repris.
$qid = (int)($_GET['qid'] ?? $_POST['qid'] ?? 0);
if ($next === 'quiz') {
    if ($qid > 0) { $target .= '?id=' . $qid; }
    else          { $target = 'index.php'; }
}

if (is_logged_in()) {
    redirect(base_url($target));
}

$errors = [];
$email  = '';
$dbUser = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }

    if (empty($errors) && $isAdminMode) {
        // --- Connexion par identifiants de base de donnees ---
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';

        if (!dblogin_credentials_ok($dbUser, $dbPass)) {
            $errors[] = 'Identifiants de base de données incorrects.';
        } else {
            // Identifiants valides : on ouvre la session au nom du compte admin.
            $stmt = $pdo->query('SELECT id FROM users WHERE is_admin = 1 ORDER BY id ASC LIMIT 1');
            $adminId = $stmt->fetchColumn();
            if (!$adminId) {
                $errors[] = "Aucun compte administrateur n'existe dans la table users.";
            } else {
                session_regenerate_id(true); // evite la fixation de session
                $_SESSION['user_id'] = (int)$adminId;
                flash_set('success', 'Connexion administrateur réussie.');
                redirect(base_url($target));
            }
        }

    } elseif (empty($errors)) {
        // --- Connexion classique par email ---
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (login_user($email, $password)) {
            flash_set('success', 'Connexion réussie.');
            redirect(base_url($target));
        }
        $errors[] = 'Email ou mot de passe incorrect.';
    }
}

$pageTitle = $isAdminMode ? 'Connexion administrateur' : 'Connexion';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card">
    <?php if ($isAdminMode): ?>
        <h1>Se connecter à l'administration</h1>
        <p class="muted">
            Utilise les identifiants de la <strong>base de données</strong> —
            le nom d'utilisateur MySQL (par exemple <code>u489596434_m</code>) et son mot de passe.
        </p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" class="form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="next" value="admin">
            <label>Utilisateur de la base
                <input type="text" name="db_user" value="<?= e($dbUser) ?>" required
                       autocomplete="username" placeholder="u489596434_...">
            </label>
            <label>Mot de passe de la base
                <input type="password" name="db_pass" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn-primary">Se connecter</button>
        </form>

        <p class="muted" style="margin-top:18px;">
            <a href="<?= e(base_url('pages/login.php')) ?>">← Connexion par email</a>
        </p>
    <?php else: ?>
        <h1>Se connecter</h1>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" class="form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <?php if ($next === 'quiz'): ?>
                <input type="hidden" name="qid" value="<?= (int)$qid ?>">
            <?php endif; ?>
            <label>Email
                <input type="email" name="email" value="<?= e($email) ?>" required>
            </label>
            <label>Mot de passe
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn-primary">Se connecter</button>
        </form>

        <p style="margin-top:18px;">
            <a class="btn-secondary" href="<?= e(base_url('pages/login.php?next=admin')) ?>"
               style="display:inline-block; padding:10px 18px; border-radius:6px; border:1px solid rgba(0,0,0,0.15); text-decoration:none;">
                🔐 Administration
            </a>
        </p>

        <?php $regUrl = base_url('pages/register.php') . ($next === 'quiz' ? '?next=quiz&qid=' . (int)$qid : ''); ?>
        <p class="muted">Pas encore inscrit ? <a href="<?= e($regUrl) ?>">Créer un compte</a></p>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
