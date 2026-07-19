<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Destination après inscription : uniquement le retour sur un questionnaire,
// avec un identifiant numérique. Jamais d'URL arbitraire (open redirect).
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
$qid  = (int)($_GET['qid'] ?? $_POST['qid'] ?? 0);
$target = ($next === 'quiz' && $qid > 0) ? 'pages/quiz.php?id=' . $qid : 'index.php';

if (is_logged_in()) {
    redirect(base_url($target));
}

$errors = [];
$nom = $prenom = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }
    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($nom === '' || $prenom === '') $errors[] = 'Nom et prénom sont obligatoires.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (strlen($password) < 6) $errors[] = 'Le mot de passe doit faire au moins 6 caractères.';
    if ($password !== $confirm) $errors[] = 'Les mots de passe ne correspondent pas.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Cet email est déjà utilisé.';
        } else {
            $id = register_user($nom, $prenom, $email, $password);
            $_SESSION['user_id'] = $id;
            flash_set('success', 'Bienvenue ' . $prenom . ' !');
            redirect(base_url($target));
        }
    }
}

$pageTitle = 'Inscription';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card">
    <h1>Créer un compte</h1>
    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if ($next === 'quiz' && $qid > 0): ?>
            <input type="hidden" name="next" value="quiz">
            <input type="hidden" name="qid" value="<?= (int)$qid ?>">
        <?php endif; ?>
        <label>Prénom
            <input type="text" name="prenom" value="<?= e($prenom) ?>" required>
        </label>
        <label>Nom
            <input type="text" name="nom" value="<?= e($nom) ?>" required>
        </label>
        <label>Email
            <input type="email" name="email" value="<?= e($email) ?>" required>
        </label>
        <label>Mot de passe
            <input type="password" name="password" required minlength="6">
        </label>
        <label>Confirmer le mot de passe
            <input type="password" name="confirm" required minlength="6">
        </label>
        <button type="submit" class="btn-primary">S'inscrire</button>
    </form>
    <?php $logUrl = base_url('pages/login.php') . ($next === 'quiz' && $qid > 0 ? '?next=quiz&qid=' . (int)$qid : ''); ?>
    <p class="muted">Déjà un compte ? <a href="<?= e($logUrl) ?>">Se connecter</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
