<?php
if (!function_exists('flash_get')) {
    require_once __DIR__ . '/../config/bdd.php';
}
$flash = flash_get();
?>
<div class="topbar">
    <a href="<?= BASE_URL ?>index.php" class="brand">
        <img src="<?= BASE_URL ?>assets/images/logohr.png" alt="HR Consulting" class="logo">
    </a>
    <nav class="topbar-right">
        <?php if (est_connecte()): ?>
            <span class="status-badge status-on">
                <span class="status-dot"></span>
                Connecté&nbsp;: <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
                <span class="status-role"><?= htmlspecialchars($_SESSION['user_jesuis']) ?><?= est_admin() ? ' · admin' : '' ?></span>
            </span>
            <a href="<?= BASE_URL ?>pages/dashboard.php" class="nav-link">Mon espace</a>
            <?php if (est_admin()): ?>
                <a href="<?= BASE_URL ?>admin/admin.php" class="nav-link nav-link-admin">Admin</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>pages/logout.php" class="nav-link">Déconnexion</a>
        <?php else: ?>
            <span class="status-badge status-off"><span class="status-dot"></span> Non connecté</span>
            <a href="<?= BASE_URL ?>pages/connexion.php" class="nav-link">Connexion</a>
            <a href="<?= BASE_URL ?>pages/inscription.php" class="nav-link nav-link-primary">Inscription</a>
            <a href="<?= BASE_URL ?>pages/connexion.php?admin=1" class="nav-link nav-link-admin">Admin</a>
        <?php endif; ?>
    </nav>
</div>
<nav class="mainnav">
    <a href="<?= BASE_URL ?>index.php" class="mainnav-link">Accueil</a>
    <a href="<?= BASE_URL ?>pages/missions.php" class="mainnav-link">Toutes les annonces</a>
    <?php if (est_recruteur()): ?>
        <a href="<?= BASE_URL ?>pages/poster_mission.php" class="mainnav-link">Publier une annonce</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>index.php#contact" class="mainnav-link">Contact</a>
</nav>

<?php if (!empty($flash)): ?>
    <div class="flash-zone">
        <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?>"><?= htmlspecialchars($f['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
