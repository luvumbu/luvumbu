<?php
$stmt_home = $bdd->query(
    'SELECT m.*, u.user_name AS recruteur_nom
     FROM mission m
     LEFT JOIN users u ON u.user_id = m.mission_id_user
     ORDER BY m.mission_date_up DESC LIMIT 6'
);
$dernieres_missions = $stmt_home->fetchAll();
?>

<div class="hero">
    <div class="hero-overlay">
        <h1>Trouvez votre prochaine mission</h1>
        <p>HR Consulting met en relation freelances et recruteurs depuis 2016.</p>
        <form method="GET" action="<?= BASE_URL ?>pages/missions.php" class="hero-search">
            <input type="text" name="q" placeholder="Poste, technologie, mot-clé...">
            <input type="text" name="ville" placeholder="Ville">
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </form>
        <div class="hero-cta">
            <?php if (!est_connecte()): ?>
                <a href="<?= BASE_URL ?>pages/inscription.php" class="btn btn-success">Créer un compte gratuit</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>pages/missions.php" class="btn btn-secondary">Voir toutes les annonces</a>
        </div>
    </div>
</div>

<section class="block-quisommesnous" id="quisommesnous">
    <div class="container">
        <h2>Qui sommes-nous ?</h2>
        <p><?= nl2br(htmlspecialchars(app_param('description', APP_DESCRIPTION_DEFAUT))) ?></p>
    </div>
</section>

<section class="block-recents">
    <div class="container">
        <h2>Dernières annonces</h2>
        <?php if (empty($dernieres_missions)): ?>
            <p class="empty-state">Aucune annonce pour le moment.</p>
        <?php else: ?>
            <div class="grid-missions">
                <?php foreach ($dernieres_missions as $m): ?>
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
                        </div>
                        <p class="mission-extrait"><?= htmlspecialchars(mb_substr($m['mission_description'], 0, 160)) ?>...</p>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="text-center" style="margin-top:30px;">
                <a href="<?= BASE_URL ?>pages/missions.php" class="btn btn-primary">Voir toutes les annonces</a>
            </div>
        <?php endif; ?>
    </div>
</section>
