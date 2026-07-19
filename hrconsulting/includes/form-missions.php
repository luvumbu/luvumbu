<?php
require_once __DIR__ . '/../config/bdd.php';

$q_recherche = trim($_GET['q'] ?? '');
$q_ville     = trim($_GET['ville'] ?? '');
$q_contrat   = trim($_GET['contrat'] ?? '');

$sql = 'SELECT m.*, u.user_name AS recruteur_nom
        FROM mission m
        LEFT JOIN users u ON u.user_id = m.mission_id_user
        WHERE 1=1';
$params = [];

if ($q_recherche !== '') {
    $sql .= ' AND (m.mission_titre_mission LIKE :q1 OR m.mission_description LIKE :q2 OR m.mission_technologie LIKE :q3)';
    $like = '%'.$q_recherche.'%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}
if ($q_ville !== '') {
    $sql .= ' AND m.mission_ville LIKE :ville';
    $params['ville'] = '%'.$q_ville.'%';
}
if ($q_contrat !== '') {
    $sql .= ' AND m.mission_type_contrat = :contrat';
    $params['contrat'] = $q_contrat;
}
$sql .= ' ORDER BY m.mission_date_up DESC';

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$missions = $stmt->fetchAll();
?>

<div class="missions-page">
    <div class="search-bar">
        <form method="GET" action="<?= BASE_URL ?>pages/missions.php" class="search-form">
            <input type="text" name="q" placeholder="Poste, technologie, mot-clé..." value="<?= htmlspecialchars($q_recherche) ?>">
            <input type="text" name="ville" placeholder="Ville" value="<?= htmlspecialchars($q_ville) ?>">
            <select name="contrat">
                <option value="">Tous contrats</option>
                <?php foreach (['CDI','CDD','Freelance','Stage','Alternance'] as $c): ?>
                    <option value="<?= $c ?>" <?= $q_contrat===$c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </form>
    </div>

    <div class="missions-count">
        <strong><?= count($missions) ?></strong> annonce(s) trouvée(s)
        <?php if (est_recruteur()): ?>
            <a href="<?= BASE_URL ?>pages/poster_mission.php" class="btn btn-success btn-right">+ Publier une annonce</a>
        <?php endif; ?>
    </div>

    <div class="missions-list">
        <?php if (empty($missions)): ?>
            <div class="empty-state">Aucune annonce ne correspond à votre recherche.</div>
        <?php endif; ?>

        <?php foreach ($missions as $m): ?>
            <a href="<?= BASE_URL ?>pages/mission.php?id=<?= (int)$m['mission_id'] ?>" class="mission-card">
                <h3 class="mission-titre"><?= htmlspecialchars($m['mission_titre_mission']) ?></h3>
                <div class="mission-meta">
                    <span class="badge"><?= htmlspecialchars($m['recruteur_nom'] ?? 'Recruteur') ?></span>
                    <?php if (!empty($m['mission_ville'])): ?>
                        <span class="badge"><?= htmlspecialchars($m['mission_ville']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($m['mission_type_contrat'])): ?>
                        <span class="badge badge-contrat"><?= htmlspecialchars($m['mission_type_contrat']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($m['mission_salaire'])): ?>
                        <span class="badge badge-salaire"><?= htmlspecialchars($m['mission_salaire']) ?></span>
                    <?php endif; ?>
                </div>
                <p class="mission-extrait">
                    <?= htmlspecialchars(mb_substr($m['mission_description'], 0, 220)) ?><?= mb_strlen($m['mission_description']) > 220 ? '...' : '' ?>
                </p>
                <?php if (!empty($m['mission_technologie'])): ?>
                    <div class="mission-tech"><strong>Technologies :</strong> <?= htmlspecialchars($m['mission_technologie']) ?></div>
                <?php endif; ?>
                <div class="mission-date">Publié le <?= date('d/m/Y', strtotime($m['mission_date_up'])) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
