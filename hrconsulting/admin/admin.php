<?php
require_once __DIR__ . '/../config/bdd.php';

if (!est_admin()) {
    flash_set('error', 'Accès réservé aux administrateurs.');
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['supprimer_user'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)$_SESSION['user_id']) {
            $bdd->prepare('DELETE FROM candidature WHERE candidature_id_user = :uid')->execute(['uid' => $uid]);
            $missions_users = $bdd->prepare('SELECT mission_id FROM mission WHERE mission_id_user = :uid');
            $missions_users->execute(['uid' => $uid]);
            foreach ($missions_users->fetchAll() as $row) {
                $bdd->prepare('DELETE FROM candidature WHERE candidature_id_mission = :mid')->execute(['mid' => $row['mission_id']]);
            }
            $bdd->prepare('DELETE FROM mission WHERE mission_id_user = :uid')->execute(['uid' => $uid]);
            $bdd->prepare('DELETE FROM users WHERE user_id = :uid')->execute(['uid' => $uid]);
            flash_set('success', 'Utilisateur supprimé avec toutes ses données.');
        } else {
            flash_set('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        header('Location: ' . BASE_URL . 'admin/admin.php');
        exit;
    }

    if (isset($_POST['toggle_admin'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)$_SESSION['user_id']) {
            $bdd->prepare('UPDATE users SET user_is_admin = 1 - user_is_admin WHERE user_id = :uid')
                ->execute(['uid' => $uid]);
            flash_set('success', 'Statut admin modifié.');
        }
        header('Location: ' . BASE_URL . 'admin/admin.php');
        exit;
    }

    if (isset($_POST['supprimer_mission'])) {
        $mid = (int)$_POST['mission_id'];
        $bdd->prepare('DELETE FROM candidature WHERE candidature_id_mission = :mid')->execute(['mid' => $mid]);
        $bdd->prepare('DELETE FROM mission WHERE mission_id = :mid')->execute(['mid' => $mid]);
        flash_set('success', 'Annonce supprimée.');
        header('Location: ' . BASE_URL . 'admin/admin.php');
        exit;
    }

    if (isset($_POST['supprimer_candidature'])) {
        $cid = (int)$_POST['candidature_id'];
        $bdd->prepare('DELETE FROM candidature WHERE candidature_id = :cid')->execute(['cid' => $cid]);
        flash_set('success', 'Candidature supprimée.');
        header('Location: ' . BASE_URL . 'admin/admin.php');
        exit;
    }
}

$stats = [
    'users'         => (int)$bdd->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'recruteurs'    => (int)$bdd->query("SELECT COUNT(*) FROM users WHERE user_jesuis = 'recruteur'")->fetchColumn(),
    'freelances'    => (int)$bdd->query("SELECT COUNT(*) FROM users WHERE user_jesuis = 'freelance'")->fetchColumn(),
    'admins'        => (int)$bdd->query('SELECT COUNT(*) FROM users WHERE user_is_admin = 1')->fetchColumn(),
    'missions'      => (int)$bdd->query('SELECT COUNT(*) FROM mission')->fetchColumn(),
    'candidatures'  => (int)$bdd->query('SELECT COUNT(*) FROM candidature')->fetchColumn(),
];

