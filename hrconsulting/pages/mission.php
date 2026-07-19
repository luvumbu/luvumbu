<?php
require_once __DIR__ . '/../config/bdd.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . 'pages/missions.php');
    exit;
}

$stmt = $bdd->prepare(
    'SELECT m.*, u.user_name AS recruteur_nom, u.user_email AS recruteur_email, u.user_telephone AS recruteur_tel
     FROM mission m
     LEFT JOIN users u ON u.user_id = m.mission_id_user
     WHERE m.mission_id = :id'
);
$stmt->execute(['id' => $id]);
$mission = $stmt->fetch();

if (!$mission) {
    flash_set('error', "Cette annonce n'existe pas ou a été supprimée.");
    header('Location: ' . BASE_URL . 'pages/missions.php');
    exit;
}

$deja_postule = false;
if (est_freelance()) {
    $c = $bdd->prepare('SELECT candidature_id FROM candidature WHERE candidature_id_mission = :mid AND candidature_id_user = :uid');
    $c->execute(['mid' => $id, 'uid' => $_SESSION['user_id']]);
    $deja_postule = (bool)$c->fetch();
}

$est_proprio = est_connecte() && (int)$_SESSION['user_id'] === (int)$mission['mission_id_user'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title><?= htmlspecialchars($mission['mission_titre_mission']) ?> - HR Consulting</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <div class="mission-detail">
        <a href="<?= BASE_URL ?>pages/missions.php" class="back-link">&larr; Retour aux annonces</a>

        <div class="mission-detail-card">
            <h1><?= htmlspecialchars($mission['mission_titre_mission']) ?></h1>

            <div class="mission-meta">
                <span class="badge badge-recruteur"><?= htmlspecialchars($mission['recruteur_nom'] ?? 'Recruteur') ?></span>
                <?php if (!empty($mission['mission_ville'])): ?>
                    <span class="badge"><?= htmlspecialchars($mission['mission_ville']) ?></span>
                <?php endif; ?>
                <?php if (!empty($mission['mission_type_contrat'])): ?>
                    <span class="badge badge-contrat"><?= htmlspecialchars($mission['mission_type_contrat']) ?></span>
                <?php endif; ?>
                <?php if (!empty($mission['mission_salaire'])): ?>
                    <span class="badge badge-salaire"><?= htmlspecialchars($mission['mission_salaire']) ?></span>
                <?php endif; ?>
            </div>

            <div class="mission-section">
                <h3>Description du poste</h3>
                <p class="mission-description-full"><?= nl2br(htmlspecialchars($mission['mission_description'])) ?></p>
            </div>

            <?php if (!empty($mission['mission_technologie'])): ?>
                <div class="mission-section">
                    <h3>Technologies</h3>
                    <p><?= htmlspecialchars($mission['mission_technologie']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($mission['mission_profil'])): ?>
                <div class="mission-section">
                    <h3>Profil recherché</h3>
                    <p><?= htmlspecialchars($mission['mission_profil']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($mission['mission_niveau_etudes'])): ?>
                <div class="mission-section">
                    <h3>Niveau d'études</h3>
                    <p><?= htmlspecialchars($mission['mission_niveau_etudes']) ?></p>
                </div>
            <?php endif; ?>

            <div class="mission-date">Publié le <?= date('d/m/Y', strtotime($mission['mission_date_up'])) ?></div>

            <div class="mission-actions">
                <?php if ($est_proprio): ?>
                    <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-primary">Gérer cette annonce</a>
                <?php elseif (!est_connecte()): ?>
                    <a href="<?= BASE_URL ?>pages/connexion.php" class="btn btn-primary">Connectez-vous pour postuler</a>
                <?php elseif (est_freelance()): ?>
                    <?php if ($deja_postule): ?>
                        <div class="alert alert-success">Vous avez déjà postulé à cette annonce.</div>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>pages/postuler.php?id=<?= (int)$mission['mission_id'] ?>" class="btn btn-success">Postuler à cette offre</a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">Seuls les freelances peuvent postuler à une annonce.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
