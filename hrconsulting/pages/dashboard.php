<?php
require_once __DIR__ . '/../config/bdd.php';

if (!est_connecte()) {
    flash_set('error', 'Vous devez être connecté pour accéder à votre espace.');
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

$user = utilisateur_actuel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (est_recruteur() && isset($_POST['supprimer_mission'])) {
        $mid = (int)$_POST['mission_id'];
        $d = $bdd->prepare('DELETE FROM mission WHERE mission_id = :id AND mission_id_user = :uid');
        $d->execute(['id' => $mid, 'uid' => $user['id']]);
        $bdd->prepare('DELETE FROM candidature WHERE candidature_id_mission = :id')->execute(['id' => $mid]);
        flash_set('success', 'Annonce supprimée.');
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }

    if (est_recruteur() && isset($_POST['changer_statut'])) {
        $cid     = (int)$_POST['candidature_id'];
        $statut  = $_POST['statut'] ?? 'en_attente';
        if (in_array($statut, ['en_attente','acceptee','refusee'], true)) {
            $check = $bdd->prepare(
                'SELECT c.candidature_id FROM candidature c
                 JOIN mission m ON m.mission_id = c.candidature_id_mission
                 WHERE c.candidature_id = :cid AND m.mission_id_user = :uid'
            );
            $check->execute(['cid' => $cid, 'uid' => $user['id']]);
            if ($check->fetch()) {
                $bdd->prepare('UPDATE candidature SET candidature_statut = :s WHERE candidature_id = :cid')
                    ->execute(['s' => $statut, 'cid' => $cid]);
                flash_set('success', 'Statut de la candidature mis à jour.');
            }
        }
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}

if (est_recruteur()) {
    $stmt = $bdd->prepare(
        'SELECT m.*,
                (SELECT COUNT(*) FROM candidature c WHERE c.candidature_id_mission = m.mission_id) AS nb_candidatures
         FROM mission m
         WHERE m.mission_id_user = :uid
         ORDER BY m.mission_date_up DESC'
    );
    $stmt->execute(['uid' => $user['id']]);
    $mes_missions = $stmt->fetchAll();

    $stmt2 = $bdd->prepare(
        'SELECT c.*, m.mission_titre_mission, m.mission_id,
                u.user_name AS freelance_nom, u.user_email AS freelance_email
         FROM candidature c
         JOIN mission m ON m.mission_id = c.candidature_id_mission
         JOIN users u ON u.user_id = c.candidature_id_user
         WHERE m.mission_id_user = :uid
         ORDER BY c.candidature_date DESC'
    );
    $stmt2->execute(['uid' => $user['id']]);
    $candidatures_recues = $stmt2->fetchAll();
} else {
    $stmt = $bdd->prepare(
        'SELECT c.*, m.mission_titre_mission, m.mission_ville, m.mission_type_contrat,
                u.user_name AS recruteur_nom
         FROM candidature c
         JOIN mission m ON m.mission_id = c.candidature_id_mission
         JOIN users u ON u.user_id = m.mission_id_user
         WHERE c.candidature_id_user = :uid
         ORDER BY c.candidature_date DESC'
    );
    $stmt->execute(['uid' => $user['id']]);
    $mes_candidatures = $stmt->fetchAll();
}

$statut_labels = [
    'en_attente' => 'En attente',
    'acceptee'   => 'Acceptée',
    'refusee'    => 'Refusée',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Mon espace - HR Consulting</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <div class="dashboard">
        <h2>Bonjour <?= htmlspecialchars($user['name']) ?></h2>
        <p class="user-role">Compte : <strong><?= htmlspecialchars($user['jesuis']) ?></strong></p>

        <?php if (est_recruteur()): ?>
            <section class="dash-section">
                <div class="dash-section-head">
                    <h3>Mes annonces (<?= count($mes_missions) ?>)</h3>
                    <a href="<?= BASE_URL ?>pages/poster_mission.php" class="btn btn-success">+ Nouvelle annonce</a>
                </div>

                <?php if (empty($mes_missions)): ?>
                    <p class="empty-state">Vous n'avez pas encore publié d'annonce.</p>
                <?php else: ?>
                    <table class="dash-table">
                        <thead>
                            <tr><th>Titre</th><th>Ville</th><th>Contrat</th><th>Candidatures</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mes_missions as $m): ?>
                            <tr>
                                <td><a href="<?= BASE_URL ?>pages/mission.php?id=<?= (int)$m['mission_id'] ?>"><?= htmlspecialchars($m['mission_titre_mission']) ?></a></td>
                                <td><?= htmlspecialchars($m['mission_ville']) ?></td>
                                <td><?= htmlspecialchars($m['mission_type_contrat'] ?? '-') ?></td>
                                <td><strong><?= (int)$m['nb_candidatures'] ?></strong></td>
                                <td>
                                    <a href="<?= BASE_URL ?>pages/poster_mission.php?edit=<?= (int)$m['mission_id'] ?>" class="btn btn-sm">Modifier</a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette annonce ?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="mission_id" value="<?= (int)$m['mission_id'] ?>">
                                        <button type="submit" name="supprimer_mission" class="btn btn-sm btn-danger">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="dash-section">
                <h3>Candidatures reçues (<?= count($candidatures_recues) ?>)</h3>
                <?php if (empty($candidatures_recues)): ?>
                    <p class="empty-state">Aucune candidature pour l'instant.</p>
                <?php else: ?>
                    <?php foreach ($candidatures_recues as $c): ?>
                        <div class="candidature-card statut-<?= htmlspecialchars($c['candidature_statut']) ?>">
                            <div class="cand-head">
                                <strong><?= htmlspecialchars($c['freelance_nom']) ?></strong>
                                <span class="badge badge-statut"><?= $statut_labels[$c['candidature_statut']] ?></span>
                            </div>
                            <div class="cand-info">
                                Pour : <a href="<?= BASE_URL ?>pages/mission.php?id=<?= (int)$c['mission_id'] ?>"><?= htmlspecialchars($c['mission_titre_mission']) ?></a>
                                &middot; Email : <a href="mailto:<?= htmlspecialchars($c['freelance_email']) ?>"><?= htmlspecialchars($c['freelance_email']) ?></a>
                                &middot; Le <?= date('d/m/Y H:i', strtotime($c['candidature_date'])) ?>
                            </div>
                            <p class="cand-message"><?= nl2br(htmlspecialchars($c['candidature_message'])) ?></p>
                            <form method="POST" class="cand-actions">
                                <?= csrf_field() ?>
                                <input type="hidden" name="candidature_id" value="<?= (int)$c['candidature_id'] ?>">
                                <select name="statut">
                                    <?php foreach ($statut_labels as $val => $lab): ?>
                                        <option value="<?= $val ?>" <?= $c['candidature_statut']===$val ? 'selected' : '' ?>><?= $lab ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="changer_statut" class="btn btn-sm btn-primary">Mettre à jour</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <section class="dash-section">
                <h3>Mes candidatures (<?= count($mes_candidatures) ?>)</h3>
                <?php if (empty($mes_candidatures)): ?>
                    <p class="empty-state">Vous n'avez pas encore postulé à d'annonces. <a href="<?= BASE_URL ?>pages/missions.php">Voir les annonces</a></p>
                <?php else: ?>
                    <?php foreach ($mes_candidatures as $c): ?>
                        <div class="candidature-card statut-<?= htmlspecialchars($c['candidature_statut']) ?>">
                            <div class="cand-head">
                                <a href="<?= BASE_URL ?>pages/mission.php?id=<?= (int)$c['candidature_id_mission'] ?>"><strong><?= htmlspecialchars($c['mission_titre_mission']) ?></strong></a>
                                <span class="badge badge-statut"><?= $statut_labels[$c['candidature_statut']] ?></span>
                            </div>
                            <div class="cand-info">
                                <?= htmlspecialchars($c['recruteur_nom']) ?>
                                &middot; <?= htmlspecialchars($c['mission_ville']) ?>
                                &middot; <?= htmlspecialchars($c['mission_type_contrat'] ?? '') ?>
                                &middot; Postulé le <?= date('d/m/Y', strtotime($c['candidature_date'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