$users = $bdd->query('SELECT * FROM users ORDER BY user_id DESC')->fetchAll();
$missions = $bdd->query(
    'SELECT m.*, u.user_name AS recruteur_nom,
            (SELECT COUNT(*) FROM candidature c WHERE c.candidature_id_mission = m.mission_id) AS nb_cand
     FROM mission m
     LEFT JOIN users u ON u.user_id = m.mission_id_user
     ORDER BY m.mission_date_up DESC'
)->fetchAll();
$candidatures = $bdd->query(
    'SELECT c.*, m.mission_titre_mission, u.user_name AS freelance_nom, u.user_email AS freelance_email
     FROM candidature c
     LEFT JOIN mission m ON m.mission_id = c.candidature_id_mission
     LEFT JOIN users u ON u.user_id = c.candidature_id_user
     ORDER BY c.candidature_date DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Administration - HR Consulting</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <div class="dashboard">
        <h2>Espace Administrateur</h2>
        <p class="user-role">Connecté en tant que <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
            &middot; <a href="<?= BASE_URL ?>admin/api.php">Clé API</a>
            &middot; <a href="<?= BASE_URL ?>admin/parametres.php">Paramètres</a></p>

        <section class="dash-section">
            <h3>Vue d'ensemble</h3>
            <div class="admin-stats">
                <div class="stat-card"><div class="stat-num"><?= $stats['users'] ?></div><div>Utilisateurs</div></div>
                <div class="stat-card"><div class="stat-num"><?= $stats['recruteurs'] ?></div><div>Recruteurs</div></div>
                <div class="stat-card"><div class="stat-num"><?= $stats['freelances'] ?></div><div>Freelances</div></div>
                <div class="stat-card"><div class="stat-num"><?= $stats['admins'] ?></div><div>Admins</div></div>
                <div class="stat-card"><div class="stat-num"><?= $stats['missions'] ?></div><div>Annonces</div></div>
                <div class="stat-card"><div class="stat-num"><?= $stats['candidatures'] ?></div><div>Candidatures</div></div>
            </div>
        </section>

        <section class="dash-section">
            <h3>Utilisateurs (<?= count($users) ?>)</h3>
            <?php if (empty($users)): ?>
                <p class="empty-state">Aucun utilisateur.</p>
            <?php else: ?>
                <table class="dash-table">
                    <thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Type</th><th>Admin</th><th>Inscrit le</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr<?= $u['user_is_admin'] ? ' style="background:#fff8e1"' : '' ?>>
                            <td>#<?= (int)$u['user_id'] ?></td>
                            <td><?= htmlspecialchars($u['user_name']) ?></td>
                            <td><?= htmlspecialchars($u['user_email']) ?></td>
                            <td><span class="badge"><?= htmlspecialchars($u['user_jesuis']) ?></span></td>
                            <td><?= $u['user_is_admin'] ? '<span class="badge badge-recruteur">ADMIN</span>' : '—' ?></td>
                            <td><?= date('d/m/Y', strtotime($u['user_update'])) ?></td>
                            <td>
                                <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                        <button type="submit" name="toggle_admin" class="btn btn-sm"><?= $u['user_is_admin'] ? 'Retirer admin' : 'Faire admin' ?></button>
                                    </form>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet utilisateur et toutes ses données ?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                        <button type="submit" name="supprimer_user" class="btn btn-sm btn-danger">Supprimer</button>
                                    </form>
                                <?php else: ?>
                                    <em>(vous-même)</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="dash-section">
            <h3>Annonces (<?= count($missions) ?>)</h3>
            <?php if (empty($missions)): ?>
                <p class="empty-state">Aucune annonce.</p>
            <?php else: ?>
                <table class="dash-table">
                    <thead><tr><th>ID</th><th>Titre</th><th>Recruteur</th><th>Ville</th><th>Contrat</th><th>Cand.</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($missions as $m): ?>
                        <tr>
                            <td>#<?= (int)$m['mission_id'] ?></td>
                            <td><a href="<?= BASE_URL ?>pages/mission.php?id=<?= (int)$m['mission_id'] ?>"><?= htmlspecialchars($m['mission_titre_mission']) ?></a></td>
                            <td><?= htmlspecialchars($m['recruteur_nom'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($m['mission_ville']) ?></td>
                            <td><?= htmlspecialchars($m['mission_type_contrat'] ?? '—') ?></td>
                            <td><strong><?= (int)$m['nb_cand'] ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($m['mission_date_up'])) ?></td>
                            <td>
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
            <h3>Candidatures (<?= count($candidatures) ?>)</h3>
            <?php if (empty($candidatures)): ?>
                <p class="empty-state">Aucune candidature.</p>
            <?php else: ?>
                <table class="dash-table">
                    <thead><tr><th>ID</th><th>Annonce</th><th>Candidat</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($candidatures as $c): ?>
                        <tr>
                            <td>#<?= (int)$c['candidature_id'] ?></td>
                            <td><a href="<?= BASE_URL ?>pages/mission.php?id=<?= (int)$c['candidature_id_mission'] ?>"><?= htmlspecialchars($c['mission_titre_mission'] ?? '—') ?></a></td>
                            <td><?= htmlspecialchars($c['freelance_nom'] ?? '—') ?> &middot; <?= htmlspecialchars($c['freelance_email'] ?? '') ?></td>
                            <td><span class="badge"><?= htmlspecialchars($c['candidature_statut']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($c['candidature_date'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette candidature ?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="candidature_id" value="<?= (int)$c['candidature_id'] ?>">
                                    <button type="submit" name="supprimer_candidature" class="btn btn-sm btn-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
