<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// Bascule visible / masqué d'un article depuis le tableau de bord.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_visible') {
    if (csrf_check($_POST['csrf'] ?? '')) {
        $aid = (int)($_POST['id'] ?? 0);
        if ($aid > 0) {
            $st = $pdo->prepare('SELECT visible FROM articles WHERE id = ?');
            $st->execute([$aid]);
            $cur = $st->fetchColumn();
            if ($cur !== false) {
                $new = (int)$cur === 1 ? 0 : 1;
                $pdo->prepare('UPDATE articles SET visible = ? WHERE id = ?')->execute([$new, $aid]);
                flash_set('success', $new ? 'Article rendu visible.' : 'Article masqué (brouillon).');
            }
        }
    }
    redirect(base_url('pages/admin.php'));
}

$stats = [
    'users'    => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'articles' => (int)$pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    'comments' => (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
    'socials'  => (int)$pdo->query('SELECT COUNT(*) FROM social_links')->fetchColumn(),
];

// Fréquentation : on distingue les vues de la page d'accueil (article_id = 0)
// des vues d'articles (article_id > 0). Visiteurs uniques = IP distinctes globales.
$stats['visitors']       = 0;
$stats['views_home']     = 0;
$stats['views_articles'] = 0;
try {
    $stats['visitors']       = (int)$pdo->query('SELECT COUNT(DISTINCT ip_hash) FROM article_views')->fetchColumn();
    $stats['views_home']     = (int)$pdo->query('SELECT COUNT(*) FROM article_views WHERE article_id = 0')->fetchColumn();
    $stats['views_articles'] = (int)$pdo->query('SELECT COUNT(*) FROM article_views WHERE article_id > 0')->fetchColumn();
} catch (Throwable $e) { /* table article_views pas encore migrée */ }

$users = $pdo->query('SELECT id, nom, prenom, email, is_admin, created_at FROM users ORDER BY created_at DESC')->fetchAll();

$articles = $pdo->query("
    SELECT a.id, a.titre, a.created_at, a.visible, u.prenom, u.nom,
           (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) AS nb_comments,
           (SELECT COUNT(*) FROM article_views v WHERE v.article_id = a.id) AS nb_views
    FROM articles a
    JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 50
")->fetchAll();

$comments = $pdo->query("
    SELECT c.id, c.contenu, c.created_at, c.article_id,
           u.prenom, u.nom, a.titre
    FROM comments c
    JOIN users u ON u.id = c.user_id
    JOIN articles a ON a.id = c.article_id
    ORDER BY c.created_at DESC
    LIMIT 50
")->fetchAll();

$pageTitle = 'Admin';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>Tableau de bord</h1>

    <div class="admin-actions">
        <a class="admin-action green" href="<?= e(base_url('pages/landing_settings.php')) ?>">
            <span class="ico">🎨</span>
            <span class="body">
                <h3>Apparence accueil</h3>
                <p>Personnalise la page d'accueil publique (textes, couleurs, bouton).</p>
            </span>
        </a>
        <a class="admin-action purple" href="<?= e(base_url('pages/blog_theme.php')) ?>">
            <span class="ico">🎭</span>
            <span class="body">
                <h3>Thème du blog</h3>
                <p>Change l'ambiance de tout le blog (gothique, nuit, sépia…).</p>
            </span>
        </a>
        <a class="admin-action blue" href="<?= e(base_url('pages/settings.php')) ?>">
            <span class="ico">⚙️</span>
            <span class="body">
                <h3>Paramètres du site</h3>
                <p>Nom du site, slogan, baseline, texte "À propos".</p>
            </span>
        </a>
        <a class="admin-action purple" href="<?= e(base_url('pages/social.php')) ?>">
            <span class="ico">🔗</span>
            <span class="body">
                <h3>Réseaux sociaux</h3>
                <p>Ajoute et organise les liens vers tes réseaux.</p>
            </span>
        </a>
        <a class="admin-action rose" href="<?= e(base_url('pages/sync_keys.php')) ?>">
            <span class="ico">🔑</span>
            <span class="body">
                <h3>Clés sync (serveur)</h3>
                <p>Génère une clé d'autorisation à usage unique pour recevoir une sync.</p>
            </span>
        </a>
        <a class="admin-action slate" href="<?= e(base_url('pages/sync_json.php')) ?>">
            <span class="ico">📦</span>
            <span class="body">
                <h3>Import / Export JSON</h3>
                <p>Sauvegarde ou restaure les données sous forme de fichier JSON.</p>
            </span>
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat"><span class="stat-num"><?= $stats['users'] ?></span><span class="stat-label">Utilisateurs</span></div>
        <div class="stat"><span class="stat-num"><?= $stats['articles'] ?></span><span class="stat-label">Articles</span></div>
        <div class="stat"><span class="stat-num"><?= $stats['comments'] ?></span><span class="stat-label">Commentaires</span></div>
        <div class="stat"><span class="stat-num"><?= $stats['socials'] ?></span><span class="stat-label">Réseaux sociaux</span></div>
        <div class="stat"><span class="stat-num">👁️ <?= $stats['visitors'] ?></span><span class="stat-label">Visiteurs uniques (IP)</span></div>
        <div class="stat"><span class="stat-num">🏠 <?= $stats['views_home'] ?></span><span class="stat-label">Vues page d'accueil</span></div>
        <div class="stat"><span class="stat-num">📄 <?= $stats['views_articles'] ?></span><span class="stat-label">Vues articles</span></div>
    </div>

    <h2>Utilisateurs</h2>
    <table class="admin-table">
        <thead><tr><th>Prénom</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscrit le</th></tr></thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['prenom']) ?></td>
                    <td><?= e($u['nom']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= $u['is_admin'] ? '<span class="badge badge-admin">admin</span>' : 'membre' ?></td>
                    <td><?= e($u['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Articles (50 plus récents)</h2>
    <table class="admin-table">
        <thead><tr><th>Titre</th><th>Auteur</th><th>Vues</th><th>Commentaires</th><th>Date</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($articles as $a): $isVisible = (int)$a['visible'] === 1; ?>
                <tr>
                    <td><a href="<?= e(base_url('pages/article.php?id=' . $a['id'])) ?>"><?= e($a['titre']) ?></a></td>
                    <td><?= e($a['prenom'] . ' ' . $a['nom']) ?></td>
                    <td>👁️ <?= (int)$a['nb_views'] ?></td>
                    <td><?= (int)$a['nb_comments'] ?></td>
                    <td><?= e($a['created_at']) ?></td>
                    <td>
                        <?php if ($isVisible): ?>
                            <span class="pill pill-ok">👁️ visible</span>
                        <?php else: ?>
                            <span class="pill pill-warn">🔒 masqué</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn-secondary" href="<?= e(base_url('pages/article_edit.php?id=' . $a['id'])) ?>">Modifier</a>
                        <form method="post" action="<?= e(base_url('pages/admin.php')) ?>" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle_visible">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="btn-secondary">
                                <?= $isVisible ? '🔒 Masquer' : '👁️ Rendre visible' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= e(base_url('pages/article_delete.php')) ?>" class="inline-form"
                              onsubmit="return confirm('Supprimer cet article ?');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="btn-danger">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Commentaires (50 plus récents)</h2>
    <table class="admin-table">
        <thead><tr><th>Auteur</th><th>Sur l'article</th><th>Contenu</th><th>Date</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($comments as $c): ?>
                <tr>
                    <td><?= e($c['prenom'] . ' ' . $c['nom']) ?></td>
                    <td><a href="<?= e(base_url('pages/article.php?id=' . $c['article_id'])) ?>"><?= e(mb_substr($c['titre'], 0, 40)) ?></a></td>
                    <td><?= e(mb_substr($c['contenu'], 0, 100)) ?><?= mb_strlen($c['contenu']) > 100 ? '…' : '' ?></td>
                    <td><?= e($c['created_at']) ?></td>
                    <td>
                        <form method="post" action="<?= e(base_url('pages/comment_delete.php')) ?>" class="inline-form"
                              onsubmit="return confirm('Supprimer ce commentaire ?');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn-danger">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
