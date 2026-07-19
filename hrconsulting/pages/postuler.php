<?php
require_once __DIR__ . '/../config/bdd.php';

if (!est_freelance()) {
    flash_set('error', 'Seuls les freelances peuvent postuler.');
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

$mission_id = (int)($_GET['id'] ?? 0);
$stmt = $bdd->prepare('SELECT * FROM mission WHERE mission_id = :id');
$stmt->execute(['id' => $mission_id]);
$mission = $stmt->fetch();

if (!$mission) {
    flash_set('error', 'Cette annonce n\'existe pas.');
    header('Location: ' . BASE_URL . 'pages/missions.php');
    exit;
}

$check = $bdd->prepare('SELECT candidature_id FROM candidature WHERE candidature_id_mission = :mid AND candidature_id_user = :uid');
$check->execute(['mid' => $mission_id, 'uid' => $_SESSION['user_id']]);
if ($check->fetch()) {
    flash_set('error', 'Vous avez déjà postulé à cette annonce.');
    header('Location: ' . BASE_URL . 'pages/mission.php?id=' . $mission_id);
    exit;
}

$erreurs = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $message = trim($_POST['message'] ?? '');
    if (strlen($message) < 20) {
        $erreurs[] = 'Votre message de motivation doit faire au moins 20 caractères.';
    }
    if (empty($erreurs)) {
        $req = $bdd->prepare(
            'INSERT INTO candidature(candidature_id_mission, candidature_id_user, candidature_message)
             VALUES(:mid, :uid, :msg)'
        );
        $req->execute([
            'mid' => $mission_id,
            'uid' => $_SESSION['user_id'],
            'msg' => $message,
        ]);
        flash_set('success', 'Votre candidature a bien été envoyée !');
        header('Location: ' . BASE_URL . 'pages/mission.php?id=' . $mission_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Postuler - HR Consulting</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <div class="form-page">
        <a href="<?= BASE_URL ?>pages/mission.php?id=<?= (int)$mission_id ?>" class="back-link">&larr; Retour à l'annonce</a>
        <h2>Postuler à : <?= htmlspecialchars($mission['mission_titre_mission']) ?></h2>

        <?php if (!empty($erreurs)): ?>
            <div class="alert alert-error">
                <?php foreach ($erreurs as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="big-form">
            <?= csrf_field() ?>
            <label>Message de motivation *
                <textarea name="message" rows="10" placeholder="Présentez-vous et expliquez pourquoi ce poste vous intéresse..." required><?= htmlspecialchars($message) ?></textarea>
            </label>
            <button type="submit" class="btn btn-success">Envoyer ma candidature</button>
        </form>
    </div>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
